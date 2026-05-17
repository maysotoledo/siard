<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\Auditable;

class AnaliseRunIp extends Model
{
        use Auditable;
    protected $fillable = [
        'analise_run_id',
        'ip',
        'last_seen_at',
        'occurrences',
        'enriched',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'enriched' => 'boolean',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AnaliseRun::class, 'analise_run_id');
    }

    public function ipEnrichment(): BelongsTo
    {
        return $this->belongsTo(IpEnrichment::class, 'ip', 'ip');
    }

    protected function providerLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $provider = trim((string) ($this->ipEnrichment?->isp ?: $this->ipEnrichment?->org));
            return $provider !== '' ? $provider : 'Desconhecido';
        });
    }

    protected function cityLabel(): Attribute
    {
        return Attribute::get(function (): string {
            $city = trim((string) ($this->ipEnrichment?->city ?? ''));
            return $city !== '' ? $city : 'Desconhecida';
        });
    }

    protected function connectionType(): Attribute
    {
        return Attribute::get(fn (): string => ($this->ipEnrichment?->mobile ?? false) ? 'Móvel' : 'Residencial');
    }
}
