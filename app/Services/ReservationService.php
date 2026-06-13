<?php

namespace App\Services;

use App\Repositories\ReservationRepositoryInterface;
use App\Repositories\ChambreRepositoryInterface;
use App\Exceptions\ChambreIndisponibleException;
use App\Mail\ConfirmationReservation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * ============================================================
 * CLASSE : ReservationService
 * ============================================================
 * Contient :
 *   1. La validation des dates (règles métier)
 *   2. La vérification anti-chevauchement
 *   3. Le calcul automatique du prix total
 *   4. L'envoi de l'email de confirmation
 *   5. La notification temps réel (broadcast)
 *
 * Chemin : app/Services/ReservationService.php
 * ============================================================
 */
class ReservationService
{
    /**
     * Injection des deux repositories nécessaires.
     */
    public function __construct(
        protected ReservationRepositoryInterface $reservationRepository,
        protected ChambreRepositoryInterface     $chambreRepository
    ) {}

    // ════════════════════════════════════════════════════════
    // MÉTHODE PRINCIPALE : Créer une réservation
    // ════════════════════════════════════════════════════════

    public function creerReservation(array $data, int $userId): \App\Models\Reservation
    {
        // ── ÉTAPE 1 : Validation des dates ───────────────────
        $this->validerDates($data['date_arrivee'], $data['date_depart']);

        // ── ÉTAPE 2 : Charger la chambre AVEC son type ───────
        $chambre = \App\Models\Chambre::with('typeChambre')
                                      ->find($data['chambre_id']);

        if (!$chambre) {
            throw new ModelNotFoundException(
                "La chambre ID {$data['chambre_id']} n'existe pas."
            );
        }

        // ── ÉTAPE 3 : Vérifier que le type chambre est chargé
        if (!$chambre->typeChambre) {
            throw new \Exception(
                "Impossible de calculer le prix : le type de la chambre ".
                "ID {$data['chambre_id']} est introuvable."
            );
        }

        // ── ÉTAPE 4 : Vérification anti-chevauchement ────────
        // Requête SQL :
        //   SELECT COUNT(*) FROM reservations
        //   WHERE chambre_id = ?
        //     AND statut IN ('en_attente','confirmee')
        //     AND date_arrivee < [notre dateDepart]
        //     AND date_depart  > [notre dateArrivee]
        $estDisponible = $this->chambreRepository->estDisponible(
            $data['chambre_id'],
            $data['date_arrivee'],
            $data['date_depart']
        );

        if (!$estDisponible) {
            throw new ChambreIndisponibleException(
                "La chambre \"{$chambre->numero}\" est déjà réservée ".
                "du {$data['date_arrivee']} au {$data['date_depart']}. ".
                "Veuillez choisir d'autres dates ou une autre chambre."
            );
        }

        // ── ÉTAPE 5 : Calcul du prix total ───────────────────
        // Exemple : arrivée 01/06, départ 05/06 = 4 nuits
        //           prix_base = 600 MAD
        //           prix_total = 600 × 4 = 2400 MAD
        $dateArrivee = Carbon::parse($data['date_arrivee']);
        $dateDepart  = Carbon::parse($data['date_depart']);
        $nombreNuits = $dateArrivee->diffInDays($dateDepart);
        $prixTotal   = (float) $chambre->typeChambre->prix_base * $nombreNuits;

        // ── ÉTAPE 6 : Création en base de données ────────────
        $reservation = $this->reservationRepository->create([
            'user_id'      => $userId,
            'chambre_id'   => $data['chambre_id'],
            'date_arrivee' => $data['date_arrivee'],
            'date_depart'  => $data['date_depart'],
            'nb_personnes' => $data['nb_personnes'] ?? 1,
            'prix_total'   => $prixTotal,
            'statut'       => 'en_attente',
            'commentaire'  => $data['commentaire'] ?? null,
        ]);

        // ── ÉTAPE 7 : Envoi de l'email de confirmation ───────
        $reservation->load(['user', 'chambre.hotel', 'chambre.typeChambre']);
        $this->envoyerEmailConfirmation($reservation);

        // ── ÉTAPE 8 : Broadcast temps réel ───────────────────
        // Désactivé temporairement (Pusher non configuré)
        // event(new \App\Events\ReservationCreated($reservation));

        return $reservation;
    }

    // ════════════════════════════════════════════════════════
    // AUTRES MÉTHODES
    // ════════════════════════════════════════════════════════

    /**
     * Lister toutes les réservations (admin).
     */
    public function listerToutes(int $perPage = 15)
    {
        return $this->reservationRepository->getAllPaginated($perPage);
    }

    /**
     * Lister les réservations de l'utilisateur connecté.
     */
    public function mesReservations(int $userId)
    {
        return $this->reservationRepository->getByUser($userId);
    }

    /**
     * Trouver une réservation ou lever une exception 404.
     */
    public function trouver(int $id): \App\Models\Reservation
    {
        $reservation = $this->reservationRepository->findById($id);

        if (!$reservation) {
            throw new ModelNotFoundException("Réservation ID {$id} introuvable.");
        }

        return $reservation;
    }

    /**
     * Annuler une réservation.
     * Règles métier :
     *   - Seul le propriétaire ou un admin peut annuler
     *   - Impossible si la date d'arrivée est passée
     *   - Impossible si déjà annulée
     */
    public function annuler(int $reservationId, int $userId, bool $isAdmin = false): \App\Models\Reservation
    {
        $reservation = $this->trouver($reservationId);

        if (!$isAdmin && $reservation->user_id !== $userId) {
            throw new \Exception(
                "Vous n'êtes pas autorisé à annuler cette réservation.",
                403
            );
        }

        if (Carbon::parse($reservation->date_arrivee)->isPast()) {
            throw new \Exception(
                "Impossible d'annuler une réservation dont la date d'arrivée est passée.",
                422
            );
        }

        if ($reservation->statut === 'annulee') {
            throw new \Exception("Cette réservation est déjà annulée.", 422);
        }

        return $this->reservationRepository->annuler($reservationId);
    }

    /**
     * Confirmer une réservation (admin uniquement).
     */
    public function confirmer(int $reservationId): \App\Models\Reservation
    {
        $reservation = $this->trouver($reservationId);

        if ($reservation->statut !== 'en_attente') {
            throw new \Exception(
                "Seules les réservations en attente peuvent être confirmées.",
                422
            );
        }

        $reservationConfirmee = $this->reservationRepository->confirmer($reservationId);

        $reservationConfirmee->load(['user', 'chambre.hotel', 'chambre.typeChambre']);
        $this->envoyerEmailConfirmation($reservationConfirmee);

        return $reservationConfirmee;
    }

    // ════════════════════════════════════════════════════════
    // MÉTHODE PRIVÉE : Envoi email avec routing Gmail / Mailtrap
    // ════════════════════════════════════════════════════════

    private function envoyerEmailConfirmation(\App\Models\Reservation $reservation): void
    {
        try {
            $userEmail = $reservation->user->email;

            if ($userEmail === 'm.miri1820@uca.ac.ma') {
                // ── Gmail SMTP (vrai email) ───────────────────
                config([
                    'mail.mailers.smtp.host'       => 'smtp.gmail.com',
                    'mail.mailers.smtp.port'       => 587,
                    'mail.mailers.smtp.username'   => env('GMAIL_USERNAME'),
                    'mail.mailers.smtp.password'   => env('GMAIL_PASSWORD'),
                    'mail.mailers.smtp.encryption' => 'tls',
                ]);
            } else {
                // ── Mailtrap (email de test) ──────────────────
                config([
                    'mail.mailers.smtp.host'       => env('MAIL_HOST'),
                    'mail.mailers.smtp.port'       => env('MAIL_PORT'),
                    'mail.mailers.smtp.username'   => env('MAIL_USERNAME'),
                    'mail.mailers.smtp.password'   => env('MAIL_PASSWORD'),
                    'mail.mailers.smtp.encryption' => 'tls',
                ]);
            }

            Mail::to($userEmail)->send(new ConfirmationReservation($reservation));
            Log::info("Email de confirmation envoyé via " . ($userEmail === 'm.miri1820@uca.ac.ma' ? 'Gmail' : 'Mailtrap') . " à : {$userEmail}");

        } catch (\Exception $e) {
            Log::warning('Email de confirmation non envoyé : ' . $e->getMessage());
        }
    }

    // ════════════════════════════════════════════════════════
    // MÉTHODE PRIVÉE : Validation des dates
    // ════════════════════════════════════════════════════════

    /**
     * Valide les dates selon les règles métier.
     *
     * @throws \InvalidArgumentException
     */
    private function validerDates(string $dateArrivee, string $dateDepart): void
    {
        $arrivee = Carbon::parse($dateArrivee);
        $depart  = Carbon::parse($dateDepart);

        // Règle 1 : L'arrivée doit être dans le futur
        if ($arrivee->isPast()) {
            throw new \InvalidArgumentException(
                "La date d'arrivée ({$dateArrivee}) ne peut pas être dans le passé."
            );
        }

        // Règle 2 : Le départ doit être strictement après l'arrivée
        if ($depart->lte($arrivee)) {
            throw new \InvalidArgumentException(
                "La date de départ doit être après la date d'arrivée."
            );
        }

        // Règle 3 : Maximum 30 nuits
        if ($arrivee->diffInDays($depart) > 30) {
            throw new \InvalidArgumentException(
                "La durée maximale d'une réservation est de 30 nuits."
            );
        }

        // Règle 4 : Minimum 1 nuit
        if ($arrivee->diffInDays($depart) < 1) {
            throw new \InvalidArgumentException(
                "La durée minimale d'une réservation est d'1 nuit."
            );
        }
    }
}