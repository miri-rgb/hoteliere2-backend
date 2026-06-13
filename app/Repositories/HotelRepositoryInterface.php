<?php

namespace App\Repositories;

/**
 * ============================================================
 * INTERFACE : HotelRepositoryInterface
 * ============================================================
 * Rôle : Définit le "contrat" (les méthodes obligatoires) que
 *        toute classe Repository pour Hotel doit respecter.
 *
 * Pourquoi une interface ?
 * → Si demain on change de base de données (MySQL → MongoDB),
 *   on crée juste une nouvelle classe qui implémente cette
 *   interface, sans toucher au Service ni au Controller.
 *   C'est le principe SOLID : "Ouvert à l'extension, fermé à
 *   la modification".
 * ============================================================
 */
interface HotelRepositoryInterface
{
    /**
     * Récupérer tous les hôtels avec pagination.
     *
     * @param int $perPage  Nombre d'hôtels par page (défaut : 12)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 12);

    /**
     * Récupérer un hôtel par son identifiant.
     *
     * @param int $id  L'identifiant de l'hôtel
     * @return \App\Models\Hotel|null
     */
    public function findById(int $id): ?\App\Models\Hotel;

    /**
     * Créer un nouvel hôtel.
     *
     * @param array $data  Les données du formulaire
     * @return \App\Models\Hotel
     */
    public function create(array $data): \App\Models\Hotel;

    /**
     * Mettre à jour un hôtel existant.
     *
     * @param int   $id    L'identifiant de l'hôtel à modifier
     * @param array $data  Les nouvelles données
     * @return \App\Models\Hotel
     */
    public function update(int $id, array $data): \App\Models\Hotel;

    /**
     * Supprimer un hôtel.
     *
     * @param int $id  L'identifiant de l'hôtel à supprimer
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Récupérer les hôtels recommandés pour un utilisateur.
     * (Basé sur sa ville préférée issue de son historique)
     *
     * @param int $userId  L'identifiant de l'utilisateur
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecommandations(int $userId);
}