<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── HOTELS ───────────────────────────────────────────────
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('nom');
            $table->string('adresse');
            $table->string('ville');
            $table->string('code_postal')->nullable();
            $table->text('description_detaillee')->nullable();
            $table->string('image_url')->nullable();
            $table->string('categorie');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // ── TYPES_CHAMBRE ─────────────────────────────────────────
        Schema::create('types_chambre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->string('nom_type');
            $table->decimal('prix_base', 10, 2);
            $table->timestamps();
        });

        // ── CHAMBRES ─────────────────────────────────────────────
        Schema::create('chambres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->foreignId('type_chambre_id')->constrained('types_chambre')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        // ── SERVICES_HOTEL ────────────────────────────────────────
        Schema::create('services_hotel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->string('nom_service');
            $table->timestamps();
        });

        // ── REPAS ─────────────────────────────────────────────────
        Schema::create('repas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->string('type_repas');
            $table->decimal('prix', 8, 2);
            $table->timestamps();
        });

        // ── DIVERTISSEMENTS ───────────────────────────────────────
        Schema::create('divertissements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->string('description');
            $table->boolean('est_gratuit')->default(false);
            $table->timestamps();
        });

        // ── PREFERENCES ───────────────────────────────────────────
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->string('nom_preference')->unique();
            $table->timestamps();
        });

        // ── USER_PREFERENCES (pivot) ──────────────────────────────
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('preference_id')->constrained('preferences')->onDelete('cascade');
            $table->primary(['user_id', 'preference_id']);
        });

        // ── RESERVATIONS ──────────────────────────────────────────
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('chambre_id')->constrained('chambres')->onDelete('cascade');
            $table->dateTime('date_reservation');
            $table->date('date_arrivee');
            $table->date('date_depart');
            $table->enum('statut', ['en_attente', 'confirmée', 'annulée', 'terminée'])->default('en_attente');
            $table->timestamps();
        });

        // ── PAIEMENTS ─────────────────────────────────────────────
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->decimal('montant', 10, 2);
            $table->dateTime('date_paiement')->nullable();
            $table->string('jeton_transaction')->nullable();
            $table->enum('methode', ['carte_bancaire', 'paypal', 'virement', 'especes'])->default('carte_bancaire');
            $table->enum('statut', ['en_attente', 'payé', 'remboursé', 'échoué'])->default('en_attente');
            $table->timestamps();
        });

        // ── AVIS ──────────────────────────────────────────────────
        Schema::create('avis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained('hotels')->onDelete('cascade');
            $table->foreignId('reservation_id')->constrained('reservations')->onDelete('cascade');
            $table->unsignedTinyInteger('note');
            $table->text('commentaire_texte');
            $table->string('score_sentiment_ia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avis');
        Schema::dropIfExists('paiements');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('user_preferences');
        Schema::dropIfExists('preferences');
        Schema::dropIfExists('divertissements');
        Schema::dropIfExists('repas');
        Schema::dropIfExists('services_hotel');
        Schema::dropIfExists('chambres');
        Schema::dropIfExists('types_chambre');
        Schema::dropIfExists('hotels');
    }
};