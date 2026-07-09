<?php

namespace Tests\Feature\API\V1;

use App\Exceptions\AiProviderFailureException;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiProviderConfig;
use App\Models\User;
use App\Services\Import\AiImportProfileSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Tests\TestCase;

class ImportProfileSuggestTest extends TestCase
{
    use RefreshDatabase;

    private const string SAMPLE_CSV = "Date,Amount,Payee,Description\n2025-01-01,100.00,Grocery Store,Weekly shopping\n2025-01-02,-200.00,Landlord,Rent payment";

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson(route('api.v1.imports.file-profiles.suggest'), [
            'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
        ])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_returns_422_when_no_ai_provider_is_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'AI provider'));
    }

    public function test_returns_suggestion_for_authenticated_user_with_ai_provider(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $suggestion = [
            'delimiter' => ',',
            'has_header_row' => true,
            'date_format' => 'Y-m-d',
            'decimal_separator' => '.',
            'thousand_separator' => '',
            'sign_handling' => 'as_is',
            'mapping_json' => [
                'Date' => 'date',
                'Amount' => 'amount',
                'Payee' => 'payee',
                'Description' => 'comment',
            ],
            'confidence_notes' => [
                ['field' => 'date_format', 'note' => 'ISO 8601 format detected from sample values.'],
            ],
        ];

        $this->mock(AiImportProfileSuggestionService::class)
            ->shouldReceive('suggest')
            ->once()
            ->andReturn($suggestion);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.delimiter', ',')
            ->assertJsonPath('data.has_header_row', true)
            ->assertJsonPath('data.date_format', 'Y-m-d')
            ->assertJsonPath('data.sign_handling', 'as_is')
            ->assertJsonPath('data.mapping_json.Date', 'date')
            ->assertJsonPath('data.mapping_json.Amount', 'amount');
    }

    public function test_service_is_called_with_csv_content_from_uploaded_file(): void
    {
        // Trimming to 10 rows is unit-tested in AiImportProfileSuggestionServiceTest.
        // This test verifies the endpoint reads the file and forwards its content to suggest().
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $this->mock(AiImportProfileSuggestionService::class)
            ->shouldReceive('suggest')
            ->once()
            ->withArgs(fn (AiProviderConfig $config, string $csvContent, ?int $accountId): bool => str_contains($csvContent, 'Date') && $accountId === null)
            ->andReturn([
                'delimiter' => ',',
                'has_header_row' => true,
                'date_format' => 'Y-m-d',
                'decimal_separator' => '.',
                'thousand_separator' => '',
                'sign_handling' => 'as_is',
                'mapping_json' => ['Date' => 'date', 'Amount' => 'amount'],
                'confidence_notes' => [],
            ]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
            ])
            ->assertOk();
    }

    public function test_returns_422_when_uploaded_file_is_not_parseable_csv(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $this->mock(AiImportProfileSuggestionService::class)
            ->shouldReceive('suggest')
            ->once()
            ->andThrow(new RuntimeException('The uploaded file could not be parsed as a CSV file.'));

        // Non-empty content that passes the controller's empty-file guard but fails in the service
        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('bad.csv', 'not-csv-content'),
            ])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'parsed'));
    }

    public function test_returns_502_when_ai_provider_call_fails(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $this->mock(AiImportProfileSuggestionService::class)
            ->shouldReceive('suggest')
            ->once()
            ->andThrow(new AiProviderFailureException(
                step: 'import_profile_suggestion',
                provider: 'openai',
                model: 'gpt-4o',
                timeout: false,
                message: 'AI provider error: connection refused',
            ));

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
            ])
            ->assertStatus(Response::HTTP_BAD_GATEWAY)
            ->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'AI provider'));
    }

    public function test_optional_account_id_is_passed_to_suggestion_service(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();
        $account = $this->createAccountEntity($user);

        $this->mock(AiImportProfileSuggestionService::class)
            ->shouldReceive('suggest')
            ->once()
            ->withArgs(fn (AiProviderConfig $config, string $csvContent, ?int $accountId) => $accountId === $account->id)
            ->andReturn([
                'delimiter' => ',',
                'has_header_row' => true,
                'date_format' => 'Y-m-d',
                'decimal_separator' => '.',
                'thousand_separator' => '',
                'sign_handling' => 'as_is',
                'mapping_json' => ['Date' => 'date', 'Amount' => 'amount'],
                'confidence_notes' => [],
            ]);

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [
                'file' => UploadedFile::fake()->createWithContent('sample.csv', self::SAMPLE_CSV),
                'account_id' => $account->id,
            ])
            ->assertOk();
    }

    public function test_returns_422_when_file_field_is_missing(): void
    {
        $user = User::factory()->create();
        AiProviderConfig::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.suggest'), [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonValidationErrors(['file']);
    }

    private function createAccountEntity(User $user): AccountEntity
    {
        return AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
            ]);
    }
}
