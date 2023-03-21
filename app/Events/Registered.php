<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Auth\Events\Registered as OriginalRegistered;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Registered extends OriginalRegistered
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $user;
    public array $context;

    /**
     * Create a new event instance.
     *
     * @param User $user
     * @param array $context
     */
    public function __construct(User $user, array $context)
    {
        $this->user = $user;
        $this->context = $context;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn(): Channel|array
    {
        return new PrivateChannel('channel-name');
    }
}
