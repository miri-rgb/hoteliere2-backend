<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Repas extends Model
{
    protected $fillable = ['hotel_id', 'type_repas', 'prix'];

    public function hotel() { return $this->belongsTo(Hotel::class); }
}