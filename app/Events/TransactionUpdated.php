<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransactionUpdated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Transaction $transaction;
    public array $changedAttributes;

    /**
     * Create a new event instance.
     */
    public function __construct(Transaction $transaction, array $changedAttributes)
    {
        $this->transaction = $transaction;
        $this->changedAttributes = $changedAttributes;
    }
}
