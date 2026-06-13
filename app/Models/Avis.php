<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Avis extends Model
{
    protected $fillable = [
        'user_id', 'hotel_id', 'note', 'commentaire'
    ];

    public function client()      { return $this->belongsTo(User::class, 'user_id'); }
    public function hotel()       { return $this->belongsTo(Hotel::class); }
    public function reservation() { return $this->belongsTo(Reservation::class); }
}