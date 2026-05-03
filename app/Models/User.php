<?php

namespace App\Models;

use App\Enums\FuncaoOperacional;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\Auditable;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Auditable;

    protected $fillable = [
        'name',
        'email',
        'data_ingresso',
        'password',
        'attendance_hours',
        'attendance_slot_duration_minutes',
        'google_calendar_token',
        'google_calendar_refresh_token',
        'google_calendar_token_expires_at',
        'google_calendar_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_calendar_token',
        'google_calendar_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'data_ingresso' => 'date',
            'password' => 'hashed',
            'attendance_hours' => 'array',
            'attendance_slot_duration_minutes' => 'integer',
            'google_calendar_token' => 'encrypted',
            'google_calendar_refresh_token' => 'encrypted',
            'google_calendar_token_expires_at' => 'datetime',
        ];
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(Evento::class);
    }

    public function analiseRuns(): HasMany
    {
        return $this->hasMany(AnaliseRun::class);
    }

    public function getFuncaoOperacionalAttribute(): ?FuncaoOperacional
    {
        foreach (['ipc_plantao', 'epc_plantao', 'cartorio_central', 'dpc', 'ipc', 'epc'] as $role) {
            if ($this->hasRole($role)) {
                return FuncaoOperacional::fromRole($role);
            }
        }

        return null;
    }
}
