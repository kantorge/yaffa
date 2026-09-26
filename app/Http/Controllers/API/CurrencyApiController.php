<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Services\CurrencyService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:write', only: [
    'destroy',
])]
class CurrencyApiController extends Controller
{
    public function __construct(protected CurrencyService $currencyService)
    {
    }

    /**
     * Delete a currency
     *
     * @throws AuthorizationException
     */
    #[Authorize('delete', 'currency')]
    public function destroy(Currency $currency): JsonResponse
    {
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
