<?php

use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('lists an unreviewed auto-recorded transaction and removes it once confirmed', function () {
    $transaction = Transaction::where('user_id', $this->user->id)->where('config_type', 'standard')->firstOrFail();
    $document = AiDocument::factory()->for($this->user)->autoRecorded()->create();
    $origin = TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CREATED, $document, 'Browser test reason');

    $page = visit(route('ai-documents.index', ['tab' => 'auto-recorded']));
    $this->waitUntil($page, "document.querySelector('[dusk=\"origin-{$origin->id}\"]')");

    $page->assertSeeIn("[dusk=\"origin-{$origin->id}\"]", 'Browser test reason')
        ->click("[dusk=\"origin-{$origin->id}\"] [dusk=\"confirm-origin\"]");

    $this->waitUntil($page, "!document.querySelector('[dusk=\"origin-{$origin->id}\"]')");

    expect($origin->fresh()->reviewed_at)->not->toBeNull();
})->group('critical');
