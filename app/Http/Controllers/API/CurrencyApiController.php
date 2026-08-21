<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Gate;

class CurrencyApiController extends Controller implements HasMiddleware
{
    protected CurrencyService $currencyService;

    public function __construct()
    {
        $this->currencyService = new CurrencyService();
    }

    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            'verified',
            new Middleware('abilities:write', only: [
                'destroy',
            ]),
        ];
    }

    /**
     * Delete a currency
     *
     * @throws AuthorizationException
     */
    public function destroy(Currency $currency): JsonResponse
    {
        Gate::authorize('delete', $currency);
        $result = $this->currencyService->delete($currency);

        if ($result['success']) {
            return response()
                ->json(
                    ['currency' => $currency],
                    Response::HTTP_OK
                );
        }

        return response()
            ->json(
                [
                    'currency' => $currency,
                    'error' => $result['error'],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
    }
}
