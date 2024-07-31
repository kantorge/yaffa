<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Models\GocardlessAccount;
use App\Models\User;
use App\Services\GocardlessService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use JavaScript;

class GocardlessController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $requisitions = $user->gocardlessRequisitions()
            ->withCount(['allAccounts', 'linkedAccounts'])
            ->get();

        JavaScript::put([
            'requisitions' => $requisitions,
            'requistionStatuses' => RequisitionStatus::getAllStatuses(),
        ]);

        return view('gocardless.index');
    }

    public function create(): View
    {
        return view('gocardless.create');
    }

    public function callback(Request $request): RedirectResponse
    {
        // Get the key variables from the return URL
        $reference = $request->get('ref');
        $error = $request->get('error');
        $details = $request->get('details');

        // Check if any errors were returned. If yes, display the error message and redirect to the create page
        if ($error) {
            $this->addMessage(
                __('The authorization failed.'),
                'danger',
                $details,
                null,
                true
            );

            return redirect()->route('gocardless.index');
        }

        // If no errors, create the requisition, display a success message and redirect to the index page
        // We also need to update the requisition with the details from GoCardless
        $goCardlessService = new GocardlessService();
        $requisitionData = $goCardlessService->getRequisitionDetails($reference);

        $requisition = auth()->user()
            ->gocardlessRequisitions()
            ->where('id', $reference)
            ->where('user_id', auth()->id())
            ->first();

        // Update the requisition with the details from GoCardless
        $requisition->update([
            'status' => $requisitionData['status'],
        ]);

        // Create the GoCardless Accounts, too
        foreach($requisitionData['accounts'] as $account) {
            $data = $goCardlessService->getAccountDetails($account);
            logger('GoCardless Account Data', $data);

            $requisition->allAccounts()->create([
                'id' => $account,
                'name' => $data['name'] ?? $data['iban'],
                'iban' => $data['iban'],
                'currency_code' => $data['currency'],
            ]);
        }

        $this->addMessage(
            __('Requisition created successfully.'),
            'success',
            null,
            null,
            true
        );

        return redirect()->route('gocardless.index');
    }

    public function linkAccounts(): View
    {
        // Get all GoCardless accounts and the linked requisition data from the database
        $goCardlessAccounts = GocardlessAccount::with([
                'requisition' => function ($query) {
                    $query->where('user_id', auth()->id());
                },
            ])
            ->get();

        // Get all YAFFA accounts for the user
        $accounts = auth()->user()->accounts;
        $accounts->load([
            'config',
            'config.accountGroup',
            'config.currency',
            'config.gocardlessAccount'
        ]);

        JavaScript::put([
            'goCardlessAccounts' => $goCardlessAccounts,
            'accounts' => $accounts,
        ]);

        return view('gocardless.link-accounts');
    }

    public function transactions(GocardlessAccount $gocardlessAccount): View
    {
        $goCardlessService = new GocardlessService();
        $transactions = $goCardlessService->getTransactions(
            $gocardlessAccount,
            Carbon::now()->subDays(30),
            Carbon::today()
        );

        dd($transactions);

        JavaScript::put([
            'transactions' => $transactions,
        ]);

        return view('gocardless.transactions');
    }
}
