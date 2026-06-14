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
            if (empty($line)) continue;
            if (stripos($line, 'SET ') === 0) continue;
            DB::statement($line);
        }

        $this->command->info('✅ Données réelles importées depuis export_data.sql');
    }
}
