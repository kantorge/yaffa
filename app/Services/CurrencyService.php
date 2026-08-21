<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Database\QueryException;
use Throwable;

class CurrencyService
{
    public function delete(Currency $currency): array
    {
        // Base currency cannot be deleted
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
                : __('Database error:') . ' ' . $e->getMessage();

            return [
                'success' => false,
                'error' => $error,
            ];
        }
    }
}
