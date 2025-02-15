<?php

namespace App\Services;

use App\Models\InvestmentGroup;
use Exception;

class InvestmentGroupService
{
    public function delete(InvestmentGroup $investmentGroup): array
    {
        if ($investmentGroup->investments()->count() > 0) {
            return [
                'success' => false,
                'error' => __('Investment group is in use, cannot be deleted'),
            ];
        }

        $success = false;
        $error = null;

        try {
            $investmentGroup->delete();
            $success = true;
        } catch (Exception $e) {
            $error = __('Database error:') . ' ' . $e->getMessage();
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }
}
