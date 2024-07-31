<?php

namespace App\Services;

use App\Models\GocardlessAccount;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class GocardlessService
{
    public function getAccessToken()
    {
        $url = 'https://bankaccountdata.gocardless.com/api/v2/token/new/';

        // Make a POST request to the GoCardless API to get an access token
        $response = Http::post($url, [
            'secret_id' => config('yaffa.gocardless_secret_id'),
            'secret_key' => config('yaffa.gocardless_access_key'),
        ]);

        return $response->json();
    }

    public function getListOfInstitutesForCountry(string $countryCode): array
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/institutions/?country=' . $countryCode;
        // Make a GET request to the GoCardless API to get a list of banks for a specific country
        // Set the Bearer token to the access token received from the previous request

        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url);

        return $response->json();
    }

    public function getInstitutionDetails(string $institutionId): array
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/institutions/' . $institutionId . '/';
        // Make a GET request to the GoCardless API to get details for a specific bank
        // Set the Bearer token to the access token received from the previous request

        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url);

        return $response->json();
    }

    public function getRequisitionDetails(string $reference): array
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/requisitions/' . $reference . '/';
        // Make a GET request to the GoCardless API to get details for a specific requisition
        // Set the Bearer token to the access token received from the previous request

        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url);

        return $response->json();
    }

    public function getAllRequisitions(): array {
        $tokenData = $this->getAccessToken();
        $url = 'https://bankaccountdata.gocardless.com/api/v2/requisitions/';
        $allRequisitions = [];
        do {
            $response = Http::withToken($tokenData['access'])
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
            $data = $response->json();
            $allRequisitions = array_merge($allRequisitions, $data['results']);
            $url = $data['next'] ?? null;
        } while ($url);

        return $allRequisitions;
    }

    public function deleteRequisition(string $requisitionId): void
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/requisitions/' . $requisitionId . '/';
        // Make a DELETE request to the GoCardless API to delete a specific requisition
        // Set the Bearer token to the access token received from the previous request

        Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->delete($url);
    }

    public function getAccountDetails(string $accountId): array
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/accounts/' . $accountId . '/details/';
        // Make a GET request to the GoCardless API to get details for a specific account
        // Set the Bearer token to the access token received from the previous request

        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url);

        return $response->json()['account'];
    }

    public function getTransactions(GocardlessAccount $gocardlessAccount, Carbon $dateFrom, Carbon $dateTo): array
    {
        $tokenData = $this->getAccessToken();

        $url = 'https://bankaccountdata.gocardless.com/api/v2/accounts/' . $gocardlessAccount->id . '/transactions/';
        $params = [
            'date_from' => $dateFrom->format('Y-m-d'),
            'date_to' => $dateTo->format('Y-m-d'),
        ];

        // Make a GET request to the GoCardless API to get transactions for a specific account
        // Set the Bearer token to the access token received from the previous request
        $response = Http::withToken($tokenData['access'])
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->get($url, $params);

        return $response->json();
    }
}
