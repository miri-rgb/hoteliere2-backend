<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'chambre_id',
        'date_arrivee',
        'date_depart',
        'nb_personnes',
        'prix_total',
        'statut',
        'commentaire',
    ];

    protected $casts = [
        'date_arrivee' => 'date',
        'date_depart'  => 'date',
        'prix_total'   => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chambre()
    {
        return $this->belongsTo(Chambre::class);
    }

    public function paiement()
    {
        return $this->hasOne(Paiement::class);
    }
}