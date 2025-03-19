<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $url;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct($message, $userId, $url = null)
    {
        $this->message = $message;
        $this->url = $url;
        $this->userId = $userId;
    }

    public function broadcastOn()
    {
        return new Channel('notification.{$this->userId}');
    }

    public function broadcastWith()
    {
        return ['message' => $this->message , 'url' => $this->url, 'userId' => $this->userId ];
    }

    public function broadcastAs()
    {
        return 'notification.sent';
    }
}
