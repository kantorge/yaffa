<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use App\Models\User;
use App\Services\Import\SystemFileImportProfileRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class ImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_requests_to_import_profile_routes_are_denied(): void
    {
        $profile = FileImportProfile::factory()->create();

        $this->getJson(route('api.v1.imports.file-profiles.index'))->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->postJson(route('api.v1.imports.file-profiles.store'), [])->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->patchJson(route('api.v1.imports.file-profiles.update', $profile), [])->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->deleteJson(route('api.v1.imports.file-profiles.destroy', $profile))->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_user_can_crud_own_profiles(): void
    {
        $user = User::factory()->create();

        $storeResponse = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'My Import Profile',
                'delimiter' => ',',
                'has_header_row' => true,
                'mapping_json' => [
                    'Date' => 'date',
                    'Amount' => 'amount',
                    'Payee' => 'payee',
                ],
                'options_json' => [
                    'parser_settings' => ['trim_strings' => true],
                ],
            ]);

        $storeResponse->assertCreated();

        $profileId = $storeResponse->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.imports.file-profiles.update', $profileId), [
                'name' => 'Updated Import Profile',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Import Profile');

        $this->actingAs($user, 'sanctum')
            ->deleteJson(route('api.v1.imports.file-profiles.destroy', $profileId))
            ->assertNoContent();
    }

    public function test_user_cannot_edit_another_users_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $profile = FileImportProfile::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.imports.file-profiles.update', $profile), [
                'name' => 'Should fail',
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_system_profiles(): void
    {
        $user = User::factory()->create();
        $profile = $this->createSystemProfile();

        $this->actingAs($user, 'sanctum')
            ->deleteJson(route('api.v1.imports.file-profiles.destroy', $profile))
            ->assertForbidden();
    }

    public function test_user_can_update_account_preferred_profile_only_with_accessible_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $accountEntity = $this->createAccountEntity($user);
        $accountEntity->load('config');

        $ownProfile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);
        $foreignProfile = FileImportProfile::factory()->create([
            'user_id' => $otherUser->id,
            'type' => 'user',
        ]);

        $formData = [
            'config_type' => 'account',
            'name' => $accountEntity->name,
            'active' => 1,
            'config' => [
                'opening_balance' => $accountEntity->config->opening_balance,
                'account_group_id' => $accountEntity->config->account_group_id,
                'currency_id' => $accountEntity->config->currency_id,
            ],
            'preferred_file_import_profile_id' => $ownProfile->id,
        ];

        // User can set their own profile
        $this->actingAs($user)
            ->patch(route('account-entity.update', $accountEntity), $formData)
            ->assertRedirect(route('account-entity.index', ['type' => 'account']));

        $this->assertSame($ownProfile->id, $accountEntity->fresh()->preferred_file_import_profile_id);

        // User cannot set a foreign (inaccessible) profile
        $this->actingAs($user)
            ->patch(route('account-entity.update', $accountEntity), array_merge($formData, [
                'preferred_file_import_profile_id' => $foreignProfile->id,
            ]))
            ->assertSessionHasErrors('preferred_file_import_profile_id');
    }

    public function test_index_includes_account_entities_for_current_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $profile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $ownAccount = $this->createAccountEntity($user);
        $ownAccount->preferred_file_import_profile_id = $profile->id;
        $ownAccount->save();

        $otherAccount = $this->createAccountEntity($otherUser);
        $otherAccount->preferred_file_import_profile_id = $profile->id;
        $otherAccount->save();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson(route('api.v1.imports.file-profiles.index'));

        $response->assertOk();

        $profileData = collect($response->json('data'))->firstWhere('id', $profile->id);
        $this->assertNotNull($profileData);

        $accountEntities = $profileData['account_entities'] ?? null;
        $this->assertIsArray($accountEntities);
        $this->assertCount(1, $accountEntities);
        $this->assertSame($ownAccount->id, $accountEntities[0]['id']);
        $this->assertNotContains($otherAccount->id, array_column($accountEntities, 'id'));
    }

    public function test_cannot_delete_profile_in_use_by_an_account(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $account = $this->createAccountEntity($user);
        $account->preferred_file_import_profile_id = $profile->id;
        $account->save();

        $this->actingAs($user, 'sanctum')
            ->deleteJson(route('api.v1.imports.file-profiles.destroy', $profile))
            ->assertUnprocessable();

        $this->assertDatabaseHas('file_import_profiles', ['id' => $profile->id]);
    }

    public function test_store_rejects_unknown_options_json_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'Bad Options Profile',
                'options_json' => [
                    'trim_strings' => true,
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options_json']);
    }

    public function test_store_accepts_valid_options_json_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'Good Options Profile',
                'options_json' => [
                    'parser_settings' => [
                        'trim_strings' => true,
                        'skip_empty_rows' => false,
                    ],
                    'comment_separator' => ' - ',
                ],
            ])
            ->assertCreated();
    }

    public function test_update_rejects_unknown_options_json_keys(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::factory()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.imports.file-profiles.update', $profile), [
                'options_json' => [
                    'bogus_key' => 'not allowed',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options_json']);
    }

    public function test_store_accepts_qif_field_map_and_amount_sign_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.store'), [
                'name' => 'Custom QIF Profile',
                'file_type' => 'qif',
                'options_json' => [
                    'field_map' => [
                        'payee' => 'M',
                        'comment' => 'P',
                    ],
                    'amount_sign' => 'inverted',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.options_json.field_map.payee', 'M')
            ->assertJsonPath('data.options_json.amount_sign', 'inverted');
    }

    public function test_update_rejects_unknown_field_map_key(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::factory()->qif()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.imports.file-profiles.update', $profile), [
                'options_json' => [
                    'field_map' => ['unknown_field' => 'M'],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options_json.field_map']);
    }

    public function test_update_rejects_invalid_amount_sign(): void
    {
        $user = User::factory()->create();

        $profile = FileImportProfile::factory()->qif()->create([
            'user_id' => $user->id,
            'type' => 'user',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson(route('api.v1.imports.file-profiles.update', $profile), [
                'options_json' => [
                    'amount_sign' => 'sideways',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options_json.amount_sign']);
    }

    private function createSystemProfile(): FileImportProfile
    {
        $definition = (new SystemFileImportProfileRegistry())->profiles()[0];

        $record = FileImportProfile::query()->firstOrNew(['key' => $definition['key']]);
        $record->fill([
            'name' => $definition['name'],
            'delimiter' => $definition['delimiter'],
            'has_header_row' => $definition['has_header_row'],
            'date_format' => $definition['date_format'],
            'decimal_separator' => $definition['decimal_separator'],
            'thousand_separator' => $definition['thousand_separator'],
            'sign_handling' => $definition['sign_handling'],
            'mapping_json' => $definition['mapping_json'],
            'options_json' => $definition['options_json'],
            'active' => true,
        ]);
        $record->key = $definition['key'];
        $record->user_id = null;
        $record->type = 'system';
        $record->save();

        return $record;
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
