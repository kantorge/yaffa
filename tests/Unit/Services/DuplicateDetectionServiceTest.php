<?php

namespace Tests\Unit\Services;

use App\Models\AiUserSettings;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestTransactions;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use CreatesTestTransactions;
    use RefreshDatabase;

    public function test_find_duplicates_uses_user_date_window(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'duplicate_date_window_days' => 1,
            'duplicate_amount_tolerance_percent' => 10,
            'duplicate_similarity_threshold' => 0.0,
        ]);

        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, ['active' => true]);

        $this->createStandardTransaction(
            user: $user,
            accountFromId: $account->id,
            accountToId: $payee->id,
            amount: 50,
            date: now()->subDays(3),
        );

        $service = new DuplicateDetectionService();
        $matches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 50,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->assertSame([], $matches);
    }

    public function test_find_duplicates_uses_user_similarity_threshold(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'duplicate_date_window_days' => 7,
            'duplicate_amount_tolerance_percent' => 10,
            'duplicate_similarity_threshold' => 1.0,
        ]);

        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, ['active' => true]);

        $transaction = $this->createStandardTransaction(
            user: $user,
            accountFromId: $account->id,
            accountToId: $payee->id,
            amount: 99,
            date: now(),
        );

        $service = new DuplicateDetectionService();
        $matches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 99,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->assertSame([], $matches);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_exact_amount_match_scores_higher_than_tolerance_match(): void
    {
        $user = User::factory()->create();

        AiUserSettings::factory()->create([
            'user_id' => $user->id,
            'duplicate_date_window_days' => 3,
            'duplicate_amount_tolerance_percent' => 10,
            'duplicate_similarity_threshold' => 0.0,
        ]);

        $account = $this->createAccountEntity($user);
        $payee = $this->createPayeeEntity($user, ['active' => true]);

        $transaction = $this->createStandardTransaction(
            user: $user,
            accountFromId: $account->id,
            accountToId: $payee->id,
            amount: 100,
            date: now(),
        );

        $service = new DuplicateDetectionService();

        $exactMatches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 100,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $toleranceMatches = $service->findDuplicates($user, [
            'date' => now()->toDateString(),
            'amount' => 105,
            'config_type' => 'standard',
            'account_from_id' => $account->id,
            'account_to_id' => $payee->id,
        ]);

        $this->assertCount(1, $exactMatches);
        $this->assertCount(1, $toleranceMatches);
        $this->assertSame($transaction->id, $exactMatches[0]['id']);
        $this->assertSame(1.0, $exactMatches[0]['similarity']);
        $this->assertLessThan($exactMatches[0]['similarity'], $toleranceMatches[0]['similarity']);
    }
}
