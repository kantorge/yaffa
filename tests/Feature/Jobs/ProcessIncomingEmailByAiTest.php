<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessIncomingEmailByAi;
use App\Mail\TransactionCreatedFromEmail;
use App\Mail\TransactionErrorFromEmail;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
use App\Models\ReceivedMail;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Completions\CreateResponse;
use Tests\TestCase;

class ProcessIncomingEmailByAiTest extends TestCase
{
    use RefreshDatabase;

    public function test_processing_fails_if_main_response_is_not_valid_json(): void
    {
        // Generate a user and a fake incoming email
        $user = User::factory()->create();
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create();

        // The AI response is mocked, main response is not valid JSON
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'text' => 'This is not valid JSON',
                    ],
                ],
            ]),
        ]);

        Mail::fake();

        // Execute the job using the created fake email
        ProcessIncomingEmailByAi::dispatch($mail);

        // Assert that the job fails and error message was sent to the user
        Mail::assertSent(fn (TransactionErrorFromEmail $mail) => $mail->hasTo($user->email));

        // Assert that the mail is marked as processed
        $this->assertTrue($mail->fresh()->processed);

        // Assert that the mail has no transaction associated to it in the database
        $this->assertNull($mail->fresh()->transaction);
    }

    public function test_processing_fails_if_transaction_type_is_not_recognized(): void
    {
        // Generate a user and a fake incoming email
        $user = User::factory()->create();
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create();

        // The AI response is mocked, type is undefined
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'text' => '{type: null}',
                    ],
                ],
            ]),
        ]);

        Mail::fake();

        // Execute the job using the created fake email
        ProcessIncomingEmailByAi::dispatch($mail);

        // Assert that the job fails and error message was sent to the user
        Mail::assertSent(fn (TransactionErrorFromEmail $mail) => $mail->hasTo($user->email));

        // Assert that the mail is marked as processed
        $this->assertTrue($mail->fresh()->processed);

        // Assert that the mail has no transaction associated to it in the database
        $this->assertNull($mail->fresh()->transaction);
    }

    public function test_transaction_data_array_is_created_if_processing_is_successful(): void
    {
        // Generate a user and a fake incoming email
        /** @var User $user */
        $user = User::factory()->create();
        $mail = ReceivedMail::factory()
            ->for($user)
            ->create();

        // Generate further necessary assets - account
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(), 'config')
            ->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user)->create(), 'config')
            ->create();

        $date = Carbon::now();

        $amount = 100;

        // The AI response is mocked, with required data
        // Main mail processing
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [
                    [
                        'text' => '{
                            "type": "withdrawal",
                            "account": "' . $account->name . '",
                            "payee": "' . $payee->name . '",
                            "date": "' . $date->format('Y-m-d') . '",
                            "amount": "' . $amount . '"
                        }',
                    ],
                ],
            ]),
        ]);

        // Identify account
        OpenAI::addResponses([
            CreateResponse::fake([
                'choices' => [
                    [
                        'text' => (string) $account->id,
                    ],
                ],
            ]),
        ]);
        // Identify payee
        OpenAI::addResponses([
            CreateResponse::fake([
                'choices' => [
                    [
                        'text' => (string) $payee->id,
                    ],
                ],
            ]),
        ]);

        Mail::fake();

        // Execute the job using the created fake email
        $job = new ProcessIncomingEmailByAi($mail);
        $job->handle();

        // Assert that reply email is sent to the user
        Mail::assertSent(fn (TransactionCreatedFromEmail $mail) => $mail->hasTo($user->email));

        $mail->fresh();

        // Assert that the mail is marked as processed
        $this->assertTrue($mail->processed);

        // Assert that the transaction_data array is created and contains the required data
        $this->assertNotNull($mail->transaction_data);
        $this->assertEquals($mail->transaction_data['transaction_type']['name'], 'withdrawal');
        $this->assertEquals(
            $mail->transaction_data['transaction_type_id'],
            TransactionType::where('name', 'withdrawal')->first()->id
        );
        $this->assertEquals(
            $mail->transaction_data['date'],
            $date->format('Y-m-d')
        );
        $this->assertEquals(
            $mail->transaction_data['config']['account_from_id'],
            $account->id
        );
        $this->assertEquals(
            $mail->transaction_data['config']['account_to_id'],
            $payee->id
        );
        $this->assertEquals(
            $mail->transaction_data['config']['amount_from'],
            $amount
        );
        $this->assertEquals(
            $mail->transaction_data['config']['amount_to'],
            $amount
        );
    }
}
