<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private TeamInvitation $invitation,
        private ?string $rawToken = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $campaign = $this->invitation->campaign;
        $inviter = $this->invitation->inviter;
        $token = $this->rawToken ?? $this->invitation->token;
        $acceptUrl = config('app.frontend_url', config('app.url'))
            . '/invite/accept?token=' . $token;

        return (new MailMessage)
            ->subject("You're invited to join {$campaign->name} on KuraDigital")
            ->greeting("Hello!")
            ->line("{$inviter->name} has invited you to join the **{$campaign->name}** campaign as a **{$this->invitation->role}**.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("This invitation expires on {$this->invitation->expires_at->format('F j, Y')}.")
            ->line('If you did not expect this invitation, you can safely ignore this email.');
    }
}
