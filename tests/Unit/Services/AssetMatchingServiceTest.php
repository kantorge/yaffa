<?php

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiUserSettings;
use App\Models\Payee;
use App\Models\User;
use App\Services\AssetMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_payees_uses_user_similarity_threshold(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 1.0,
            'asset_max_suggestions' => 10,
        ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Auchan Magyarorszag Kft',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'payee',
                'active' => true,
                'name' => 'Lidl Magyarorszag',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchPayees('AUCHAN MAGYARORSZAG KFT');

        $this->assertCount(1, $matches);
        $this->assertSame('Auchan Magyarorszag Kft', $matches[0]['name']);
        $this->assertSame(1.0, $matches[0]['similarity']);
    }

    public function test_match_accounts_uses_user_max_suggestions(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'asset_similarity_threshold' => 0.0,
            'asset_max_suggestions' => 1,
        ]);

        $expectedTopMatch = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Alpha Account',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Beta Account',
                'alias' => null,
            ]);

        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'config_type' => 'account',
                'active' => true,
                'name' => 'Gamma Account',
                'alias' => null,
            ]);

        $service = new AssetMatchingService($user);
        $matches = $service->matchAccounts('alpha');

        $this->assertCount(1, $matches);
        $this->assertSame($expectedTopMatch->id, $matches[0]['id']);
    }
}
