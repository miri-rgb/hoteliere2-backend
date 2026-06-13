<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeChambre extends Model
{
    protected $table    = 'types_chambre';
    protected $fillable = ['hotel_id', 'nom_type', 'prix_base'];

    public function hotel()    { return $this->belongsTo(Hotel::class); }
    public function chambres() { return $this->hasMany(Chambre::class); }
}