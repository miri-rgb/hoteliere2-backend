<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    protected $fillable = ['nom_preference'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_preferences');
    }
}