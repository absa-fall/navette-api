<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    protected $fillable = [
        'immatriculation',
        'capacite',
        'etat',
        'date_controle_technique',
    ];

    public function ordresMission()
    {
        return $this->hasMany(OrdreMission::class);
    }
}