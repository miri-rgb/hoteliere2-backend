<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HotelSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin (requis car hotels.user_id FK NOT NULL) ─────────
        $admin = DB::table('users')->where('email', 'admin@hoteliere.ma')->first();

        $adminId = $admin?->id ?? DB::table('users')->insertGetId([
            'nom'        => 'Admin',
            'prenom'     => 'Hoteliere',
            'email'      => 'admin@hoteliere.ma',
            'password'   => Hash::make('admin123'),
            'role'       => 'admin',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── 5 Hôtels ─────────────────────────────────────────────
        $hotels = [
            [
                'nom'                   => 'Royal Mansour',
                'adresse'               => 'Rue Abou Abbas El Sebti',
                'ville'                 => 'Marrakech',
                'code_postal'           => '40000',
                'categorie'             => '5 étoiles',
                'description_detaillee' => 'Palais royal au cœur de la médina de Marrakech. Une expérience unique alliant luxe et art de vivre marocain dans un cadre somptueux.',
            ],
            [
                'nom'                   => 'Kenzi Tower',
                'adresse'               => 'Boulevard Mohammed Zerktouni',
                'ville'                 => 'Casablanca',
                'code_postal'           => '20040',
                'categorie'             => '5 étoiles',
                'description_detaillee' => 'Hôtel d\'affaires emblématique de Casablanca, situé au sommet d\'une tour de 28 étages avec vue panoramique sur l\'Atlantique.',
            ],
            [
                'nom'                   => 'Sofitel Rabat Jardin des Roses',
                'adresse'               => 'Rue de Zaire, Souissi',
                'ville'                 => 'Rabat',
                'code_postal'           => '10000',
                'categorie'             => '5 étoiles',
                'description_detaillee' => 'Niché dans un immense jardin de roses, ce palace offre une escapade luxueuse en plein cœur de la capitale du Maroc.',
            ],
            [
                'nom'                   => 'Barceló Fès Medina',
                'adresse'               => 'Avenue des FAR',
                'ville'                 => 'Fès',
                'code_postal'           => '30000',
                'categorie'             => '4 étoiles',
                'description_detaillee' => 'Hôtel moderne aux portes de la médina de Fès, offrant un confort supérieur avec une décoration inspirée de l\'artisanat fassi.',
            ],
            [
                'nom'                   => 'Ibis Agadir',
                'adresse'               => 'Boulevard Mohammed V',
                'ville'                 => 'Agadir',
                'code_postal'           => '80000',
                'categorie'             => '3 étoiles',
                'description_detaillee' => 'Hôtel pratique et confortable à deux pas de la plage d\'Agadir. Idéal pour les séjours balnéaires et voyages d\'affaires.',
            ],
        ];

        // ── Types de chambre par hôtel ────────────────────────────
        $typesParHotel = [
            [
                ['nom_type' => 'Suite Royale',    'prix_base' => 3500.00],
                ['nom_type' => 'Chambre Deluxe',  'prix_base' => 1800.00],
                ['nom_type' => 'Chambre Standard','prix_base' => 1200.00],
            ],
            [
                ['nom_type' => 'Suite Executive',    'prix_base' => 2800.00],
                ['nom_type' => 'Chambre Supérieure', 'prix_base' => 1500.00],
                ['nom_type' => 'Chambre Standard',   'prix_base' =>  900.00],
            ],
            [
                ['nom_type' => 'Suite Prestige',  'prix_base' => 3000.00],
                ['nom_type' => 'Chambre Luxury',  'prix_base' => 1600.00],
                ['nom_type' => 'Chambre Classique','prix_base'=> 1000.00],
            ],
            [
                ['nom_type' => 'Suite Junior',       'prix_base' => 1200.00],
                ['nom_type' => 'Chambre Supérieure', 'prix_base' =>  800.00],
                ['nom_type' => 'Chambre Standard',   'prix_base' =>  550.00],
            ],
            [
                ['nom_type' => 'Chambre Double', 'prix_base' => 450.00],
                ['nom_type' => 'Chambre Simple', 'prix_base' => 300.00],
            ],
        ];

        // ── Services par hôtel ────────────────────────────────────
        $servicesParHotel = [
            ['Piscine', 'Spa & Hammam', 'Restaurant gastronomique', 'Conciergerie 24h/24', 'Parking privé', 'Wi-Fi gratuit'],
            ['Rooftop Bar', 'Centre de fitness', 'Restaurant panoramique', 'Salle de conférences', 'Parking', 'Wi-Fi gratuit'],
            ['Jardin', 'Piscine olympique', 'Spa', 'Restaurant français', 'Tennis', 'Wi-Fi gratuit'],
            ['Piscine', 'Restaurant marocain', 'Salle de réunion', 'Hammam', 'Wi-Fi gratuit'],
            ['Piscine', 'Restaurant', 'Bar', 'Wi-Fi gratuit'],
        ];

        // ── Images Unsplash par hôtel ─────────────────────────────
        $images = [
            'https://images.unsplash.com/photo-1539037116277-4db20889f2d4?w=800&q=80',
            'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            'https://images.unsplash.com/photo-1571896349842-33c89424de2d?w=800&q=80',
            'https://images.unsplash.com/photo-1578683010236-d716f9a3f461?w=800&q=80',
            'https://images.unsplash.com/photo-1520250497591-112f2f40a3f4?w=800&q=80',
        ];

        // ── Insertion ─────────────────────────────────────────────
        foreach ($hotels as $index => $hotelData) {
            $hotelId = DB::table('hotels')->insertGetId([
                ...$hotelData,
                'user_id'    => $adminId,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($typesParHotel[$index] as $type) {
                $typeId = DB::table('types_chambre')->insertGetId([
                    'hotel_id'   => $hotelId,
                    'nom_type'   => $type['nom_type'],
                    'prix_base'  => $type['prix_base'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 3 chambres par type de chambre
                for ($i = 1; $i <= 3; $i++) {
                    DB::table('chambres')->insert([
                        'hotel_id'        => $hotelId,
                        'type_chambre_id' => $typeId,
                        'description'     => "Chambre {$type['nom_type']} — numéro {$i}",
                        'image_url'       => $images[$index],
                        'is_available'    => true,
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
            }

            foreach ($servicesParHotel[$index] as $service) {
                DB::table('services_hotel')->insert([
                    'hotel_id'    => $hotelId,
                    'nom_service' => $service,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $this->command->info('✅ 5 hôtels insérés avec types de chambre, chambres et services.');
    }
}
