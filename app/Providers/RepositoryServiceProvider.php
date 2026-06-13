<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// ── Interfaces ────────────────────────────────────────────
use App\Repositories\HotelRepositoryInterface;
use App\Repositories\ChambreRepositoryInterface;
use App\Repositories\ReservationRepositoryInterface;

// ── Implémentations concrètes ─────────────────────────────
use App\Repositories\HotelRepository;
use App\Repositories\ChambreRepository;
use App\Repositories\ReservationRepository;

/**
 * ============================================================
 * CLASSE : RepositoryServiceProvider (VERSION COMPLÈTE)
 * ============================================================
 * Rôle : C'est le "chef d'orchestre" des dépendances.
 *
 * Il dit à Laravel :
 *   "Quand quelqu'un demande HotelRepositoryInterface,
 *    donne-lui un HotelRepository."
 *   "Quand quelqu'un demande ChambreRepositoryInterface,
 *    donne-lui un ChambreRepository."
 *   etc.
 *
 * SANS ce fichier → erreur :
 *   "Target [XxxRepositoryInterface] is not instantiable"
 *
 * Chemin : app/Providers/RepositoryServiceProvider.php
 * ============================================================
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Enregistrer tous les bindings Interface → Classe concrète.
     *
     * bind() = nouvelle instance à chaque appel
     * singleton() = une seule instance partagée (plus performant)
     *
     * Pour les Repository, on utilise bind() car chaque requête
     * HTTP doit avoir sa propre instance fraîche.
     */
    public function register(): void
    {
        // ── Binding Hotel ─────────────────────────────────────
        $this->app->bind(
            HotelRepositoryInterface::class,
            HotelRepository::class
        );

        // ── Binding Chambre ───────────────────────────────────
        $this->app->bind(
            ChambreRepositoryInterface::class,
            ChambreRepository::class
        );

        // ── Binding Reservation ───────────────────────────────
        $this->app->bind(
            ReservationRepositoryInterface::class,
            ReservationRepository::class
        );
    }

    public function boot(): void {}
}