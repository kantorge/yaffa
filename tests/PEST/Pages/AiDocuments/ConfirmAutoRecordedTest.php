<?php

use App\Models\AiDocument;
use App\Models\Transaction;
use App\Models\TransactionOrigin;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('flags an unreviewed auto-recorded transaction and confirms it from the review filter', function () {
    $transaction = Transaction::where('user_id', $this->user->id)->where('config_type', 'standard')->firstOrFail();
    $document = AiDocument::factory()->for($this->user)->autoRecorded()->create();
    $origin = TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CREATED, $document, 'Browser test reason');

    $page = visit(route('ai-documents.index', ['review' => 'auto-recorded']));
    $this->waitUntil($page, "document.querySelector('#ai-document-table')");

    $page->assertSee('Awaiting confirmation')
        ->click('#ai-document-table button.confirm-origin');

    $this->waitUntil($page, "!document.querySelector('#ai-document-table button.confirm-origin')");

    expect($origin->fresh()->reviewed_at)->not->toBeNull();
})->group('critical');

it('confirms every shown auto-recorded transaction with the Confirm all shown action', function () {
    $transaction = Transaction::where('user_id', $this->user->id)->where('config_type', 'standard')->firstOrFail();
    $document = AiDocument::factory()->for($this->user)->autoRecorded()->create();
    $origin = TransactionOrigin::record($this->user, $transaction, TransactionOrigin::RELATION_CREATED, $document, 'Browser test reason');

    $page = visit(route('ai-documents.index', ['review' => 'auto-recorded']));
    $this->waitUntil($page, "document.querySelector('[dusk=\"confirm-all\"]')");

    $page->click('[dusk="confirm-all"]');

    $this->waitUntil($page, "!document.querySelector('#ai-document-table button.confirm-origin')");

    expect($origin->fresh()->reviewed_at)->not->toBeNull();
})->group('critical');
