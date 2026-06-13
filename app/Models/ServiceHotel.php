<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceHotel extends Model
{
    protected $table    = 'services_hotel';
    protected $fillable = ['hotel_id', 'nom_service'];

    public function hotel() { return $this->belongsTo(Hotel::class); }
}