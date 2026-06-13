<?php

namespace App\Services;

use App\Repositories\ChambreRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ============================================================
 * CLASSE : ChambreService
 * ============================================================
 * Logique métier pour les chambres.
 * Chemin : app/Services/ChambreService.php
 * ============================================================
 */
class ChambreService
{
    public function __construct(
        protected ChambreRepositoryInterface $chambreRepository
    ) {}

    /**
     * Récupérer les chambres d'un hôtel.
     */
    public function listerParHotel(int $hotelId)
    {
        return $this->chambreRepository->getByHotel($hotelId);
    }

    /**
     * Trouver une chambre ou lever une exception 404.
     */
    public function trouverChambre(int $id): \App\Models\Chambre
    {
        $chambre = $this->chambreRepository->findById($id);

        if (!$chambre) {
            throw new ModelNotFoundException("Chambre ID {$id} introuvable.");
        }

        return $chambre;
    }

    /**
     * Vérifier la disponibilité d'une chambre.
     * Délègue au Repository qui contient l'algorithme SQL.
     *
     * @return bool true = disponible, false = occupée
     */
    public function verifierDisponibilite(
        int $chambreId,
        string $dateArrivee,
        string $dateDepart,
        ?int $excludeReservationId = null
    ): bool {
        // Validation métier : la date de départ doit être après l'arrivée
        if ($dateArrivee >= $dateDepart) {
            throw new \InvalidArgumentException(
                "La date de départ doit être strictement après la date d'arrivée."
            );
        }

        // Validation : on ne peut pas réserver dans le passé
        if ($dateArrivee < now()->format('Y-m-d')) {
            throw new \InvalidArgumentException(
                "La date d'arrivée ne peut pas être dans le passé."
            );
        }

        return $this->chambreRepository->estDisponible(
            $chambreId,
            $dateArrivee,
            $dateDepart,
            $excludeReservationId
        );
    }

    /**
     * Rechercher les chambres disponibles pour une période.
     */
    public function rechercherDisponibles(
        string $dateArrivee,
        string $dateDepart,
        ?int $hotelId = null
    ) {
        return $this->chambreRepository->getDisponibles(
            $dateArrivee,
            $dateDepart,
            $hotelId
        );
    }

    /**
     * Créer une chambre avec validation métier.
     */
    public function creerChambre(array $data): \App\Models\Chambre
    {
        return $this->chambreRepository->create($data);
    }

    /**
     * Mettre à jour une chambre.
     */
    public function mettreAJour(int $id, array $data): \App\Models\Chambre
    {
        $this->trouverChambre($id); // Vérifie l'existence avant MAJ
        return $this->chambreRepository->update($id, $data);
    }

    /**
     * Supprimer une chambre.
     */
    public function supprimer(int $id): bool
    {
        $this->trouverChambre($id);
        return $this->chambreRepository->delete($id);
    }
}