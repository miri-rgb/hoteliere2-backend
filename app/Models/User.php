<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'telephone',
        'is_active',
        'google_id',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active'         => 'boolean',
    ];

    // ── Requis par JWT ───────────────────────────────────────────
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role'  => $this->role,
            'email' => $this->email,
            'nom'   => $this->prenom . ' ' . $this->nom,
        ];
    }

    // ── Relations ────────────────────────────────────────────────
    public function hotels()       { return $this->hasMany(Hotel::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function avis()         { return $this->hasMany(Avis::class); }
    public function preferences()  { return $this->belongsToMany(Preference::class, 'user_preferences'); }

    // ── Helpers rôles ────────────────────────────────────────────
    public function isClient(): bool         { return $this->role === 'client'; }
    public function isAdministrateur(): bool { return $this->role === 'administrateur'; }

    // ── Ville la plus réservée (pour recommandations internes) ───
    public function villePreferee(): ?string
    {
        $villes = $this->reservations()
            ->with('chambre.hotel')
            ->get()
            ->pluck('chambre.hotel.ville')
            ->filter();

        if ($villes->isEmpty()) return null;

        return $villes->groupBy(fn($v) => $v)
                      ->sortByDesc(fn($g) => $g->count())
                      ->keys()
                      ->first();
    }
}