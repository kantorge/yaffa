<?php

namespace App\Jobs;

use App\Models\ReceivedMail;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use OpenAI\Laravel\Facades\OpenAI;

class ProcessLandlordPdfByAi implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public ReceivedMail $mail;

    private const string AI_PROMPT_LANDLORD = <<<'EOF'
I will provide you the text extracted from a landlord statement PDF.
The statement contains multiple financial transactions (rent payments, expenses, etc).
I'd like you to extract ALL transactions from it.

The response should be a JSON array of transactions, without any additional text or explanation.
Each transaction should have these fields:

{
  "type": <transaction type, exactly one of: deposit, withdrawal>,
  "date": <transaction date in format yyyy-mm-dd>,
  "description": <description of the transaction>,
  "amount": <amount as a positive number, no currency symbols>,
  "category": <category: "rent", "maintenance", "insurance", "management_fee", "utility", "other">
}

For a landlord statement:
- Rent received = "deposit" (money coming in)
- All expenses (maintenance, fees, etc) = "withdrawal" (money going out)
- Convert all dates to yyyy-mm-dd format
- Remove currency symbols and thousand separators from amounts
- Use the description from the statement as-is

The text to process is the following:
"""
%s
"""
EOF;

    public function __construct(ReceivedMail $mail)
    {
        $this->mail = $mail;
    }

    public function uniqueId()
    {
        return $this->mail->id;
    }

    public function handle(): void
    {
        logger()->info('Processing landlord PDF', [
            'message_id' => $this->mail->message_id,
            'user' => $this->mail->user,
            'subject' => $this->mail->subject,
        ]);

        try {
            // Get transactions from the PDF text
            $transactions = $this->extractTransactions($this->mail->text);

            if (empty($transactions)) {
                throw new Exception('No transactions could be extracted from the PDF.');
            }

            // Store the extracted transactions in transaction_data
            $this->mail->transaction_data = [
                'source' => 'landlord_pdf',
                'transactions' => $transactions,
                'total_transactions' => count($transactions),
            ];

            $this->markMailAsProcessed();

            logger()->info('Landlord PDF processed successfully', [
                'message_id' => $this->mail->message_id,
                'transaction_count' => count($transactions),
            ]);

        } catch (Exception $e) {
            logger()->error('Error processing landlord PDF', [
                'message_id' => $this->mail->message_id,
                'error' => $e->getMessage(),
            ]);

            $this->mail->transaction_data = [
                'error' => $e->getMessage(),
            ];

            $this->markMailAsProcessed();
        }
    }

    private function extractTransactions(string $text): array
    {
        // Clean up the text
        $text = $this->cleanText($text);

        // Call OpenAI to extract transactions
        $prompt = sprintf(self::AI_PROMPT_LANDLORD, $text);

        $response = $this->getAiResponse($prompt);

        $resultText = trim($response->choices[0]->text);

        // Parse JSON response
        $transactions = json_decode($resultText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse AI response as JSON: ' . json_last_error_msg());
        }

        if (!is_array($transactions)) {
            throw new Exception('AI response is not an array of transactions');
        }

        // Validate and clean each transaction
        $validTransactions = [];
        foreach ($transactions as $transaction) {
            if ($this->isValidTransaction($transaction)) {
                $validTransactions[] = $transaction;
            }
        }

        return $validTransactions;
    }

    private function cleanText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Limit length to avoid token limits (keep first 15000 characters)
        if (strlen($text) > 15000) {
            $text = substr($text, 0, 15000);
        }

        return trim($text);
    }

    private function isValidTransaction(array $transaction): bool
    {
        // Check required fields
        $requiredFields = ['type', 'date', 'description', 'amount'];
        
        foreach ($requiredFields as $field) {
            if (!isset($transaction[$field]) || empty($transaction[$field])) {
                return false;
            }
        }

        // Validate type
        if (!in_array($transaction['type'], ['deposit', 'withdrawal'])) {
            return false;
        }

        // Validate amount is numeric
        if (!is_numeric($transaction['amount'])) {
            return false;
        }

        return true;
    }

    private function getAiResponse(string $prompt): \OpenAI\Responses\Completions\CreateResponse
    {
        return OpenAI::completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => $prompt,
            'max_tokens' => 2000,
            'temperature' => 0.1,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0,
        ]);
    }

    private function markMailAsProcessed(): void
    {
        $this->mail->processed = true;
        $this->mail->save();
    }
}
