<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PixelTrack extends Model
{
    use Auditable;

    /**
     * Somente criação e exclusão são auditadas.
     * Atualizações são omitidas pois o modelo se atualiza automaticamente
     * a cada acesso do alvo (IP, geolocalização, clicked_at, etc.).
     */
    public static function bootAuditable(): void
    {
        static::created(function (PixelTrack $model): void {
            self::write('created', $model, null, self::sanitizeAuditValues($model->getAttributes()));
        });

        static::deleted(function (PixelTrack $model): void {
            self::write('deleted', $model, self::sanitizeAuditValues($model->getOriginal()), null);
        });
    }

    protected $fillable = [
        'token',
        'label',
        'preview_tipo',
        'tracking_domain',
        'mensagem',
        'noticia_url',
        'capture_gps',
        'og_titulo',
        'og_descricao',
        'og_imagem',
        'og_imagem_upload',
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
        'isp',
        'user_agent',
        'total_acessos',
        'clicked_at',
    ];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'gps_latitude' => 'float',
        'gps_longitude' => 'float',
        'gps_accuracy' => 'float',
        'capture_gps' => 'boolean',
        'clicked_at'  => 'datetime',
        'total_acessos' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (PixelTrack $pixel): void {
            if ($pixel->og_imagem_upload) {
                Storage::disk('public')->delete($pixel->og_imagem_upload);
            }
        });
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(PixelTrackAccess::class);
    }

    public function statusLabel(): string
    {
        return $this->clicked_at ? 'Capturado' : 'Aguardando';
    }

    public function trackingDomain(): ?string
    {
        return match ($this->preview_tipo) {
            'pix_bradesco' => 'comprovante-pix.site',
            'noticia' => 'agenciadanoticia.online',
            default => filled($this->tracking_domain) ? (string) $this->tracking_domain : null,
        };
    }

    public function trackingUrl(): string
    {
        $path = route('pixel.track', $this->token, false);
        $domain = $this->trackingDomain();

        if (! $domain) {
            return route('pixel.track', $this->token);
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
        $baseUrl = $this->trackingUrl();
        $queryString = http_build_query(array_filter($query, static fn (mixed $value): bool => $value !== null));

        if ($queryString === '') {
            return $baseUrl;
        }

        return $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . $queryString;
    }
}
