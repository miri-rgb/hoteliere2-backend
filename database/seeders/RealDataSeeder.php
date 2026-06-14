<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RealDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insertOrIgnore([
            'id'         => 1,
            'nom'        => 'Admin',
            'prenom'     => 'Hotel',
            'email'      => 'admin@hoteliere.ma',
            'password'   => Hash::make('password123'),
            'role'       => 'admin',
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sql = file_get_contents(database_path('seeders/export_data.sql'));
        if (str_starts_with($sql, "\xEF\xBB\xBF")) {
            $sql = substr($sql, 3);
        }
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
