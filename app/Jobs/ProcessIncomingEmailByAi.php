<?php

namespace App\Jobs;

use App\Mail\TransactionCreatedFromEmail;
use App\Mail\TransactionErrorFromEmail;
use App\Models\ReceivedMail;
use App\Models\TransactionType;
use App\Models\User;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use JsonException;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Completions\CreateResponse;

class ProcessIncomingEmailByAi implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ReceivedMail $mail;

    private const string AI_PROMPT_MAIN = <<<'EOF'
I will provide you the text body of an email, which is a receipt of a financial transaction.
The language used in the email is unknown.
I'd like you to extract certain information from it.
The response should be in JSON format, without any additional text or explanation.

The desired output format is the following.
All keys are required. If a value is not available or cannot be determined, mark the entire value as null.

{
  "type": <extracted transaction type, exactly one of: withdrawal, deposit>,
  "account": <extracted account used for payment or to receive money>,
  "payee": <extracted payee where the purchase happened, or who sent the money>,
  "date": <extracted date in the format of yyyy-mm-dd, fallback to current date if cannot be determined>,
  "amount": <extracted total amount of the transaction, no currency label and no thousand separators>,
  "currency": <ISO code of currency used in the transaction>
}

Further details about the expected values:
* type
** The type is withdrawal if money was spent, and deposit if money was received.
** Any order or purchase is a type of withdrawal, any income is a type of deposit.
* account
** The account can also be a credit card, a bank account, a PayPal account, or any other account.
** The account can also be referred to as "funding source" or "payment method".

The text to process is the following:
"""
%s
"""
EOF;

    private const string AI_PROMPT_ACCOUNT = <<<'EOF'
I will provide you a list of accounts and their IDs in the following format: "ID: Account name|Optional list of aliases"
I'd like you to identify the ID of the account used for payment in the email,
based on the account name or any of the aliases.
The account name is the first part of the string before the pipe character, and the aliases are the rest, if present.
The name takes precedence over the aliases.
Please provide the ID of the account, without its name or any further explanation.
If there is no match, please provide N/A.

The list of accounts is the following:
"""
%s
"""

The text to process is the following:
"""
%s
"""
EOF;

    private const string AI_PROMPT_PAYEE = <<<'EOF'
I will provide you a list of payees and their IDs in the following format: "ID: Payee name"
I'd like you to identify the ID of the payee where the purchase happened in the email, based on the payee name.
Please provide the ID of the payee, without its name or any further explanation.
If there is no match, please provide N/A.

The list of payees is the following:
"""
%s
"""

The text to process is the following:
"""
%s
"""
EOF;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(ReceivedMail $mail)
    {
        $this->mail = $mail;
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId()
    {
        return $this->mail->id;
    }

    /**
     * Execute the job.
     *
     */
    public function handle(): void
    {
        logger()->info('Processing incoming email', [
            'message_id' => $this->mail->message_id,
            'sender' => $this->mail->sender,
            'user' => $this->mail->user,
            'subject' => $this->mail->subject,
            'raw_text' => $this->mail->text,
        ]);

        try {
            // Retrieve the values from the email
            $values = $this->getValuesFromEmail($this->mail->text);

            // At the moment, only withdrawals and deposits are supported. Pther transaction types throw an error.
            if (!in_array($values['type'], ['withdrawal', 'deposit'])) {
                throw new Exception('Only withdrawals and deposits are supported at the moment.');
            }

            // Retrieve the transaction type ID from the transaction type name
            $values['transaction_type_id'] = $this->getTransactionTypeIdFromType($values['type']);
        } catch (Exception $e) {
            // Send a response email to the user with the error message
            $this->sendErrorEmail($this->mail, $e->getMessage());

            // Mark the email as processed
            $this->markMailAsProcessed();

            return;
        }

        // Retrieve certain components from the assets of the user - account
        $values['account_id'] = $this->getAccountIdFromAccount($this->mail->user, $values['account']);

        // Retrieve certain components from the assets of the user - payee
        $values['payee_id'] = $this->getPayeeIdFromPayee($this->mail->user, $values['payee']);

        // Create a new transaction
        $this->mail->transaction_data = $this->createTransaction($values, $this->mail->user);

        // Mark the email as processed
        $this->markMailAsProcessed();

        // Send a response email to the user with the summary of the transaction
        $this->sendResponseEmail($this->mail);
    }

    private function createTransaction(array $values, User $user): array
    {
        // Create an array from the extracted values, which is aligned with the structure of the transaction config
        $data = [
            'transaction_type_id' => $values['transaction_type_id'],
            'date' => $values['date'],
            'config_type' => 'standard',
            'config' => [
                'amount_from' => floatval($values['amount']),
                'amount_to' => floatval($values['amount']),
                'account_from_id' => $values['type'] === 'withdrawal' ? $values['account_id'] : $values['payee_id'],
                'account_to_id' => $values['type'] === 'withdrawal' ? $values['payee_id'] : $values['account_id'],
            ],
            'transaction_type' => [
                'name' => $values['type'],
            ],
            'raw' => $values,
        ];

        // Create log for the new transaction
        logger()->info('New transaction created from email', [
            'transaction' => $data,
        ]);

        return $data;
    }

    /**
     * @throws Exception
     */
    private function getValuesFromEmail(string $text): array
    {
        // Currently the text is too long for the AI to process, so we need to clean it up a bit
        // TODO: make this an optional user setting
        $text = $this->cleanUpText($text);

        $response = $this->getAiResponse(sprintf(self::AI_PROMPT_MAIN, $text));

        logger()->debug('OpenAI response - mail parse', [
            'cleaned_text' => $text,
            'response' => $response,
        ]);

        // Process the JSON response into an associative array
        try {
            $result = json_decode($response['choices'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            // TODO: add retry mechanism by enabling the user to mark the email as unprocessed

            logger()->error('Failed to parse AI response', [
                'response' => $response,
                'exception' => $exception,
            ]);

            throw new Exception('Failed to parse AI response');
        }

        return $result;
    }

    /**
     * Clean up the text to make it easier for the AI to process. Remove image and link references.
     * @param string $text
     * @return string
     */
    private function cleanUpText(string $text): string
    {
        // Remove image references
        $text = preg_replace('/\[image:.*?\]/', '', $text);

        // Remove link references
        return preg_replace('/<http[^>]+>/', '', $text);
    }

    /**
     * Get a list of all active accounts that are similar to the provided name.
     *
     * @param string $name The name of the account to search for.
     * @param int $limit The maximum number of accounts to return.
     * @param User $user The user to search for accounts for.
     * @return Collection
     */
    private function getSimilarAccounts(string $name, int $limit, User $user): Collection
    {
        // Get all active accounts for the user
        $accounts = $user->accounts()->active()->get();

        return $accounts->map(function ($account) use ($name) {
            // Calculate the similarity of the account name to the provided name
            // Use the same letter case for both strings, to get a more accurate result
            similar_text(Str::lower($account->name), Str::lower($name), $similarity_name);

            if ($account->alias !== null) {
                similar_text(Str::lower($account->alias), Str::lower($name), $similarity_alias);
            } else {
                $similarity_alias = 0;
            }

            $account->similarity = max($similarity_name, $similarity_alias);

            return $account;
        })
            ->sortByDesc('similarity')
            ->take($limit);
    }

    /**
     * Get a list of all active payees that are similar to the provided name.
     *
     * @param string $name The name of the payee to search for.
     * @param int $limit The maximum number of payees to return.
     * @param User $user The user to search for payees for.
     * @return Collection
     */
    private function getSimilarPayees(string $name, int $limit, User $user): Collection
    {
        // Get all active payees for the user
        $payees = $user->payees()->active()->get();

        return $payees->map(function ($payee) use ($name) {
            // Calculate the similarity of the payee name to the provided name
            // Use the same letter case for both strings, to get a more accurate result
            similar_text(Str::lower($payee->name), Str::lower($name), $similarity_name);

            if ($payee->alias !== null) {
                similar_text(Str::lower($payee->alias), Str::lower($name), $similarity_alias);
            } else {
                $similarity_alias = 0;
            }

            $payee->similarity = max($similarity_name, $similarity_alias);

            return $payee;
        })
            ->sortByDesc('similarity')
            ->take($limit);
    }

    private function getAccountIdFromAccount(User $user, string $account = null): ?int
    {
        if (!$account) {
            return null;
        }

        $response = $this->getAiResponse(
            sprintf(
                self::AI_PROMPT_ACCOUNT,
                $this->getSimilarAccounts($account, 10, $user),
                $account
            )
        );

        $result = $response['choices'][0]['text'];

        logger()->debug('OpenAI response - account parse', [
            'response' => $response,
            'result' => $result,
        ]);

        return mb_trim($result) !== 'N/A' ? (int) $result : null;
    }

    private function getPayeeIdFromPayee(User $user, string $payee = null): ?int
    {
        if (!$payee) {
            return null;
        }

        $response = $this->getAiResponse(
            sprintf(
                self::AI_PROMPT_PAYEE,
                $this->getSimilarPayees($payee, 10, $user),
                $payee
            )
        );

        $result = $response['choices'][0]['text'];

        logger()->debug('OpenAI response - payee parse', [
            'response' => $response,
            'result' => $result,
        ]);

        return mb_trim($result) !== 'N/A' ? (int) $result : null;
    }

    private function getTransactionTypeIdFromType(string $type): int
    {
        return TransactionType::where('name', $type)
            // Investment types to be supported later
            ->where('type', 'standard')
            ->firstOr(function () {
                $this->markMailAsProcessed();
                throw new Exception('Transaction type could not be determined');
            })
            ->id;
    }

    private function markMailAsProcessed(): void
    {
        $this->mail->processed = true;
        $this->mail->save();
    }

    /**
     * @param string $prompt
     * @param array $attributes
     * @return CreateResponse
     */
    private function getAiResponse(string $prompt, array $attributes = []): CreateResponse
    {
        // Merge the attributes with defaults
        $attributes = array_merge(
            [
                'model' => 'gpt-3.5-turbo-instruct',
                'max_tokens' => 256,
                'temperature' => 0.1,
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
                'best_of' => 1,
            ],
            $attributes
        );

        $attributes['prompt'] = $prompt;

        return OpenAI::completions()->create($attributes);
    }

    /**
     * Send a response email to the user with the summary of the transaction created.
     *
     * @param ReceivedMail $mail The originally received mail to send the response for.
     */
    private function sendResponseEmail(ReceivedMail $mail): void
    {
        // Send a response email to the user with the summary of the transaction
        Mail::to($mail->user->email)->send(new TransactionCreatedFromEmail($mail));
    }

    private function sendErrorEmail(ReceivedMail $mail, string $error): void
    {
        // Send a response email to the user with the summary of the transaction
        Mail::to($mail->user->email)->send(new TransactionErrorFromEmail($mail, $error));
    }
}
