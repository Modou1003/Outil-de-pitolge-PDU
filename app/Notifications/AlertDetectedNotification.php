<?php

namespace App\Notifications;

use App\Models\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertDetectedNotification extends Notification
{
    use Queueable;

    public function __construct(public Alert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectCode = $this->alert->project?->code ?? 'N/A';
        $projectTitle = $this->alert->project?->title ?? 'Projet';

        return (new MailMessage)
            ->subject('[PDU Tracker] Nouvelle alerte: ' . $this->alert->title)
            ->greeting('Bonjour ' . ($notifiable->name ?? ''))
            ->line('Une nouvelle alerte a ete detectee sur la plateforme.')
            ->line('Projet: ' . $projectCode . ' - ' . $projectTitle)
            ->line('Type: ' . ($this->alert->type_label ?? $this->alert->type))
            ->line('Niveau: ' . strtoupper((string) $this->alert->severity))
            ->line('Message: ' . $this->alert->message)
            ->action('Voir les alertes', route('alertes.index'))
            ->line('Cet e-mail est envoye automatiquement.');
    }
}
