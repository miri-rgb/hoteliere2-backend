<?php

namespace App\Repositories;

/**
 * ============================================================
 * INTERFACE : ChambreRepositoryInterface
 * ============================================================
 * Contrat que tout Repository de Chambre doit respecter.
 * Chemin : app/Repositories/ChambreRepositoryInterface.php
 * ============================================================
 */
interface ChambreRepositoryInterface
{
    /**
     * Récupérer toutes les chambres d'un hôtel spécifique.
     */
    public function getByHotel(int $hotelId);

    /**
     * Trouver une chambre par son ID.
     */
    public function findById(int $id): ?\App\Models\Chambre;

    /**
     * Vérifier si une chambre est disponible pour une plage de dates.
     * C'est la méthode CLÉ pour l'anti-chevauchement.
     *
     * @param int    $chambreId   ID de la chambre à vérifier
     * @param string $dateArrivee  Date d'arrivée (format: Y-m-d)
     * @param string $dateDepart   Date de départ (format: Y-m-d)
     * @param int|null $excludeReservationId  ID à exclure (pour une modification)
     * @return bool  true = disponible, false = occupée
     */
    public function estDisponible(
        int $chambreId,
        string $dateArrivee,
        string $dateDepart,
        ?int $excludeReservationId = null
    ): bool;

    /**
     * Récupérer toutes les chambres disponibles pour une période donnée.
     */
    public function getDisponibles(string $dateArrivee, string $dateDepart, ?int $hotelId = null);

    /**
     * Créer une chambre.
     */
    public function create(array $data): \App\Models\Chambre;

    /**
     * Mettre à jour une chambre.
     */
    public function update(int $id, array $data): \App\Models\Chambre;

    /**
     * Supprimer une chambre.
     */
    public function delete(int $id): bool;
}