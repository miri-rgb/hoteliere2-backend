<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AvisController extends Controller
{
    // GET /api/hotels/{id}/avis — public
    public function parHotel(int $hotelId): JsonResponse
    {
        $avis = Avis::with('client:id,nom,prenom')
                    ->where('hotel_id', $hotelId)
                    ->orderBy('created_at', 'desc')
                    ->paginate(10);

        return response()->json([
            'success' => true,
            'data'    => $avis
        ]);
    }

    // POST /api/avis — client connecté
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hotel_id'          => 'required|exists:hotels,id',
            'reservation_id'    => 'required|exists:reservations,id',
            'note'              => 'required|integer|min:1|max:5',
            'commentaire_texte' => 'required|string|min:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        // Vérifie que l'avis n'existe pas déjà pour cette réservation
        $existe = Avis::where('reservation_id', $request->reservation_id)->exists();
        if ($existe) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà laissé un avis pour cette réservation'
            ], 400);
        }

        // Analyse sentiment interne (sans API externe)
        $note      = $request->note;
        $sentiment = $note >= 4 ? 'positif' : ($note == 3 ? 'neutre' : 'négatif');

        $avis = Avis::create([
            'user_id'           => Auth::guard('api')->id(),
            'hotel_id'          => $request->hotel_id,
            'reservation_id'    => $request->reservation_id,
            'note'              => $request->note,
            'commentaire_texte' => $request->commentaire_texte,
            'score_sentiment_ia'=> $sentiment,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Avis ajouté avec succès',
            'data'    => $avis
        ], 201);
    }
}