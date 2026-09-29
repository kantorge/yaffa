<?php

use App\Models\User;

it('resets the table search with the clear button', function () {
    $this->actingAs(User::where('email', 'demo@yaffa.cc')->firstOrFail());

    $page = visit(route('account-entity.index', ['type' => 'payee']));
    $this->waitUntil($page, 'window.table !== undefined');
    $total = $page->script('() => window.table.rows({ search: "applied" }).count()');

    $page->type('#table_filter_search_text', 'no such payee');
    $this->waitUntil($page, 'window.table.rows({ search: "applied" }).count() === 0');

    $page->click('#table_filter_search_text_clear');
    $this->waitUntil($page, "window.table.rows({ search: 'applied' }).count() === {$total}");
    $page->assertValue('#table_filter_search_text', '');
});
