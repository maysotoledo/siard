<?php

namespace Database\Factories;

use App\Models\AiReport;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiReportFactory extends Factory
{
    protected $model = AiReport::class;

    public function definition(): array
    {
        return [
            'tipo'    => $this->faker->randomElement(['relatorio_completo', 'resumo_tecnico', 'linha_investigacao', 'conclusao', 'minuta_autoridade']),
            'status'  => 'pending',
            'prompt'  => '',
        ];
    }
}
