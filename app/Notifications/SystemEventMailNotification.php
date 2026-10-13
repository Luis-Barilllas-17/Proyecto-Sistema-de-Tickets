<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemEventMailNotification extends Notification
{
    use Queueable;

    public function __construct(private array $payload) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[ZOLIC Tickets] '.$this->payload['title'])
            ->greeting('Hola, '.$notifiable->name)
            ->line($this->payload['message'])
            ->when(! empty($this->payload['ticket_number']), fn (MailMessage $mail) => $mail->line('Ticket: '.$this->payload['ticket_number']))
            ->action('Consultar en el sistema', $this->payload['url'])
            ->line('Este es un aviso automático del Sistema de Gestión de Tickets ZOLIC.');
    }
}
