<?php

namespace App\Services;

use App\Models\ReceivedMail;
use Illuminate\Database\QueryException;

class ReceivedMailService
{
    public function delete(ReceivedMail $receivedMail): array
    {
        $success = false;
        $error = null;

        try {
            $receivedMail->delete();
            $success = true;
        } catch (QueryException $e) {
            $error = __('Database error:') . ' ' . $e->errorInfo[2];
        }

        return [
            'success' => $success,
            'error' => $error,
        ];
    }
}
