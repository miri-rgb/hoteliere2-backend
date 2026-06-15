<?php

namespace App\Repositories;

use App\Models\Reservation;

/**
 * ============================================================
 * CLASSE : ReservationRepository
 * ============================================================
 * Gère les accès BDD pour les réservations.
 * Chemin : app/Repositories/ReservationRepository.php
 * ============================================================
 */
class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(
        protected Reservation $reservation
    ) {}

    /**
     * Liste paginée de toutes les réservations (vue admin).
     * Charge les relations en une seule requête (anti N+1).
     */
    public function getAllPaginated(int $perPage = 15)
    {
        return $this->reservation
            ->with([
                'user',             // Le client qui a réservé
                'chambre.hotel',    // La chambre ET l'hôtel associé
                'chambre.typeChambre', // Le type de chambre
                'paiement',         // Le paiement lié
            ])
            ->orderByDesc('created_at')  // Les plus récentes en premier
            ->paginate($perPage);
    }

    /**
     * Réservations d'un utilisateur avec ses détails.
     */
    public function getByUser(int $userId)
    {
        return $this->reservation
            ->with(['chambre.hotel', 'chambre.typeChambre', 'paiement'])
            ->where('user_id', $userId)
            ->orderByDesc('date_arrivee')
            ->get();
    }

    /**
     * Réservations d'un hôtel (vue admin/propriétaire).
     */
    public function getByHotel(int $hotelId)
    {
        return $this->reservation
            ->with(['user', 'chambre', 'paiement'])
            ->whereHas('chambre', function ($q) use ($hotelId) {
                // whereHas filtre les réservations dont la chambre
                // appartient à cet hôtel. C'est l'équivalent d'un JOIN.
                $q->where('hotel_id', $hotelId);
            })
            ->orderByDesc('date_arrivee')
            ->get();
    }

    /**
     * Trouver une réservation avec toutes ses relations.
     */
    public function findById(int $id): ?Reservation
    {
        return $this->reservation
            ->with(['user', 'chambre.hotel', 'chambre.typeChambre', 'paiement'])
            ->find($id);
    }

    /**
     * Créer une réservation en base de données.
     * Le calcul du prix est déjà fait par le Service avant d'arriver ici.
     */
    public function create(array $data): Reservation
    {
        return $this->reservation->create($data);
    }

    /**
     * Changer le statut d'une réservation.
     * Les statuts possibles : en_attente, confirmee, annulee, terminee
     */
    public function updateStatut(int $id, string $statut): Reservation
    {
        $reservation = $this->reservation->findOrFail($id);
        $reservation->update(['statut' => $statut]);
        return $reservation->fresh()->load(['user', 'chambre.hotel']);
    }

    /**
     * Annuler une réservation.
     */
    public function annuler(int $id): Reservation
    {
        return $this->updateStatut($id, 'annulee');
    }

    /**
     * Confirmer une réservation.
     */
    public function confirmer(int $id): Reservation
    {
        return $this->updateStatut($id, 'confirmee');
    }
}