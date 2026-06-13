<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $fillable = [
        'nom',
        'adresse',
        'ville',
        'pays',
        'telephone',
        'email',
        'description',
        'etoiles',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'etoiles'   => 'integer',
    ];

    // ── Relation : Types de chambres ──────────────────────
    public function typesChambre()
    {
        return $this->hasMany(TypeChambre::class);
    }

    // ── Relation : Chambres ───────────────────────────────
    public function chambres()
    {
        return $this->hasMany(Chambre::class);
    }

    // ── Relation : Services ───────────────────────────────
    public function services()
    {
        return $this->hasMany(ServiceHotel::class);
    }

    // ── Relation : Avis ───────────────────────────────────
    public function avis()
    {
        return $this->hasMany(Avis::class);
    }
}