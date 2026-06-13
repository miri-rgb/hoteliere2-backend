<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function statistiques(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'users' => [
                    'total'   => \App\Models\User::count(),
                    'admins'  => \App\Models\User::where('role', 'admin')->count(),
                    'clients' => \App\Models\User::where('role', 'client')->count(),
                    'actifs'  => \App\Models\User::where('is_active', true)->count(),
                ],
                'hotels' => [
                    'total'  => \App\Models\Hotel::count(),
                    'actifs' => \App\Models\Hotel::where('is_active', true)->count(),
                ],
                'chambres' => [
                    'total'   => \App\Models\Chambre::count(),
                    'actives' => \App\Models\Chambre::where('is_active', true)->count(),
                ],
                'reservations' => [
                    'total'      => \App\Models\Reservation::count(),
                    'en_attente' => \App\Models\Reservation::where('statut', 'en_attente')->count(),
                    'confirmee'  => \App\Models\Reservation::where('statut', 'confirmee')->count(),
                    'annulee'    => \App\Models\Reservation::where('statut', 'annulee')->count(),
                    'terminee'   => \App\Models\Reservation::where('statut', 'terminee')->count(),
                ],
                'paiements' => [
                    'total'         => \App\Models\Paiement::count(),
                    'valides'       => \App\Models\Paiement::where('statut', 'valide')->count(),
                    'montant_total' => \App\Models\Paiement::where('statut', 'valide')->sum('montant'),
                ],
            ],
        ]);
    }
}
