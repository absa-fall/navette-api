<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegistreTrajet extends Model
{
    protected $fillable = [
        'ordre_mission_id',
        'chauffeur_id',
        'sg_vr_id',
        'date_trajet',
        'heure_depart',
        'heure_arrivee',
        'statut',
        'date_cloture',
    ];

    protected $casts = [
        'date_trajet' => 'date',
        'date_cloture' => 'datetime',
    ];

    public function ordreMission()
    {
        return $this->belongsTo(OrdreMission::class);
    }

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function sgVr()
    {
        return $this->belongsTo(User::class, 'sg_vr_id');
    }

    public function presences()
    {
        return $this->hasMany(PresenceNavette::class, 'registre_id');
    }
}