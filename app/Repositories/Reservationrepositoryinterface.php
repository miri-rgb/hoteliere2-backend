<?php

namespace App\Repositories;

/**
 * ============================================================
 * INTERFACE : ReservationRepositoryInterface
 * ============================================================
 * Chemin : app/Repositories/ReservationRepositoryInterface.php
 * ============================================================
 */
interface ReservationRepositoryInterface
{
    /**
     * Lister toutes les réservations (admin).
     */
    public function getAllPaginated(int $perPage = 15);

    /**
     * Récupérer les réservations d'un utilisateur spécifique.
     */
    public function getByUser(int $userId);

    /**
     * Récupérer les réservations d'un hôtel spécifique.
     */
    public function getByHotel(int $hotelId);

    /**
     * Trouver une réservation par son ID.
     */
    public function findById(int $id): ?\App\Models\Reservation;

    /**
     * Créer une nouvelle réservation.
     */
    public function create(array $data): \App\Models\Reservation;

    /**
     * Mettre à jour le statut d'une réservation.
     */
    public function updateStatut(int $id, string $statut): \App\Models\Reservation;

    /**
     * Annuler une réservation.
     */
    public function annuler(int $id): \App\Models\Reservation;

    /**
     * Confirmer une réservation.
     */
    public function confirmer(int $id): \App\Models\Reservation;
}