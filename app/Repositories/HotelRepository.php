<?php

namespace App\Repositories;

use App\Models\Hotel;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================
 * CLASSE : HotelRepository (VERSION AVEC FILTRES)
 * ============================================================
 * Chemin : app/Repositories/HotelRepository.php
 * ============================================================
 */
class HotelRepository implements HotelRepositoryInterface
{
    public function __construct(
        protected Hotel $hotel
    ) {}

    /**
     * ══════════════════════════════════════════════════════
     * MÉTHODE PRINCIPALE : Recherche avec filtres
     * ══════════════════════════════════════════════════════
     *
     * Filtres disponibles :
     *   - ville          : filtrer par ville
     *   - etoiles        : filtrer par nombre d'étoiles
     *   - prix_min       : prix minimum par nuit
     *   - prix_max       : prix maximum par nuit
     *   - date_arrivee   : date d'arrivée souhaitée
     *   - date_depart    : date de départ souhaitée
     *   - service        : filtrer par service (piscine, spa...)
     *   - per_page       : nombre de résultats par page
     */
    public function rechercherAvecFiltres(array $filtres = [], int $perPage = 12)
    {
        $query = $this->hotel
            ->with([
                'typesChambre',
                'services',
                'avis',
            ])
            // Calcul de la note moyenne et nombre d'avis
            ->withAvg('avis', 'note')
            ->withCount('avis')
            ->where('is_active', true);

        // ── FILTRE 1 : Par ville ──────────────────────────────
        // Exemple : ?ville=Marrakech
        // SQL : WHERE ville = 'Marrakech'
        if (!empty($filtres['ville'])) {
            $query->where('ville', 'LIKE', '%' . $filtres['ville'] . '%');
        }

        // ── FILTRE 2 : Par étoiles ────────────────────────────
        // Exemple : ?etoiles=5
        // SQL : WHERE etoiles = 5
        if (!empty($filtres['etoiles'])) {
            $query->where('etoiles', $filtres['etoiles']);
        }

        // ── FILTRE 3 : Par prix min/max ───────────────────────
        // Exemple : ?prix_min=500&prix_max=2000
        // SQL : WHERE id IN (SELECT hotel_id FROM types_chambre
        //                    WHERE prix_base BETWEEN 500 AND 2000)
        if (!empty($filtres['prix_min']) || !empty($filtres['prix_max'])) {
            $query->whereHas('typesChambre', function ($q) use ($filtres) {

                if (!empty($filtres['prix_min'])) {
                    // Au moins un type de chambre avec prix >= prix_min
                    $q->where('prix_base', '>=', $filtres['prix_min']);
                }

                if (!empty($filtres['prix_max'])) {
                    // Au moins un type de chambre avec prix <= prix_max
                    $q->where('prix_base', '<=', $filtres['prix_max']);
                }
            });
        }

        // ── FILTRE 4 : Par service ────────────────────────────
        // Exemple : ?service=piscine
        // SQL : WHERE id IN (SELECT hotel_id FROM services_hotel
        //                    WHERE nom LIKE '%piscine%')
        if (!empty($filtres['service'])) {
            $query->whereHas('services', function ($q) use ($filtres) {
                $q->where('nom', 'LIKE', '%' . $filtres['service'] . '%');
            });
        }

        // ── FILTRE 5 : Par disponibilité (le plus complexe) ───
        // Exemple : ?date_arrivee=2026-06-01&date_depart=2026-06-05
        //
        // LOGIQUE :
        // On veut les hôtels qui ont AU MOINS UNE chambre libre
        // pour la période demandée.
        //
        // Une chambre est OCCUPÉE si :
        //   date_arrivee < notre_depart ET date_depart > notre_arrivee
        //
        // Une chambre est LIBRE si elle n'a PAS de réservation occupée.
        if (!empty($filtres['date_arrivee']) && !empty($filtres['date_depart'])) {

            $dateArrivee = $filtres['date_arrivee'];
            $dateDepart  = $filtres['date_depart'];

            // On garde seulement les hôtels qui ont
            // au moins une chambre disponible
            $query->whereHas('chambres', function ($q) use ($dateArrivee, $dateDepart) {

                $q->where('is_active', true)
                  // La chambre ne doit PAS avoir de réservation
                  // qui chevauche notre période
                  ->whereDoesntHave('reservations', function ($r) use ($dateArrivee, $dateDepart) {
                      $r->whereIn('statut', ['en_attente', 'confirmee'])
                        ->where('date_arrivee', '<', $dateDepart)   // condition chevauchement 1
                        ->where('date_depart',  '>', $dateArrivee); // condition chevauchement 2
                  });
            });

            // Ajouter le nombre de chambres disponibles dans la réponse
            $query->withCount(['chambres as chambres_disponibles' => function ($q) use ($dateArrivee, $dateDepart) {
                $q->where('is_active', true)
                  ->whereDoesntHave('reservations', function ($r) use ($dateArrivee, $dateDepart) {
                      $r->whereIn('statut', ['en_attente', 'confirmee'])
                        ->where('date_arrivee', '<', $dateDepart)
                        ->where('date_depart',  '>', $dateArrivee);
                  });
            }]);
        }

        // ── TRI des résultats ─────────────────────────────────
        // Par défaut : trier par note moyenne décroissante
        // Les mieux notés apparaissent en premier
        $query->orderByDesc('avis_avg_note');

        return $query->paginate($perPage);
    }

    /**
     * Liste paginée sans filtres (méthode de base).
     */
    public function getAllPaginated(int $perPage = 12)
    {
        return $this->hotel
            ->with(['typesChambre', 'services', 'avis'])
            ->withAvg('avis', 'note')
            ->withCount('avis')
            ->where('is_active', true)
            ->paginate($perPage);
    }

    /**
     * Trouver un hôtel par son ID.
     */
    public function findById(int $id): ?Hotel
    {
        return $this->hotel
            ->with([
                'typesChambre',
                'services',
                'avis.user',
                'chambres',
            ])
            ->find($id);
    }

    /**
     * Créer un hôtel.
     */
    public function create(array $data): Hotel
    {
        return $this->hotel->create($data);
    }

    /**
     * Mettre à jour un hôtel.
     */
    public function update(int $id, array $data): Hotel
    {
        $hotel = $this->hotel->findOrFail($id);
        $hotel->update($data);
        return $hotel->fresh();
    }

    /**
     * Supprimer un hôtel.
     */
    public function delete(int $id): bool
    {
        return (bool) $this->hotel->findOrFail($id)->delete();
    }

    /**
     * Recommandations personnalisées basées sur l'historique.
     * Retourne : ['hasHistory' => bool, 'preferences' => array|null, 'hotels' => Collection]
     */
    public function getRecommandations(int $userId): array
    {
        $reservations = \App\Models\Reservation::with([
                'chambre.hotel.typesChambre',
                'chambre.typeChambre',
            ])
            ->where('user_id', $userId)
            ->get();

        // ── Aucun historique ──────────────────────────────────
        if ($reservations->isEmpty()) {
            $hotels = $this->hotel
                ->with(['typesChambre', 'services', 'avis'])
                ->withAvg('avis', 'note')
                ->withCount('avis')
                ->where('is_active', true)
                ->orderByDesc('avis_avg_note')
                ->limit(6)
                ->get()
                ->each(function ($h) {
                    $h->prix_min = $h->typesChambre->min('prix_base');
                });

            return ['hasHistory' => false, 'preferences' => null, 'hotels' => $hotels];
        }

        // ── Ville préférée ────────────────────────────────────
        $villesCount = [];
        foreach ($reservations as $r) {
            $ville = $r->chambre->hotel->ville ?? null;
            if ($ville) {
                $villesCount[$ville] = ($villesCount[$ville] ?? 0) + 1;
            }
        }
        arsort($villesCount);
        $villePreferee = array_key_first($villesCount);

        // ── Prix moyen par nuit ───────────────────────────────
        $totalPrix = 0;
        $nbValides = 0;
        foreach ($reservations as $r) {
            $nuits = $r->date_arrivee->diffInDays($r->date_depart);
            if ($nuits > 0 && $r->prix_total > 0) {
                $totalPrix += $r->prix_total / $nuits;
                $nbValides++;
            }
        }
        $prixMoyen = $nbValides > 0 ? $totalPrix / $nbValides : 0;

        // ── Étoiles préférées ─────────────────────────────────
        $totalEtoiles = 0;
        $nbEtoiles    = 0;
        foreach ($reservations as $r) {
            $e = $r->chambre->hotel->etoiles ?? null;
            if ($e) {
                $totalEtoiles += $e;
                $nbEtoiles++;
            }
        }
        $etoilesPreferees = $nbEtoiles > 0 ? (int) round($totalEtoiles / $nbEtoiles) : null;

        // ── Hôtels à exclure (réservés dans les 30 derniers jours) ──
        $exclure = $reservations
            ->filter(fn($r) => $r->created_at && $r->created_at->gte(now()->subDays(30)))
            ->map(fn($r) => $r->chambre->hotel->id ?? null)
            ->filter()
            ->unique()
            ->toArray();

        // ── Scoring ───────────────────────────────────────────
        $hotels = $this->hotel
            ->with(['typesChambre', 'services', 'avis'])
            ->withAvg('avis', 'note')
            ->withCount('avis')
            ->where('is_active', true)
            ->whereNotIn('id', $exclure)
            ->get();

        foreach ($hotels as $hotel) {
            $score = 0;

            if ($villePreferee && $hotel->ville === $villePreferee) {
                $score += 3;
            }
            if ($etoilesPreferees !== null && abs($hotel->etoiles - $etoilesPreferees) <= 1) {
                $score += 3;
            }
            if ($prixMoyen > 0) {
                $pMin = $hotel->typesChambre->min('prix_base') ?? 0;
                $pMax = $hotel->typesChambre->max('prix_base') ?? 0;
                if ($pMin <= $prixMoyen * 1.3 && $pMax >= $prixMoyen * 0.7) {
                    $score += 1;
                }
            }

            $hotel->score    = $score;
            $hotel->prix_min = $hotel->typesChambre->min('prix_base');
        }

        return [
            'hasHistory'  => true,
            'preferences' => [
                'ville_preferee'    => $villePreferee,
                'prix_moyen'        => (int) round($prixMoyen),
                'etoiles_preferees' => $etoilesPreferees,
            ],
            'hotels' => $hotels->sortByDesc('score')->take(6)->values(),
        ];
    }

    /**
     * Comparateur de prix entre plusieurs hôtels.
     */
    public function comparerPrix(array $hotelIds)
    {
        return $this->hotel
            ->with(['typesChambre', 'services', 'avis'])
            ->withAvg('avis', 'note')
            ->whereIn('id', $hotelIds)
            ->get()
            ->map(function ($hotel) {
                // Calcul du prix min et max pour chaque hôtel
                $hotel->prix_min = $hotel->typesChambre->min('prix_base');
                $hotel->prix_max = $hotel->typesChambre->max('prix_base');
                $hotel->note_moyenne = round($hotel->avis_avg_note, 1);
                return $hotel;
            });
    }
}