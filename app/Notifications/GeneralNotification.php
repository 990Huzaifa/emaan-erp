<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class GeneralNotification extends Notification
{
    use Queueable;

    public $message;

    /**
     * Create a new notification instance.
     *
     * @param string $message The message for the notification.
     */
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Define the channels the notification will be sent to.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database']; // You can add 'mail', 'broadcast', 'nexmo', etc.
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
}
