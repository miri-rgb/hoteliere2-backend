<?php

namespace App\Http\Controllers;

use App\Services\ReservationService;
use App\Exceptions\ChambreIndisponibleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;

/**
 * ============================================================
 * CLASSE : ReservationController
 * ============================================================
 * Reçoit les requêtes HTTP et délègue au ReservationService.
 * Ne contient AUCUNE logique métier ni requête SQL.
 *
 * Chemin : app/Http/Controllers/ReservationController.php
 * ============================================================
 */
class ReservationController extends Controller
{
    /**
     * Injection du ReservationService via le constructeur.
     * Laravel résout automatiquement la dépendance.
     */
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    // ════════════════════════════════════════════════════════
    // ROUTES CLIENT (auth:api + role:client)
    // ════════════════════════════════════════════════════════

    /**
     * POST /api/reservations
     * ─────────────────────────────────────────────────────
     * Créer une nouvelle réservation.
     *
     * Body JSON attendu :
     * {
     *   "chambre_id"   : 5,
     *   "date_arrivee" : "2024-07-15",
     *   "date_depart"  : "2024-07-20",
     *   "nb_personnes" : 2,
     *   "commentaire"  : "Chambre non-fumeur svp"
     * }
     */
    public function store(Request $request): JsonResponse
    {
        // Validation de la forme des données (pas la logique métier)
        $validated = $request->validate([
            'chambre_id'   => 'required|integer|exists:chambres,id',
            'date_arrivee' => 'required|date|date_format:Y-m-d',
            'date_depart'  => 'required|date|date_format:Y-m-d|after:date_arrivee',
            'nb_personnes' => 'nullable|integer|min:1|max:10',
            'commentaire'  => 'nullable|string|max:500',
        ]);

        try {
            // Le Service fait TOUT : validation métier, anti-chevauchement,
            // calcul du prix, création BDD, broadcast
            $reservation = $this->reservationService->creerReservation(
                $validated,
                Auth::id()  // ID de l'utilisateur connecté
            );

            return response()->json([
                'success' => true,
                'message' => 'Réservation créée avec succès ! Un email de confirmation vous sera envoyé.',
                'data'    => $reservation,
            ], 201);

        } catch (ChambreIndisponibleException $e) {
            // La chambre est occupée → HTTP 409 Conflict
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'CHAMBRE_INDISPONIBLE',
            ], 409);

        } catch (\InvalidArgumentException $e) {
            // Dates invalides (passé, durée impossible) → HTTP 422
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code'    => 'DATES_INVALIDES',
            ], 422);

        } catch (ModelNotFoundException $e) {
            // Chambre introuvable → HTTP 404
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            // Erreur inattendue → HTTP 500
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/reservations/mes
     * ─────────────────────────────────────────────────────
     * Lister les réservations de l'utilisateur connecté.
     */
    public function mesReservations(): JsonResponse
    {
        $reservations = $this->reservationService->mesReservations(Auth::id());

        return response()->json([
            'success' => true,
            'message' => 'Vos réservations',
            'data'    => $reservations,
            'total'   => $reservations->count(),
        ]);
    }

    /**
     * GET /api/reservations/{id}
     * ─────────────────────────────────────────────────────
     * Détail d'une réservation spécifique.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->trouver($id);

            // Vérification : un client ne peut voir que SES réservations
            if (Auth::user()->role === 'client' && $reservation->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé à cette réservation.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $reservation,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * PATCH /api/reservations/{id}/annuler
     * ─────────────────────────────────────────────────────
     * Annuler une réservation (client ou admin).
     */
    public function annuler(int $id): JsonResponse
    {
        try {
            $isAdmin    = Auth::user()->role === 'admin';
            $reservation = $this->reservationService->annuler($id, Auth::id(), $isAdmin);

            return response()->json([
                'success' => true,
                'message' => 'Réservation annulée avec succès.',
                'data'    => $reservation,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    // ════════════════════════════════════════════════════════
    // ROUTES ADMIN (auth:api + role:admin)
    // ════════════════════════════════════════════════════════

    /**
     * GET /api/admin/reservations
     * ─────────────────────────────────────────────────────
     * Toutes les réservations (vue admin paginée).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $perPage      = (int) $request->get('per_page', 15);
        $reservations = $this->reservationService->listerToutes($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Liste de toutes les réservations',
            'data'    => $reservations->items(),
            'meta'    => [
                'total'        => $reservations->total(),
                'per_page'     => $reservations->perPage(),
                'current_page' => $reservations->currentPage(),
                'last_page'    => $reservations->lastPage(),
            ],
        ]);
    }

    /**
     * PATCH /api/admin/reservations/{id}/confirmer
     * ─────────────────────────────────────────────────────
     * Confirmer une réservation (action admin).
     */
    public function confirmer(int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->confirmer($id);

            return response()->json([
                'success' => true,
                'message' => 'Réservation confirmée avec succès.',
                'data'    => $reservation,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}