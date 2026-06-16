<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chambre extends Model
{
    protected $fillable = [
        'hotel_id', 'type_chambre_id', 'description', 'image_url', 'is_available',
    ];

    protected $casts = ['is_available' => 'boolean'];

    public function hotel()        { return $this->belongsTo(Hotel::class); }
    public function typeChambre()  { return $this->belongsTo(TypeChambre::class, 'type_chambre_id'); }
    public function reservations() { return $this->hasMany(Reservation::class); }

    // Vérifie si la chambre est libre entre deux dates
    public function estLibre(string $arrivee, string $depart): bool
    {
        return !$this->reservations()
            ->whereIn('statut', ['en_attente', 'confirmée'])
            ->where('date_arrivee', '<', $depart)
            ->where('date_depart',  '>', $arrivee)
            ->exists();
    }
}