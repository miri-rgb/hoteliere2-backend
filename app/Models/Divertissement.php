<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Divertissement extends Model
{
    protected $fillable = ['hotel_id', 'description', 'est_gratuit'];
    protected $casts    = ['est_gratuit' => 'boolean'];

    public function hotel() { return $this->belongsTo(Hotel::class); }
}