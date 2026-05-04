<?php

namespace App\Services\Plantao;

use App\Models\PlantaoEscala;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class DutyRestValidationService
{
    public function validateRest(User $user, Carbon $newStartsAt, Carbon $newEndsAt, ?int $ignoreEscalaId = null): void
    {
        $escalas = PlantaoEscala::query()
            ->with(['equipe.servidores.user', 'permutas'])
            ->when($ignoreEscalaId, fn ($query) => $query->whereKeyNot($ignoreEscalaId))
            ->whereBetween('data_plantao', [
                $newStartsAt->copy()->subDays(5)->toDateString(),
                $newStartsAt->copy()->addDays(5)->toDateString(),
            ])
            ->get()
            ->filter(fn (PlantaoEscala $escala): bool => app(PlantaoCalendarService::class)->servidorEstaNoPlantao($escala, $user->id));

        foreach ($escalas as $escala) {
            $startsAt = Carbon::parse($escala->data_plantao->toDateString().' '.$escala->horario_inicio);
            $endsAt = $startsAt->copy()->addHours(24);

            $gap = $newStartsAt->gte($endsAt)
                ? $endsAt->diffInHours($newStartsAt)
                : $newEndsAt->diffInHours($startsAt);

            if ($gap < 72) {
                throw ValidationException::withMessages([
                    'data_plantao' => 'Servidor não respeita o descanso mínimo de 72h entre plantões.',
                ]);
            }
        }
    }
}
