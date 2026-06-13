<?php

namespace App\Services;

use App\Repositories\HotelRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ============================================================
 * CLASSE : HotelService (VERSION AVEC FILTRES)
 * ============================================================
 * Chemin : app/Services/HotelService.php
 * ============================================================
 */
class HotelService
{
    public function __construct(
        protected HotelRepositoryInterface $hotelRepository
    ) {}

    /**
     * ══════════════════════════════════════════════════════
     * MÉTHODE PRINCIPALE : Rechercher des hôtels avec filtres
     * ══════════════════════════════════════════════════════
     *
     * Valide et nettoie les filtres avant de les envoyer
     * au Repository.
     *
     * @param array $filtres  Filtres venant du Controller
     * @param int   $perPage  Résultats par page
     */
    public function rechercherHotels(array $filtres = [], int $perPage = 12)
    {
        // Nettoyage des filtres : on retire les valeurs vides
        // pour ne pas faire de WHERE inutiles en SQL
        $filtresNettoyes = array_filter($filtres, function ($valeur) {
            return $valeur !== null && $valeur !== '';
        });

        // Validation métier des dates si fournies
        if (!empty($filtresNettoyes['date_arrivee']) && !empty($filtresNettoyes['date_depart'])) {
            $this->validerDatesRecherche(
                $filtresNettoyes['date_arrivee'],
                $filtresNettoyes['date_depart']
            );
        }

        // Validation du prix
        if (!empty($filtresNettoyes['prix_min']) && !empty($filtresNettoyes['prix_max'])) {
            if ($filtresNettoyes['prix_min'] > $filtresNettoyes['prix_max']) {
                throw new \InvalidArgumentException(
                    "Le prix minimum ne peut pas être supérieur au prix maximum."
                );
            }
        }

        return $this->hotelRepository->rechercherAvecFiltres($filtresNettoyes, $perPage);
    }

    /**
     * Lister tous les hôtels sans filtre.
     */
    public function listerHotels(int $perPage = 12)
    {
        return $this->hotelRepository->getAllPaginated($perPage);
    }

    /**
     * Trouver un hôtel par son ID.
     */
    public function trouverHotel(int $id): \App\Models\Hotel
    {
        $hotel = $this->hotelRepository->findById($id);

        if (!$hotel) {
            throw new ModelNotFoundException("Hôtel ID {$id} introuvable.");
        }

        return $hotel;
    }

    /**
     * Créer un hôtel.
     */
    public function creerHotel(array $data): \App\Models\Hotel
    {
        return $this->hotelRepository->create($data);
    }

    /**
     * Mettre à jour un hôtel.
     */
    public function mettreAJourHotel(int $id, array $data): \App\Models\Hotel
    {
        $this->trouverHotel($id);
        return $this->hotelRepository->update($id, $data);
    }

    /**
     * Supprimer un hôtel.
     */
    public function supprimerHotel(int $id): bool
    {
        $this->trouverHotel($id);
        return $this->hotelRepository->delete($id);
    }

    /**
     * Recommandations personnalisées.
     */
    public function obtenirRecommandations(int $userId)
    {
        return $this->hotelRepository->getRecommandations($userId);
    }

    /**
     * Comparateur de prix.
     */
    public function comparerPrix(array $hotelIds)
    {
        if (count($hotelIds) < 2) {
            throw new \InvalidArgumentException(
                "Le comparateur nécessite au minimum 2 hôtels."
            );
        }

        if (count($hotelIds) > 5) {
            throw new \InvalidArgumentException(
                "Le comparateur accepte maximum 5 hôtels à la fois."
            );
        }

        return $this->hotelRepository->comparerPrix($hotelIds);
    }

    /**
     * Valider les dates de recherche.
     * Règles : date_depart doit être après date_arrivee.
     */
    private function validerDatesRecherche(string $dateArrivee, string $dateDepart): void
    {
        $arrivee = \Carbon\Carbon::parse($dateArrivee);
        $depart  = \Carbon\Carbon::parse($dateDepart);

        if ($depart->lte($arrivee)) {
            throw new \InvalidArgumentException(
                "La date de départ doit être après la date d'arrivée."
            );
        }
    }
}