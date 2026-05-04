<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PlantaoPermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'view_plantao',
            'create_plantao',
            'update_plantao',
            'delete_plantao',
            'gerar_escala_plantao',
            'publicar_escala_plantao',
            'permutar_plantao',
            'gerar_pdf_plantao',
            'importar_escala_delta',
            'view_escala_delta',
            'update_escala_delta',
            'delete_escala_delta',
            'view_cqh',
            'manage_cqh',
            'gerar_escala_cqh',
            'permutar_cqh',
        ] as $permission) {
            Permission::findOrCreate($permission);
        }
    }
}
