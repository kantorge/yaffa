<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Database\QueryException;
use Throwable;

class CurrencyService
{
    /**
     * @return array{success: bool, error: ?string}
     */
    public function delete(Currency $currency): array
    {
        if ($currency->base) {
            return [
                'success' => false,
                'error' => __('Base currency cannot be deleted'),
            ];
        }

        try {
            $currency->delete();

            return [
                'success' => true,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $error = $e instanceof QueryException && $e->errorInfo[1] === 1451
                ? __('Currency is in use, cannot be deleted')
                : __('A database error occurred.');

            return [
                'success' => false,
                'error' => $error,
            ];
        }
    }
}
