<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\ChambreController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\AdminController;

/**
 * ============================================================
 * ROUTES API — Hôtelière 2.0 (VERSION COMPLÈTE)
 * ============================================================
 * Chemin : routes/api.php
 * ============================================================
 */

// ════════════════════════════════════════════════════════════
// ROUTES PUBLIQUES
// ════════════════════════════════════════════════════════════

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
    Route::post('/google',   [AuthController::class, 'loginAvecGoogle']);
});

// ── Hotels ────────────────────────────────────────────────
// IMPORTANT : les routes nommées DOIVENT être AVANT '{id}'
// sinon Laravel traite "comparer" ou "recommandations" comme un ID !
Route::get('/hotels/comparer',        [HotelController::class, 'comparer']);
Route::get('/hotels/recommandations', [HotelController::class, 'recommandations'])->middleware('auth:api');
Route::get('/hotels',                 [HotelController::class, 'index']);
Route::get('/hotels/{id}',            [HotelController::class, 'show']);

// ── Chambres disponibles (public) ─────────────────────────
Route::get('/chambres/disponibles',   [ChambreController::class, 'disponibles']);

// ════════════════════════════════════════════════════════════
// ROUTES AUTHENTIFIÉES (JWT requis)
// ════════════════════════════════════════════════════════════

Route::middleware('auth:api')->group(function () {

    // Auth
    Route::get('/auth/me',       [AuthController::class, 'me']);
    Route::put('/auth/profile',  [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout',  [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // ── Chatbot IA ─────────────────────────────────────────
    Route::post('/chatbot', [ChatbotController::class, 'repondre']);

    // ── Routes CLIENT ──────────────────────────────────────
    Route::middleware('role:client')->group(function () {

        // Réservations
        Route::post('/reservations',               [ReservationController::class, 'store']);
        Route::get('/reservations/mes',            [ReservationController::class, 'mesReservations']);
        Route::get('/reservations/{id}',           [ReservationController::class, 'show']);
        Route::patch('/reservations/{id}/annuler', [ReservationController::class, 'annuler']);

        // Paiements Stripe
        Route::post('/paiements/intent',    [PaiementController::class, 'creerIntent']);
        Route::post('/paiements/confirmer', [PaiementController::class, 'confirmer']);

        // Avis
        Route::post('/hotels/{id}/avis', [HotelController::class, 'ajouterAvis']);
    });

    // ── Routes ADMIN ───────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // Statistiques dashboard
        Route::get('/statistiques', [AdminController::class, 'statistiques']);

        // Hotels
        Route::get('/hotels',          [HotelController::class, 'adminIndex']);
        Route::post('/hotels',         [HotelController::class, 'store']);
        Route::put('/hotels/{id}',     [HotelController::class, 'update']);
        Route::delete('/hotels/{id}',  [HotelController::class, 'destroy']);

        // Chambres
        Route::get('/chambres',         [ChambreController::class, 'index']);
        Route::post('/chambres',        [ChambreController::class, 'store']);
        Route::put('/chambres/{id}',    [ChambreController::class, 'update']);
        Route::delete('/chambres/{id}', [ChambreController::class, 'destroy']);

        // Réservations
        Route::get('/reservations',                  [ReservationController::class, 'adminIndex']);
        Route::patch('/reservations/{id}/confirmer', [ReservationController::class, 'confirmer']);
        Route::patch('/reservations/{id}/annuler',   [ReservationController::class, 'annuler']);

        // Users
        Route::get('/users',                         [UserController::class, 'index']);
        Route::get('/users/{id}',                    [UserController::class, 'show']);
        Route::put('/users/{id}',                    [UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-actif',     [UserController::class, 'toggleActif']);
        Route::delete('/users/{id}',                 [UserController::class, 'destroy']);
    });

}); // ← fermeture du groupe auth:api