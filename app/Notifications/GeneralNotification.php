<?php

namespace App\Notifications;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;
    public $url;

    /**
     * Create a new notification instance.
     *
     * @param string $message The message for the notification.
     */
    public function __construct($message, $url = null)
    {
        $this->message = $message;
        $this->url = $url;
    }

    /**
     * Define the channels the notification will be sent to.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast']; // You can add 'mail', 'broadcast', 'nexmo', etc.
    }

    /**
     * Define the representation of the notification for the database.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'message' => $this->message,
            'url' => $this->url,
            'sent_at' => now(),
        ];
    }

    /**
     * Define the representation of the notification for email (optional).
     *
     * @param mixed $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Notification')
            ->line($this->message);
    }


    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'message' => $this->message,
            'url' => $this->url,
            'sent_at' => now(),
        ]);
    }
}
