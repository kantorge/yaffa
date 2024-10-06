<?php

namespace App\Services;

use App\Models\AccountGroup;
use Exception;

class AccountGroupService
{
    public function delete(AccountGroup $accountGroup): array
    {
        if ($accountGroup->accountEntities()->count() > 0) {
            return [
                'success' => false,
                'error' => __('Account group is in use, cannot be deleted'),
            ];
        }

        $success = false;
        $error = null;

        try {
            $accountGroup->delete();
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
