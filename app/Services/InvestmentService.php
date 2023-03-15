<?php

namespace App\Services;

use App\Models\Investment;
use Illuminate\Database\QueryException;

class InvestmentService
{
    public function deleteInvestment(Investment $investment): array
    {
        $success = false;
        $error = null;

        try {
            $investment->delete();
            $success = true;
        } catch (QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                $error = __('Investment is in use, cannot be deleted');
            } else {
                $error = __('Database error:') . ' ' . $e->errorInfo[2];
            }
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }
}
