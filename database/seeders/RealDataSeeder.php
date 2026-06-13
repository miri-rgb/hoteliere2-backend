<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        $sql = file_get_contents(database_path('seeders/export_data.sql'));
        DB::unprepared($sql);
        $this->command->info('✅ Données réelles importées depuis export_data.sql');
    }
}
