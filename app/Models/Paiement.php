<?php
 
namespace App\Models;
 
use Illuminate\Database\Eloquent\Model;
 
class Paiement extends Model
{
    protected $fillable = [
        'reservation_id', 'montant', 'date_paiement',
        'jeton_transaction', 'methode', 'statut'
    ];
 
    protected $casts = ['date_paiement' => 'datetime'];
 
    public function reservation() { return $this->belongsTo(Reservation::class); }
}
 