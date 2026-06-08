<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RapportVoyage extends Model
{
    protected $fillable = [
        'voyage_id',
        'enseignant_id',
        'contenu',
        'fichier_pdf',
        'date_depot',
        'statut',
        'commentaire_vr',
    ];

    protected $casts = [
        'date_depot' => 'date',
    ];

    public function voyage()
    {
        return $this->belongsTo(VoyageEtude::class, 'voyage_id');
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }
}