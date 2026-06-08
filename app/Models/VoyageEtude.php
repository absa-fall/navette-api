<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageEtude extends Model
{
    protected $fillable = [
        'enseignant_id',
        'vice_recteur_id',
        'destination',
        'date_debut',
        'date_fin',
        'objet',
        'statut',
        'commentaire_vr',
        'ordre_mission_genere',
        'date_limite_rapport',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_limite_rapport' => 'date',
        'ordre_mission_genere' => 'boolean',
    ];

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function viceRecteur()
    {
        return $this->belongsTo(User::class, 'vice_recteur_id');
    }

    public function rapport()
    {
        return $this->hasOne(RapportVoyage::class, 'voyage_id');
    }

    // Vérifie si l'enseignant est éligible (2 ans depuis dernier voyage)
    public static function estEligible(int $enseignantId): bool
    {
        $dernierVoyage = self::where('enseignant_id', $enseignantId)
            ->where('statut', 'approuve')
            ->latest()
            ->first();

        if (!$dernierVoyage) return true;

        return $dernierVoyage->date_debut->diffInYears(now()) >= 2;
    }
}