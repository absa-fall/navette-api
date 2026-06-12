<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistreTrajet extends Model
{
    use HasFactory;

    protected $table = 'registre_trajets';

    protected $fillable = [
        'chauffeur_id',
        'vehicule_id',
        'ordre_mission_id',
        'date_trajet',
        'heure_depart',
        'heure_arrivee',
        'kilometrage_depart',
        'kilometrage_arrivee',
        'statut',
    ];

    protected $casts = [
        'date_trajet' => 'date',
        'heure_depart' => 'datetime',
        'heure_arrivee' => 'datetime',
    ];

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function ordreMission()
    {
        return $this->belongsTo(OrdreMission::class);
    }

    public function presences()
    {
        return $this->hasMany(PresenceNavette::class, 'registre_trajet_id');
    }
}