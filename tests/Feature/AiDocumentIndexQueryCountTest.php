<?php

use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

it('resolves matched entities for the whole page without per-document queries', function () {
    $user = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($user)->create();

    $makeDocuments = fn (int $count) => AiDocument::factory()->count($count)->for($user)->create([
        'processed_transaction_data' => [
            'config_type' => 'standard',
            'transaction_type' => 'withdrawal',
            'config' => ['account_from_id' => $account->id, 'account_to_id' => $account->id],
        ],
    ]);

    Sanctum::actingAs($user, ['*']);

    $countQueries = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson(route('api.v1.documents.index'))
            ->assertOk()
            ->assertJsonPath('data.0.processed_transaction_data.matched_entities.account.id', AccountEntity::first()->id);

        return count(DB::getQueryLog());
    };

    $makeDocuments(2);
    $few = $countQueries();

    $makeDocuments(8);

    expect($countQueries())->toBe($few);
});
