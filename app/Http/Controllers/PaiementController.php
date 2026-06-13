<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Stripe\Stripe;
use Stripe\PaymentIntent;

class PaiementController extends Controller
{
    // ── Détecte si Stripe est configuré ou en mode simulation ──
    private function estModeSimulation(): bool
    {
        $secret = env('STRIPE_SECRET', '');
        return !str_starts_with($secret, 'sk_test_') && !str_starts_with($secret, 'sk_live_');
    }

    private function infoReservation(Reservation $r): array
    {
        return [
            'id'           => $r->id,
            'prix_total'   => $r->prix_total,
            'date_arrivee' => $r->date_arrivee,
            'date_depart'  => $r->date_depart,
            'hotel_nom'    => $r->chambre->hotel->nom ?? null,
            'chambre_num'  => $r->chambre->numero ?? null,
            'chambre_type' => $r->chambre->typeChambre->nom ?? null,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // STRIPE — Créer un PaymentIntent
    // POST /api/paiements/intent
    // Body : { reservation_id }
    // ══════════════════════════════════════════════════════════
    public function creerIntent(Request $request): JsonResponse
    {
        $request->validate([
            'reservation_id' => 'required|integer|exists:reservations,id',
        ]);

        $reservation = Reservation::with(['chambre.hotel', 'chambre.typeChambre'])
            ->where('id', $request->reservation_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation introuvable ou accès refusé.',
            ], 403);
        }

        $montant = $reservation->prix_total ?? 0;

        if ($montant <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Le montant de la réservation est invalide.',
            ], 422);
        }

        // ── Mode simulation (pas de vraie clé Stripe) ──────────
        if ($this->estModeSimulation()) {
            $simToken = 'sim_' . strtoupper(Str::random(24));

            Paiement::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'montant'           => $montant,
                    'methode'           => 'carte',
                    'statut'            => 'en_attente',
                    'reference'         => $simToken,
                ]
            );

            return response()->json([
                'success'       => true,
                'simulation'    => true,
                'client_secret' => $simToken,
                'montant'       => $montant,
                'reservation'   => $this->infoReservation($reservation),
            ]);
        }

        // ── Mode Stripe réel ───────────────────────────────────
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount'      => (int) round($montant * 100),
                'currency'    => 'mad',
                'description' => 'Réservation #' . $reservation->id . ' — Hôtelière 2.0',
                'metadata'    => [
                    'reservation_id' => $reservation->id,
                    'user_id'        => Auth::id(),
                ],
            ]);

            Paiement::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'montant'           => $montant,
                    'methode'           => 'carte',
                    'statut'            => 'en_attente',
                    'reference'         => $paymentIntent->id,
                ]
            );

            return response()->json([
                'success'       => true,
                'simulation'    => false,
                'client_secret' => $paymentIntent->client_secret,
                'montant'       => $montant,
                'reservation'   => $this->infoReservation($reservation),
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Stripe : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════
    // STRIPE — Confirmer le paiement
    // POST /api/paiements/confirmer
    // Body : { reservation_id, payment_intent_id }
    // ══════════════════════════════════════════════════════════
    public function confirmer(Request $request): JsonResponse
    {
        $request->validate([
            'reservation_id'    => 'required|integer|exists:reservations,id',
            'payment_intent_id' => 'required|string',
        ]);

        $reservation = Reservation::where('id', $request->reservation_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$reservation) {
            return response()->json([
                'success' => false,
                'message' => 'Réservation introuvable ou accès refusé.',
            ], 403);
        }

        // ── Mode simulation ────────────────────────────────────
        if (str_starts_with($request->payment_intent_id, 'sim_')) {
            $reservation->update(['statut' => 'confirmée']);

            Paiement::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'montant'           => $reservation->prix_total,
                    'methode'           => 'carte',
                    'statut'            => 'valide',
                    'reference'         => $request->payment_intent_id,
                    'date_paiement'     => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement simulé confirmé ! Réservation validée.',
                'data'    => [
                    'reservation_id'    => $reservation->id,
                    'statut'            => 'confirmée',
                    'payment_intent_id' => $request->payment_intent_id,
                ],
            ]);
        }

        // ── Mode Stripe réel ───────────────────────────────────
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::retrieve($request->payment_intent_id);

            if ($paymentIntent->status !== 'succeeded') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le paiement n\'a pas abouti (statut : ' . $paymentIntent->status . ').',
                ], 422);
            }

            $reservation->update(['statut' => 'confirmée']);

            Paiement::updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'montant'           => $reservation->prix_total,
                    'methode'           => 'carte',
                    'statut'            => 'valide',
                    'reference'         => $paymentIntent->id,
                    'date_paiement'     => now(),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Paiement confirmé ! Votre réservation est validée.',
                'data'    => [
                    'reservation_id'    => $reservation->id,
                    'statut'            => 'confirmée',
                    'payment_intent_id' => $paymentIntent->id,
                ],
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur Stripe : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ══════════════════════════════════════════════════════════
    // 1. EFFECTUER UN PAIEMENT (100% interne, sans API externe)
    // POST /api/paiements
    // Header : Authorization: Bearer {token}
    // Body : { reservation_id, methode }
    // ══════════════════════════════════════════════════════════
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reservation_id' => 'required|exists:reservations,id',
            'methode'        => 'required|in:carte,virement,especes,cheque',

            // Champs carte (requis si methode = carte)
            'numero_carte'   => 'required_if:methode,carte|nullable|digits:16',
            'nom_titulaire'  => 'required_if:methode,carte|nullable|string',
            'expiration'     => 'required_if:methode,carte|nullable|date_format:m/y',
            'cvv'            => 'required_if:methode,carte|nullable|digits_between:3,4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors()
            ], 422);
        }

        $reservation = Reservation::with(['chambre.typeChambre', 'paiement'])->find($request->reservation_id);

        // Vérifie que la réservation appartient au client connecté
        if ($reservation->user_id !== Auth::guard('api')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé'
            ], 403);
        }

        // Vérifie qu'elle n'est pas déjà payée
        if ($reservation->paiement && $reservation->paiement->statut === 'valide') {
            return response()->json([
                'success' => false,
                'message' => 'Cette réservation est déjà payée'
            ], 400);
        }

        // Vérifie que la réservation est dans un état payable
        if (!in_array($reservation->statut, ['en_attente', 'confirmée'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être payée (statut : ' . $reservation->statut . ')'
            ], 400);
        }

        // ── Calcul du montant ────────────────────────────────────
        $nbNuits = $reservation->nb_nuits;
        $montant = round($reservation->chambre->typeChambre->prix_base * $nbNuits, 2);

        // ── Traitement interne du paiement ───────────────────────
        // Génère un numéro de transaction unique en interne
        $jetonTransaction = 'HTL-' . strtoupper(Str::random(8)) . '-' . now()->format('YmdHis');

        // Crée ou met à jour le paiement
        $paiement = Paiement::updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'montant'       => $montant,
                'date_paiement' => now(),
                'reference'     => $jetonTransaction,
                'methode'       => $request->methode,
                'statut'        => 'valide',
            ]
        );

        // Met à jour le statut de la réservation en "confirmée"
        $reservation->confirmer();

        return response()->json([
            'success'           => true,
            'message'           => 'Paiement effectué avec succès',
            'data'              => [
                'paiement'          => $paiement,
                'reference'         => $jetonTransaction,
                'montant'           => $montant,
                'nb_nuits'          => $nbNuits,
                'reservation'       => $reservation->fresh()->load('chambre.hotel'),
            ]
        ], 201);
    }

    // ══════════════════════════════════════════════════════════
    // 2. REMBOURSER UN PAIEMENT (admin ou propriétaire)
    // PATCH /api/paiements/{id}/rembourser
    // ══════════════════════════════════════════════════════════
    public function rembourser(int $id): JsonResponse
    {
        $paiement = Paiement::with('reservation')->find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement introuvable'
            ], 404);
        }

        if ($paiement->statut !== 'valide') {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les paiements "payé" peuvent être remboursés'
            ], 400);
        }

        $paiement->update(['statut' => 'remboursé']);
        $paiement->reservation->annuler();

        return response()->json([
            'success' => true,
            'message' => 'Remboursement effectué',
            'data'    => $paiement->fresh()
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 3. VOIR LES PAIEMENTS (admin)
    // GET /api/admin/paiements
    // ══════════════════════════════════════════════════════════
    public function index(Request $request): JsonResponse
    {
        $query = Paiement::with(['reservation.client', 'reservation.chambre.hotel']);

        if ($request->has('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->has('methode')) {
            $query->where('methode', $request->methode);
        }

        $paiements = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $paiements
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // 4. VOIR LE DÉTAIL D'UN PAIEMENT
    // GET /api/paiements/{id}
    // ══════════════════════════════════════════════════════════
    public function show(int $id): JsonResponse
    {
        $user     = Auth::guard('api')->user();
        $paiement = Paiement::with(['reservation.client', 'reservation.chambre.hotel'])->find($id);

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Paiement introuvable'
            ], 404);
        }

        // Le client ne peut voir que ses propres paiements
        if ($user->isClient() && $paiement->reservation->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => $paiement
        ]);
    }
}