<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Authorization callbacks for private and presence channels.
*/

// Canal par défaut Laravel (notifications utilisateur)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privé pour TOUS les admins (reçoit toutes les nouvelles réservations)
Broadcast::channel('admin-notifications', function ($user) {
    return $user->role === 'admin';
});

// Canal privé par hôtel (l'admin d'un hôtel précis reçoit ses réservations)
// Utilisé dans ReservationCreated::broadcastOn() → PrivateChannel('hotel.{hotelId}')
Broadcast::channel('hotel.{hotelId}', function ($user, $hotelId) {
    return $user->role === 'admin';
});
