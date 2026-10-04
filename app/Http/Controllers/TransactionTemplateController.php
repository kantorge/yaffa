<?php

namespace App\Http\Controllers;

use App\Models\TransactionTemplate;
use App\Services\TransactionDraftService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;

#[Middleware('auth')]
#[Middleware('verified')]
class TransactionTemplateController extends Controller
{
    public function index(): View
    {
        /**
         * @get("/transaction-templates")
         * @name("transaction-templates.index")
         * @middlewares("web", "auth", "verified")
         */
        return view('transaction-templates.index');
    }

    public function create(string $type): View
    {
        /**
         * @get("/transaction-templates/create/{type}")
         * @name("transaction-templates.create")
         * @middlewares("web", "auth", "verified")
         */
        return view('transactions.form', [
            'transaction' => null,
            'action' => 'template',
            'type' => $type,
            'template' => ['id' => null, 'name' => '', 'is_featured' => false, 'notices' => []],
        ]);
    }

    #[Authorize('update', 'template')]
    public function edit(Request $request, TransactionTemplate $template, TransactionDraftService $draftService): View
    {
        /**
         * @get("/transaction-templates/{template}/edit")
         * @name("transaction-templates.edit")
         * @middlewares("web", "auth", "verified")
         */
        ['draft' => $draft, 'notices' => $notices] = $draftService->normalize($template->draft, $request->user());

        $transaction = $draftService->toUnsavedTransaction($draft, $request->user());

        JavaScript::put(['transaction' => $transaction]);

        return view('transactions.form', [
            'transaction' => $transaction,
            'action' => 'template',
            'type' => $draft['config_type'],
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'is_featured' => $template->is_featured,
                'notices' => $notices,
            ],
        ]);
    }
}
