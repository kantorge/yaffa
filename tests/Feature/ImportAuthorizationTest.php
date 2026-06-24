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
        $this->postJson(route('api.v1.imports.file-profiles.clone', $profile))->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_user_can_crud_own_profiles_and_clone_system_profiles(): void
    {
        $user = User::factory()->create();
        $systemProfile = $this->createSystemProfile();

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
                    'trim_strings' => true,
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

        $cloneResponse = $this->actingAs($user, 'sanctum')
            ->postJson(route('api.v1.imports.file-profiles.clone', $systemProfile), [
                'name' => 'Cloned System Profile',
            ]);

        $cloneResponse->assertCreated()
            ->assertJsonPath('data.type', 'user')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.key', null);

        $this->assertNull(data_get($cloneResponse->json('data.options_json'), 'matching_rules'));
        $this->assertNull(data_get($cloneResponse->json('data.options_json'), 'defaults'));

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

    private function createSystemProfile(): FileImportProfile
    {
        $definition = (new SystemFileImportProfileRegistry())->profiles()[0];

        return FileImportProfile::query()->updateOrCreate(
            ['key' => $definition['key']],
            [
                'user_id' => null,
                'type' => 'system',
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
            ],
        );
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
