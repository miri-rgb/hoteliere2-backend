<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HotelImageSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            1  => 'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?w=800&q=80',
            2  => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            3  => 'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80',
            4  => 'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&q=80',
            5  => 'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
            6  => 'https://images.unsplash.com/photo-1582719508461-905c673771fd?w=800&q=80',
            7  => 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?w=800&q=80',
            8  => 'https://images.unsplash.com/photo-1551882547-ff40c63fe5fa?w=800&q=80',
            9  => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?w=800&q=80',
            10 => 'https://images.unsplash.com/photo-1455587734955-081b22074882?w=800&q=80',
            11 => 'https://images.unsplash.com/photo-1561501900-3701fa6a0864?w=800&q=80',
            12 => 'https://images.unsplash.com/photo-1549294413-26f195200c16?w=800&q=80',
            13 => 'https://images.unsplash.com/photo-1590073242678-70ee3fc28e8e?w=800&q=80',
            14 => 'https://images.unsplash.com/photo-1564501049412-61c2a3083791?w=800&q=80',
            15 => 'https://images.unsplash.com/photo-1571003123894-1f0594d2b5d9?w=800&q=80',
            16 => 'https://images.unsplash.com/photo-1606046604972-77cc76aee944?w=800&q=80',
            17 => 'https://images.unsplash.com/photo-1496417263034-38ec4f0b665a?w=800&q=80',
            18 => 'https://images.unsplash.com/photo-1540541338537-1d4d9741a9c9?w=800&q=80',
            19 => 'https://images.unsplash.com/photo-1522798514-97ceb8c4f1c8?w=800&q=80',
            20 => 'https://images.unsplash.com/photo-1587985064135-0366536eab42?w=800&q=80',
        ];

        foreach ($images as $id => $url) {
            DB::table('hotels')->where('id', $id)->update(['image' => $url]);
        }

        $this->command->info('✅ Photos Unsplash appliquées sur ' . count($images) . ' hôtels.');
    }
}
