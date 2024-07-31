<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\GocardlessAccount;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Services\GocardlessService;

class GoCardlessApiController extends Controller
{
    private GocardlessService $goCardlessService;

    public function __construct()
    {
        $this->goCardlessService = new GocardlessService();
    }
    public function showAccessToken()
    {
        $client = $this->goCardlessService->getAccessToken();

        return response()->json($client);
    }

    public function getListOfInstitutesForCountry(string $countryCode): JsonResponse
    {
        return response()->json($this->goCardlessService->getListOfInstitutesForCountry($countryCode));
    }

    public function getAuthenticationUrl(string $institutionId, Request $request): JsonResponse
    {
        $tokenData = $this->goCardlessService->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/requisitions/';
        $params = [
            'institution_id' => $institutionId,
            'redirect' => route('gocardless.callback')
        ];
        // Make a POST request to the GoCardless API to get the authentication URL
        // Set the Bearer token to the access token received from the previous request
        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post($url, $params);

        // Check if the request was successful. On failure, there is a status_code and a reference key in the response
        if ($response->json()['status_code'] ?? null) {
            // Return a HTTP response with the status code, and the content of reference
            return response()->json($response->json(), $response->status());
        }

        // Save the requisition
        // Create a new requisition in the database
        auth()->user()->gocardlessRequisitions()->create([
            'id' => $response->json()['id'],
            'institution_id' => $institutionId,
            'institution_name' => $request->input('institution_name'),
            'created_at' => $response->json()['created'],
            'updated_at' => $response->json()['created'],
            'status' => $response->json()['status'],
            'authorization_url' => $response->json()['link'],
        ]);

        return response()->json($response->json());
    }

    public function getAllRequisitions(): JsonResponse
    {
        return response()->json($this->goCardlessService->getAllRequisitions());
    }

    public function getInstitutionDetails(string $institutionId): JsonResponse
    {
        $tokenData = $this->goCardlessService->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/institutions/' . $institutionId;
        // Make a GET request to the GoCardless API to get details of a specific institution
        // Set the Bearer token to the access token received from the previous request
        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url);

        return response()->json($response->json());
    }

    public function createLink(AccountEntity $account, GocardlessAccount $gocardlessAccount): JsonResponse
    {
        // Validate if both assets are owned by the authenticated user
        if ($account->user_id !== auth()->id() || $gocardlessAccount->requisition->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate if the account does not have a GoCardless account associated
        // Note: while technically it would be possible to replace the link, it is not supported at the moment
        if ($account->config->gocardlessAccount) {
            return response()->json(['error' => __('Account is already linked to a GoCardless account')], 400);
        }

        // Validate if the GoCardless account is not already assigned to any account of the user
        $linkedAccounts = Account::where('gocardless_account_id', $gocardlessAccount->id)->count() > 0;
        if ($linkedAccounts) {
            return response()->json(['error' => __('GoCardless account is already linked to another account')], 400);
        }

        // Save the relation to the database
        $account->config->gocardlessAccount()->associate($gocardlessAccount);
        $account->config->save();
        $account->refresh();
        $account->load([
            'config',
            'config.accountGroup',
            'config.currency',
            'config.gocardlessAccount'
        ]);

        // Return the updated account along with the 200 response
        return response()->json([
            'account' => $account
        ]);
    }

    public function deleteLink(AccountEntity $account, GocardlessAccount $gocardlessAccount): JsonResponse
    {
        // Validate if both assets are owned by the authenticated user
        if ($account->user_id !== auth()->id() || $gocardlessAccount->requisition->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Validate if the given accounts are actually linked together
        if ($account->config->gocardless_account_id !== $gocardlessAccount->id) {
            return response()->json(['error' => __('Accounts are not linked')], 400);
        }

        // Remove the link
        $account->config->gocardlessAccount()->dissociate();
        $account->config->save();
        $account->refresh();
        $account->load([
            'config',
            'config.accountGroup',
            'config.currency',
            'config.gocardlessAccount'
        ]);

        // Return the updated account along with the 200 response
        return response()->json([
            'account' => $account
        ]);
    }

    /**
     * This function retrieves the transactions from a given account, but does not return them in the reponse
     *
     * @param GocardlessAccount $gocardlessAccount
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getTransactions(GocardlessAccount $gocardlessAccount, Request $request): JsonResponse
    {
        // Validate that the account belongs to the authenticated user
        if ($gocardlessAccount->requisition->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get the date from and date to parameters from the request
        $dateFromString = $request->input('date_from', null);
        $dateToString = $request->input('date_to', null);

        // Basic validation for the dates
        // 1. Both dates must be valid dates
        $dateFrom = Carbon::parse($dateFromString);
        if (!$dateFrom) {
            return response()->json(['error' => 'Invalid date_from parameter'], 400);
        }
        $dateTo = Carbon::parse($dateToString);
        if (!$dateTo) {
            return response()->json(['error' => 'Invalid date_to parameter'], 400);
        }

        // 2. None of the dates are later than today
        if ($dateFrom->greaterThan(Carbon::today())) {
            return response()->json(['error' => 'date_from cannot be later than today'], 400);
        }
        if ($dateTo->greaterThan(Carbon::today())) {
            return response()->json(['error' => 'date_to cannot be later than today'], 400);
        }

        // 3. None of the dates are earlier than 90 days before today
        if ($dateFrom->lessThan(Carbon::today()->subDays(90))) {
            return response()->json(['error' => 'date_from cannot be earlier than 90 days before today'], 400);
        }
        if ($dateTo->lessThan(Carbon::today()->subDays(90))) {
            return response()->json(['error' => 'date_to cannot be earlier than 90 days before today'], 400);
        }

        // 4. The date from should not be later than the date to
        if ($dateFrom->greaterThan($dateTo)) {
            return response()->json(['error' => 'date_from cannot be later than date_to'], 400);
        }

        // Initialize the service and get the transactions
        $gocardlessService = new GocardlessService();
        $allTransactions = $gocardlessService->getTransactions($gocardlessAccount, $dateFrom, $dateTo);

        $counter = [
            'created' => 0,
            'updated' => 0,
        ];

        // Loop the transactions and store them in the database (create or update)
        // At the moment, only booked transactions are supported
        // Pending transactions don't have an ID associated with them, so identifying each unique item is not straightforward
        foreach ($allTransactions['transactions']['booked'] as $transaction) {
            // Check if the transaction already exists in the database
            $existingTransaction = $gocardlessAccount->transactions()
                ->where('transaction_id', $transaction['transactionId'])
                ->where('user_id', auth()->id())
                ->where('gocardless_account_id', $gocardlessAccount->id)
                ->first();

            logger('GoCardless Transaction Data', $transaction);

            $extractedData = [
                'status' => 'booked',
                'booking_date' => $transaction['bookingDate'] ?? null,
                'value_date' => $transaction['valueDate'],
                'transaction_amount' => $transaction['transactionAmount']['amount'],
                'currency_code' => $transaction['transactionAmount']['currency'],
                'description' => $transaction['remittanceInformationUnstructured'] ?? null,
                'debtor_name' => $transaction['debtorName'] ?? null,
                'creditor_name' => $transaction['creditorName'] ?? null,
                'raw_data' => $transaction,
            ];

            // If the transaction does not exist, create it
            if (!$existingTransaction) {
                $gocardlessAccount->transactions()
                    ->create(array_merge([
                            'transaction_id' => $transaction['transactionId'],
                            'user_id' => auth()->id(),
                            'gocardless_account_id' => $gocardlessAccount->id
                        ], $extractedData)
                    );

                $counter['created']++;
            } else {
                // If the transaction exists, update it
                $existingTransaction->update($extractedData);

                $counter['updated']++;
            }
        }

        return response()->json([
            'transactions' => $counter
        ]);
    }
}
