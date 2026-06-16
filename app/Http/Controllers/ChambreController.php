<?php

namespace App\Http\Controllers;

use App\Models\Chambre;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ChambreController extends Controller
{
    // ════════════════════════════════════════════════════
    // ROUTES PUBLIQUES
    // ════════════════════════════════════════════════════

    /**
     * GET /api/chambres/disponibles
     * Récupérer les chambres disponibles pour une période
     */
    public function disponibles(Request $request): JsonResponse
    {
        $request->validate([
            'hotel_id'     => 'required|integer|exists:hotels,id',
            'date_arrivee' => 'required|date',
            'date_depart'  => 'required|date|after:date_arrivee',
        ]);

        $hotelId = $request->hotel_id;
        $arrivee = $request->date_arrivee;
        $depart  = $request->date_depart;

        $chambres = Chambre::with('typeChambre')
            ->where('hotel_id', $hotelId)
            ->where('is_available', true)
            ->get();

        $result = [];
        foreach ($chambres as $c) {
            if ($c->estLibre($arrivee, $depart)) {
                $typeChambre = $c->typeChambre;
                $result[] = [
                    'id'          => $c->id,
                    'hotel_id'    => $c->hotel_id,
                    'type'        => $typeChambre ? $typeChambre->nom_type : 'Standard',
                    'prix_nuit'   => $typeChambre ? $typeChambre->prix_base : 0,
                    'description' => $c->description,
                    'image_url'   => $c->image_url,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * GET /api/hotels/{id}/chambres
     * Récupérer les chambres d'un hôtel
     */
    public function parHotel(int $hotelId): JsonResponse
    {
        $hotel = Hotel::find($hotelId);

        if (!$hotel) {
            return response()->json([
                'success' => false,
                'message' => 'Hôtel introuvable'
            ], 404);
        }

        $chambres = Chambre::with('typeChambre')
            ->where('hotel_id', $hotelId)
            ->where('is_available', true)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $chambres
        ]);
    }

    // ════════════════════════════════════════════════════
    // ROUTES ADMIN
    // ════════════════════════════════════════════════════

    /**
     * GET /api/admin/chambres
     * Liste toutes les chambres (admin)
     * MÉTHODE MANQUANTE - AJOUTÉE
     */
    public function index(Request $request): JsonResponse
    {
        $chambres = Chambre::with(['hotel', 'typeChambre'])
            ->orderBy('hotel_id')
            ->orderBy('numero')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Liste des chambres',
            'data'    => $chambres,
            'total'   => $chambres->count()
        ]);
    }

    /**
     * POST /api/admin/chambres
     * Créer une nouvelle chambre
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id'        => 'required|integer|exists:hotels,id',
            'type_chambre_id' => 'required|integer|exists:types_chambre,id',
            'description'     => 'nullable|string',
            'image_url'       => 'nullable|string|url',
            'is_available'    => 'nullable|boolean',
        ]);

        $chambre = Chambre::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Chambre ajoutée avec succès',
            'data'    => $chambre->load(['hotel', 'typeChambre'])
        ], 201);
    }

    /**
     * PUT /api/admin/chambres/{id}
     * Modifier une chambre
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $chambre = Chambre::find($id);

        if (!$chambre) {
            return response()->json([
                'success' => false,
                'message' => 'Chambre introuvable'
            ], 404);
        }

        $validated = $request->validate([
            'hotel_id'        => 'sometimes|integer|exists:hotels,id',
            'type_chambre_id' => 'sometimes|integer|exists:types_chambre,id',
            'description'     => 'nullable|string',
            'image_url'       => 'nullable|string|url',
            'is_available'    => 'nullable|boolean',
        ]);

        $chambre->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Chambre modifiée avec succès',
            'data'    => $chambre->fresh()->load(['hotel', 'typeChambre'])
        ]);
    }

    /**
     * DELETE /api/admin/chambres/{id}
     * Supprimer une chambre
     */
    public function destroy(int $id): JsonResponse
    {
        $chambre = Chambre::find($id);

        if (!$chambre) {
            return response()->json([
                'success' => false,
                'message' => 'Chambre introuvable'
            ], 404);
        }

        $chambre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chambre supprimée avec succès'
        ]);
    }
}