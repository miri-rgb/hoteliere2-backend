<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        $sql   = file_get_contents(database_path('seeders/export_data.sql'));
        $lines = explode(";\n", $sql);

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line) && !str_starts_with($line, 'SET')) {
                DB::statement($line);
            }
        }

        $this->command->info('✅ Données réelles importées depuis export_data.sql');
    }
}
