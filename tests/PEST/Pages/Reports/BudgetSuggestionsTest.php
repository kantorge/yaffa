<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::where('email', 'demo@yaffa.cc')->firstOrFail());
});

it('runs "Review all categories" and shows a result without errors', function () {
    $page = visit(route('reports.budgetchart'));
    $this->waitUntil($page, "document.querySelector('#budgetSuggestionsApp button.btn-outline-primary')");

    $page->click('#budgetSuggestionsApp button.btn-outline-primary');
    // A result is either a list of recommendations, the empty notice, or the collapsed verdicts
    $this->waitUntil(
        $page,
        "document.querySelector('#budgetSuggestionsApp [data-testid=suggestion], #budgetSuggestionsApp [data-testid=no-suggestions], #budgetSuggestionsApp .alert-danger')",
    );

    $page->assertScript("document.querySelector('#budgetSuggestionsApp .alert-danger') === null", true)
        ->assertNoJavaScriptErrors();
});
