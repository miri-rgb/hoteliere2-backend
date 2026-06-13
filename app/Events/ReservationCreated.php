<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ============================================================
 * ÉVÉNEMENT : ReservationCreated
 * ============================================================
 * Cet événement est déclenché (dispatched) dès qu'une nouvelle
 * réservation est créée avec succès.
 *
 * Il implémente ShouldBroadcast → Laravel va automatiquement
 * envoyer les données via WebSocket (Pusher ou Reverb) à tous
 * les clients connectés qui écoutent le canal défini.
 *
 * FLUX TEMPS RÉEL :
 * ─────────────────
 * 1. Client réserve → POST /api/reservations
 * 2. ReservationService crée la réservation
 * 3. event(new ReservationCreated($reservation)) est appelé
 * 4. Laravel envoie les données au serveur Pusher/Reverb
 * 5. Pusher/Reverb pousse les données via WebSocket
 * 6. Le dashboard admin React reçoit la notification en temps réel
 * 7. Une alerte s'affiche : "Nouvelle réservation de [client] !"
 *
 * Chemin : app/Events/ReservationCreated.php
 * ============================================================
 */
class ReservationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * La réservation qui vient d'être créée.
     * Elle sera sérialisée et envoyée au frontend.
     *
     * On la déclare public → Laravel la sérialise automatiquement
     * dans le payload de l'événement broadcast.
     */
    public Reservation $reservation;

    /**
     * Constructeur : on reçoit la réservation complète
     * (avec ses relations : user, chambre, hotel chargées)
     */
    public function __construct(Reservation $reservation)
    {
        $this->reservation = $reservation;
    }

    /**
     * ──────────────────────────────────────────────────────
     * CANAL DE DIFFUSION (Broadcasting Channel)
     * ──────────────────────────────────────────────────────
     *
     * On utilise un PrivateChannel pour la sécurité.
     * Seuls les utilisateurs authentifiés et autorisés
     * peuvent s'abonner à ce canal.
     *
     * Le frontend React s'abonnera à "admin-notifications"
     * pour recevoir toutes les nouvelles réservations.
     *
     * Alternatives possibles :
     * → Channel('public-reservations')        = canal public (tout le monde)
     * → PrivateChannel('admin-notifications') = canal privé (auth requis)
     * → PresenceChannel('hotel.1')            = canal de présence (qui est connecté)
     */
    public function broadcastOn(): array
    {
        return [
            // Canal privé pour les admins
            new PrivateChannel('admin-notifications'),

            // Canal spécifique à l'hôtel concerné
            // (le propriétaire de cet hôtel reçoit la notif)
            new PrivateChannel(
                'hotel.' . $this->reservation->chambre->hotel_id
            ),
        ];
    }

    /**
     * ──────────────────────────────────────────────────────
     * NOM DE L'ÉVÉNEMENT côté frontend
     * ──────────────────────────────────────────────────────
     *
     * C'est le nom que le frontend React écoute avec :
     * channel.listen('.nouvelle.reservation', (data) => { ... })
     *
     * Sans cette méthode, le nom par défaut serait le nom
     * complet de la classe PHP (trop verbeux).
     */
    public function broadcastAs(): string
    {
        return 'nouvelle.reservation';
    }

    /**
     * ──────────────────────────────────────────────────────
     * DONNÉES ENVOYÉES au frontend
     * ──────────────────────────────────────────────────────
     *
     * Par défaut, Laravel envoie toutes les propriétés
     * publiques. Ici on surcharge pour envoyer exactement
     * ce dont le frontend a besoin (pas plus, pas moins).
     */
    public function broadcastWith(): array
    {
        return [
            'reservation_id' => $this->reservation->id,
            'client'         => [
                'nom'    => $this->reservation->user->nom,
                'prenom' => $this->reservation->user->prenom,
                'email'  => $this->reservation->user->email,
            ],
            'chambre'        => [
                'numero' => $this->reservation->chambre->numero,
                'type'   => $this->reservation->chambre->typeChambre->nom ?? '',
            ],
            'hotel'          => [
                'nom'  => $this->reservation->chambre->hotel->nom,
                'ville'=> $this->reservation->chambre->hotel->ville,
            ],
            'dates'          => [
                'arrivee' => $this->reservation->date_arrivee,
                'depart'  => $this->reservation->date_depart,
            ],
            'prix_total'     => $this->reservation->prix_total,
            'statut'         => $this->reservation->statut,
            'created_at'     => $this->reservation->created_at->toDateTimeString(),
            // Message prêt à afficher dans l'interface admin
            'message'        => "Nouvelle réservation de {$this->reservation->user->prenom} {$this->reservation->user->nom} pour l'hôtel {$this->reservation->chambre->hotel->nom}.",
        ];
    }
}