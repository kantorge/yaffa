<?php

namespace App\Services;

use App\Models\AccountEntity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class AccountEntityService
{
    public function delete(AccountEntity $accountEntity): array
    {
        $success = false;
        $error = null;

        try {
            $accountEntity->delete();
            $accountEntity->config->delete();
            $success = true;
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                $error = __(
                    ':type is in use, cannot be deleted',
                    ['type' => Str::ucfirst($accountEntity->config_type)]
                );
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
