<?php

use App\Models\InvestmentGroup;
use Diglactic\Breadcrumbs\Breadcrumbs;
use Diglactic\Breadcrumbs\Generator as BreadcrumbTrail;

// Home
Breadcrumbs::for('home', function (BreadcrumbTrail $trail) {
    $trail->push(__('Home'), route('home'));
});

// Account group resource views (index, create, edit)
Breadcrumbs::for('account-group.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Account Groups'), route('account-group.index'));
});
Breadcrumbs::for('account-group.create', function (BreadcrumbTrail $trail) {
    $trail->parent('account-group.index');
    $trail->push(__('Create'), route('account-group.create'));
});
Breadcrumbs::for('account-group.edit', function (BreadcrumbTrail $trail, $accountGroup) {
    $trail->parent('account-group.index');
    $trail->push(__('Edit'), route('account-group.edit', $accountGroup));
});

// Account entity resource views (index, create, edit, show)
Breadcrumbs::for('account-entity.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    // Based on the type in the request, the title of the breadcrumb will be different
    $type = request()->get('type');
    if ($type === 'account') {
        $trail->push(__('Accounts'), route('account-entity.index', ['type' => 'account']));
    } elseif ($type === 'payee') {
        $trail->push(__('Payees'), route('account-entity.index', ['type' => 'payee']));
    }
});
Breadcrumbs::for('account-entity.create', function (BreadcrumbTrail $trail) {
    // The add form has the same $type parameter as the index view, so we can reuse the breadcrumbs
    $trail->parent('account-entity.index');
    $trail->push(__('Create'), route('account-entity.create'));
});
Breadcrumbs::for('account-entity.edit', function (BreadcrumbTrail $trail, $accountEntity) {
    // The edit form has the same $type parameter as the index view, so we can reuse the breadcrumbs
    $trail->parent('account-entity.index');
    $trail->push(__('Edit'), route('account-entity.edit', $accountEntity));
});
Breadcrumbs::for('account-entity.show', function (BreadcrumbTrail $trail, $accountEntity) {
    // The show view does not have the $type parameter, so we need to create the breadcrumbs manually
    $trail->parent('home');
    if ($accountEntity->config_type === 'account') {
        $trail->push(__('Accounts'), route('account-entity.index', ['type' => 'account']));
    } elseif ($accountEntity->config_type === 'payee') {
        $trail->push(__('Payees'), route('account-entity.index', ['type' => 'payee']));
    }
    $trail->push(__($accountEntity->name), route('account-entity.show', $accountEntity));
});

// Account > History
Breadcrumbs::for('account.history', function (BreadcrumbTrail $trail, $accountEntity) {
    $trail->parent('account-entity.show', $accountEntity);
    $trail->push(__('History'), route('account.history', $accountEntity));
});

// Payee > merge form
Breadcrumbs::for('payees.merge.form', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Payees'), route('payee.index'));
    $trail->push(__('Merge'), route('payee.merge.form'));
});

// Category resource (index, create, edit)
Breadcrumbs::for('categories.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Categories'), route('categories.index'));
});
Breadcrumbs::for('categories.create', function (BreadcrumbTrail $trail) {
    $trail->parent('categories.index');
    $trail->push(__('Create'), route('categories.create'));
});
Breadcrumbs::for('categories.edit', function (BreadcrumbTrail $trail, $category) {
    $trail->parent('categories.index');
    $trail->push(__('Edit'), route('categories.edit', $category));
});

// Home > Categories > Merge
Breadcrumbs::for('categories.merge.form', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->parent('categories.index');
    $trail->push(__('Merge'), route('categories.merge.form'));
});

// Currency resource views (index, create, edit)
Breadcrumbs::for('currency.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Currencies'), route('currency.index'));
});
Breadcrumbs::for('currency.create', function (BreadcrumbTrail $trail) {
    $trail->parent('currency.index');
    $trail->push(__('Create'), route('currency.create'));
});
Breadcrumbs::for('currency.edit', function (BreadcrumbTrail $trail, $currency) {
    $trail->parent('currency.index');
    $trail->push(__('Edit'), route('currency.edit', $currency));
});

// Currency rate, only index view
Breadcrumbs::for('currency-rate.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Currency Rates'));
});

// Investment group resource views (index, create, edit)
Breadcrumbs::for('investment-group.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Investment Groups'), route('investment-group.index'));
});
Breadcrumbs::for('investment-group.create', function (BreadcrumbTrail $trail) {
    $trail->parent('investment-group.index');
    $trail->push(__('Create'), route('investment-group.create'));
});
Breadcrumbs::for('investment-group.edit', function (BreadcrumbTrail $trail, InvestmentGroup $investmentGroup) {
    $trail->parent('investment-group.index');
    $trail->push(__('Edit'), route('investment-group.edit', $investmentGroup));
});

// Investment resource views (index, create, edit, show)
Breadcrumbs::for('investment.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Investments'), route('investment.index'));
});
Breadcrumbs::for('investment.create', function (BreadcrumbTrail $trail) {
    $trail->parent('investment.index');
    $trail->push(__('Create'), route('investment.create'));
});
Breadcrumbs::for('investment.edit', function (BreadcrumbTrail $trail, $investment) {
    $trail->parent('investment.index');
    $trail->push(__('Edit'), route('investment.edit', $investment));
});
Breadcrumbs::for('investment.show', function (BreadcrumbTrail $trail, $investment) {
    $trail->parent('investment.index');
    $trail->push(__($investment->name), route('investment.show', $investment));
});

// Investment price resource views (create, edit)
Breadcrumbs::for('investment-price.create', function (BreadcrumbTrail $trail, $investment) {
    $trail->parent('investment.show', $investment);
    $trail->push(__('Create Price'), route('investment-price.create', $investment));
});
Breadcrumbs::for('investment-price.edit', function (BreadcrumbTrail $trail, $investmentPrice) {
    $trail->parent('investment.show', $investmentPrice->investment);
    $trail->push(__('Edit Price'), route('investment-price.edit', $investmentPrice));
});
Breadcrumbs::for('investment-price.list', function (BreadcrumbTrail $trail, $investment) {
    $trail->parent('investment.show', $investment);
    $trail->push(__('Prices'), route('investment-price.list', $investment));
});

// Tag resource views (index, create, edit)
Breadcrumbs::for('tag.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Tags'), route('tag.index'));
});
Breadcrumbs::for('tag.create', function (BreadcrumbTrail $trail) {
    $trail->parent('tag.index');
    $trail->push(__('Create'), route('tag.create'));
});
Breadcrumbs::for('tag.edit', function (BreadcrumbTrail $trail, $tag) {
    $trail->parent('tag.index');
    $trail->push(__('Edit'), route('tag.edit', $tag));
});

// Transaction related routes
Breadcrumbs::for('transaction.create', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Transactions'));
    $trail->push(__('Create'));
});
Breadcrumbs::for('transactions.createFromDraft', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Transactions'));
    $trail->push(__('Create from draft data'), route('transactions.createFromDraft'));
});
Breadcrumbs::for('transaction.open', function (BreadcrumbTrail $trail, $transaction) {
    $trail->parent('home');
    $trail->push(__('Transactions'));
    $action = request()->route('action');
    $trail->push(__(ucfirst($action)), route('transaction.open', ['transaction' => $transaction, 'action' => $action]));
});

/* Reports */

Breadcrumbs::for('reports.cashflow', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Reports');
    $trail->push(__('Cash Flow'), route('reports.cashflow'));
});
Breadcrumbs::for('reports.budgetchart', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Reports');
    $trail->push(__('Budget Chart'), route('reports.budgetchart'));
});
Breadcrumbs::for('report.schedules', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Reports');
    $trail->push(__('Schedules and Budgets'), route('report.schedules'));
});
Breadcrumbs::for('reports.transactions', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push('Reports');
    $trail->push(__('Transactions'), route('reports.transactions'));
});
Breadcrumbs::for('reports.investment_timeline', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Investments'), route('investment.index'));
    $trail->push(__('Investment timeline'), route('reports.investment_timeline'));
});

// Miscellaneous routes - received mails resource views
Breadcrumbs::for('received-mail.index', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Automations'));
    $trail->push(__('Received emails'), route('received-mail.index'));
});
Breadcrumbs::for('received-mail.show', function (BreadcrumbTrail $trail, $receivedMail) {
    $trail->parent('received-mail.index');
    $trail->push(__('Show'), route('received-mail.show', $receivedMail));
});

// Search
Breadcrumbs::for('search', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Search'));
});

// Import CSV
Breadcrumbs::for('import.csv', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('Automations'));
    $trail->push(__('Import transactions'), route('import.csv'));
});

// User related routes
Breadcrumbs::for('user.settings', function (BreadcrumbTrail $trail) {
    $trail->parent('home');
    $trail->push(__('My profile'), route('user.settings'));
});
