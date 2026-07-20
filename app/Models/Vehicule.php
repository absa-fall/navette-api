<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicule extends Model
{
    protected $fillable = [
        'immatriculation',
        'qr_code',
        'capacite',
        'etat',
        'date_controle_technique',
        'latitude',
        'longitude',
        'position_maj_at',
        'suivi_actif',
    ];

    protected $casts = [
        'latitude'        => 'float',
        'longitude'       => 'float',
        'position_maj_at' => 'datetime',
        'suivi_actif'     => 'boolean',
    ];

    public function ordresMission()
    {
        return $this->hasMany(OrdreMission::class);
    }
}