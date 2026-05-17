<?php

namespace Database\Factories;

use App\Models\InvestigationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvestigationContextFactory extends Factory
{
    protected $model = InvestigationContext::class;

    public function definition(): array
    {
        return [
            'titulo'          => ucfirst(implode(' ', $this->faker->words(4))),
            'numero_bo'       => $this->faker->numerify('####/####'),
            'natureza'        => $this->faker->randomElement(['Furto', 'Roubo', 'Estelionato', 'Ameaça']),
            'unidade_policial' => $this->faker->randomElement(['1ª DP', '2ª DP', 'DEIC']),
            'vitimas'         => [$this->faker->name()],
            'suspeitos'       => [],
            'texto_extraido'  => null,
        ];
    }
}
