<?php

namespace App\Http\Controllers;

use App\Services\HotelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * ============================================================
 * CLASSE : HotelController (VERSION AVEC FILTRES)
 * ============================================================
 * Chemin : app/Http/Controllers/HotelController.php
 * ============================================================
 */
class HotelController extends Controller
{
    public function __construct(
        protected HotelService $hotelService
    ) {}

    // ════════════════════════════════════════════════════════
    // ROUTES PUBLIQUES
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/hotels
     * ─────────────────────────────────────────────────────
     * Liste des hôtels avec filtres optionnels.
     *
     * Paramètres URL optionnels :
     *   ?ville=Marrakech
     *   ?etoiles=5
     *   ?prix_min=500&prix_max=2000
     *   ?date_arrivee=2026-06-01&date_depart=2026-06-05
     *   ?service=piscine
     *   ?per_page=12
     *
     * Exemple complet :
     *   GET /api/hotels?ville=Marrakech&etoiles=4&prix_max=1500
     *                  &date_arrivee=2026-06-01&date_depart=2026-06-05
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Récupération de tous les filtres depuis l'URL
            $filtres = [
                'ville'        => $request->get('ville'),
                'etoiles'      => $request->get('etoiles'),
                'prix_min'     => $request->get('prix_min'),
                'prix_max'     => $request->get('prix_max'),
                'date_arrivee' => $request->get('date_arrivee'),
                'date_depart'  => $request->get('date_depart'),
                'service'      => $request->get('service'),
            ];

            $perPage = (int) $request->get('per_page', 12);

            // Appel du Service avec les filtres
            $hotels = $this->hotelService->rechercherHotels($filtres, $perPage);

            // Message dynamique selon les filtres
            $message = $this->construireMessage($filtres, $hotels->total());

            return response()->json([
                'success' => true,
                'message' => $message,
                'filtres' => array_filter($filtres), // Affiche les filtres actifs
                'data'    => $hotels->items(),
                'meta'    => [
                    'total'        => $hotels->total(),
                    'per_page'     => $hotels->perPage(),
                    'current_page' => $hotels->currentPage(),
                    'last_page'    => $hotels->lastPage(),
                ],
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/hotels/{id}
     * ─────────────────────────────────────────────────────
     * Détail d'un hôtel avec toutes ses relations.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $hotel = $this->hotelService->trouverHotel($id);

            return response()->json([
                'success' => true,
                'message' => 'Détail de l\'hôtel',
                'data'    => $hotel,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Hôtel introuvable (ID: {$id})",
            ], 404);
        }
    }

    /**
     * GET /api/hotels/comparer?ids=1,2,3
     * ─────────────────────────────────────────────────────
     * Comparer les prix de plusieurs hôtels.
     *
     * Exemple : GET /api/hotels/comparer?ids=1,2,3
     * Retourne les prix min/max et notes de chaque hôtel.
     */
    public function comparer(Request $request): JsonResponse
    {
        try {
            // Récupérer les IDs depuis ?ids=1,2,3
            $idsString = $request->get('ids', '');
            $hotelIds  = array_map('intval', explode(',', $idsString));
            $hotelIds  = array_filter($hotelIds); // Retire les 0 et valeurs vides

            if (empty($hotelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Veuillez fournir les IDs des hôtels à comparer. Exemple: ?ids=1,2,3',
                ], 422);
            }

            $hotels = $this->hotelService->comparerPrix(array_values($hotelIds));

            return response()->json([
                'success' => true,
                'message' => count($hotels) . ' hôtels comparés',
                'data'    => $hotels,
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/hotels/recommandations
     * ─────────────────────────────────────────────────────
     * Recommandations personnalisées pour l'utilisateur connecté.
     */
    public function recommandations(Request $request): JsonResponse
    {
        $result = $this->hotelService->obtenirRecommandations(
            $request->user()->id
        );

        return response()->json([
            'success'     => true,
            'message'     => 'Recommandations personnalisées',
            'has_history' => $result['hasHistory'],
            'preferences' => $result['preferences'],
            'data'        => $result['hotels'],
        ]);
    }

    // ════════════════════════════════════════════════════════
    // ROUTES ADMIN
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/hotels
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 15);
        $hotels  = $this->hotelService->listerHotels($perPage);

        return response()->json([
            'success' => true,
            'data'    => $hotels->items(),
            'meta'    => [
                'total'        => $hotels->total(),
                'per_page'     => $hotels->perPage(),
                'current_page' => $hotels->currentPage(),
                'last_page'    => $hotels->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/admin/hotels
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nom'         => 'required|string|max:255',
            'ville'       => 'required|string|max:100',
            'adresse'     => 'required|string|max:500',
            'description' => 'nullable|string',
            'telephone'   => 'nullable|string|max:20',
            'email'       => 'nullable|email',
            'etoiles'     => 'required|integer|min:1|max:5',
            'image'       => 'nullable|string',
        ]);

        try {
            $hotel = $this->hotelService->creerHotel($validated);
            return response()->json([
                'success' => true,
                'message' => 'Hôtel créé avec succès',
                'data'    => $hotel,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/admin/hotels/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'nom'         => 'sometimes|string|max:255',
            'ville'       => 'sometimes|string|max:100',
            'adresse'     => 'sometimes|string|max:500',
            'description' => 'nullable|string',
            'telephone'   => 'nullable|string|max:20',
            'email'       => 'nullable|email',
            'etoiles'     => 'sometimes|integer|min:1|max:5',
            'image'       => 'nullable|string',
        ]);

        try {
            $hotel = $this->hotelService->mettreAJourHotel($id, $validated);
            return response()->json([
                'success' => true,
                'message' => 'Hôtel mis à jour',
                'data'    => $hotel,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Hôtel introuvable (ID: {$id})",
            ], 404);
        }
    }

    /**
     * DELETE /api/admin/hotels/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->hotelService->supprimerHotel($id);
            return response()->json([
                'success' => true,
                'message' => 'Hôtel supprimé avec succès',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Hôtel introuvable (ID: {$id})",
            ], 404);
        }
    }

    // ════════════════════════════════════════════════════════
    // MÉTHODE PRIVÉE : Construction du message de réponse
    // ════════════════════════════════════════════════════════

    /**
     * Construit un message dynamique selon les filtres actifs.
     */
    private function construireMessage(array $filtres, int $total): string
    {
        $filtresActifs = array_filter($filtres);

        if (empty($filtresActifs)) {
            return "Liste des hôtels ({$total} résultats)";
        }

        $parties = [];

        if (!empty($filtres['ville'])) {
            $parties[] = "à {$filtres['ville']}";
        }
        if (!empty($filtres['etoiles'])) {
            $parties[] = "{$filtres['etoiles']} étoiles";
        }
        if (!empty($filtres['prix_min']) || !empty($filtres['prix_max'])) {
            $min = $filtres['prix_min'] ?? '0';
            $max = $filtres['prix_max'] ?? '∞';
            $parties[] = "entre {$min} et {$max} MAD/nuit";
        }
        if (!empty($filtres['date_arrivee']) && !empty($filtres['date_depart'])) {
            $parties[] = "disponibles du {$filtres['date_arrivee']} au {$filtres['date_depart']}";
        }
        if (!empty($filtres['service'])) {
            $parties[] = "avec {$filtres['service']}";
        }

        $description = implode(', ', $parties);

        return "{$total} hôtel(s) trouvé(s) {$description}";
    }
}