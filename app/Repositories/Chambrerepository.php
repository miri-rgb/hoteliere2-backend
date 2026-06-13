<?php

namespace App\Repositories;

use App\Models\Chambre;

/**
 * ============================================================
 * CLASSE : ChambreRepository
 * ============================================================
 * Contient toute la logique SQL/Eloquent pour les chambres.
 * La méthode estDisponible() est la plus importante :
 * elle détecte les chevauchements de dates.
 *
 * Chemin : app/Repositories/ChambreRepository.php
 * ============================================================
 */
class ChambreRepository implements ChambreRepositoryInterface
{
    public function __construct(
        protected Chambre $chambre
    ) {}

    /**
     * Récupérer les chambres d'un hôtel avec leurs types.
     */
    public function getByHotel(int $hotelId)
    {
        return $this->chambre
            ->with(['typeChambre'])         // Anti N+1 : charge le type en 1 requête
            ->where('hotel_id', $hotelId)
            ->get();
    }

    /**
     * Trouver une chambre par son ID avec ses relations.
     */
    public function findById(int $id): ?Chambre
    {
        return $this->chambre
            ->with(['hotel', 'typeChambre', 'reservations'])
            ->find($id);
    }

    /**
     * ══════════════════════════════════════════════════════
     * MÉTHODE CLÉ : Vérification anti-chevauchement de dates
     * ══════════════════════════════════════════════════════
     *
     * ALGORITHME DE CHEVAUCHEMENT :
     * ─────────────────────────────
     * Deux périodes se chevauchent si ET SEULEMENT SI :
     *   debut1 < fin2  ET  fin1 > debut2
     *
     * Exemple visuel :
     *
     *   Réservation existante :  [===== R1 =====]
     *                           ^arrivee1      ^depart1
     *
     *   Nouvelle demande :            [===== R2 =====]
     *                                ^arrivee2      ^depart2
     *
     * Chevauchement détecté car : arrivee2 < depart1 ET depart2 > arrivee1
     *
     * CAS SANS CHEVAUCHEMENT (OK ✅) :
     *   R1: 01/07 → 05/07  |  R2: 05/07 → 10/07  → OK (R2 commence quand R1 finit)
     *   R1: 05/07 → 10/07  |  R2: 01/07 → 05/07  → OK (R2 finit quand R1 commence)
     *
     * CAS AVEC CHEVAUCHEMENT (BLOQUÉ ❌) :
     *   R1: 01/07 → 08/07  |  R2: 05/07 → 12/07  → BLOQUÉ (overlap de 3 jours)
     *   R1: 01/07 → 15/07  |  R2: 03/07 → 10/07  → BLOQUÉ (R2 incluse dans R1)
     */
    public function estDisponible(
        int $chambreId,
        string $dateArrivee,
        string $dateDepart,
        ?int $excludeReservationId = null
    ): bool {
        $query = $this->chambre
            ->find($chambreId)
            ->reservations()
            // On ne compte que les réservations actives (pas annulées)
            ->whereIn('statut', ['en_attente', 'confirmee'])
            // ──────────────────────────────────────────────────────
            // LA REQUÊTE ANTI-CHEVAUCHEMENT :
            // On cherche des réservations qui chevauchent notre période.
            // Une réservation chevauche si :
            //   son arrivée EST AVANT notre départ
            //   ET son départ EST APRÈS notre arrivée
            // ──────────────────────────────────────────────────────
            ->where('date_arrivee', '<', $dateDepart)
            ->where('date_depart', '>', $dateArrivee);

        // Si on modifie une réservation existante, on l'exclut du calcul
        // pour ne pas se bloquer soi-même !
        if ($excludeReservationId) {
            $query->where('id', '!=', $excludeReservationId);
        }

        // S'il existe au moins une réservation qui chevauche → chambre occupée
        $nombreChevauchements = $query->count();

        // true = disponible (0 chevauchement), false = occupée (≥1 chevauchement)
        return $nombreChevauchements === 0;
    }

    /**
     * Récupérer toutes les chambres disponibles pour une période.
     * Très utile pour la recherche sur le frontend.
     */
    public function getDisponibles(string $dateArrivee, string $dateDepart, ?int $hotelId = null)
    {
        // On récupère les IDs des chambres OCCUPÉES pendant cette période
        $chambresOccupeesIds = \DB::table('reservations')
            ->where('statut', '!=', 'annulee')
            ->where('date_arrivee', '<', $dateDepart)
            ->where('date_depart', '>', $dateArrivee)
            ->pluck('chambre_id'); // Retourne juste les IDs

        // On retourne les chambres qui NE SONT PAS dans cette liste
        $query = $this->chambre
            ->with(['typeChambre', 'hotel'])
            ->whereNotIn('id', $chambresOccupeesIds)
            ->where('is_active', true);

        // Filtre optionnel par hôtel
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }

        return $query->get();
    }

    public function create(array $data): Chambre
    {
        return $this->chambre->create($data);
    }

    public function update(int $id, array $data): Chambre
    {
        $chambre = $this->chambre->findOrFail($id);
        $chambre->update($data);
        return $chambre->fresh();
    }

    public function delete(int $id): bool
    {
        return (bool) $this->chambre->findOrFail($id)->delete();
    }
}