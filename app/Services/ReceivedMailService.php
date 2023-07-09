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

    public function resetProcessed(ReceivedMail $receivedMail): array
    {
        $receivedMail->processed = false;
        $receivedMail->transaction_data = null;
        $receivedMail->handled = false;
        $receivedMail->transaction_id = null;

        $receivedMail->save();

        return [
            'success' => true,
            'error' => null,
        ];
    }
}
