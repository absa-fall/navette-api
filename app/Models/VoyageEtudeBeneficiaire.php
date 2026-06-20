<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\AutorisationAbsence;

class VoyageEtudeBeneficiaire extends Model
{
    protected $table = 'voyage_etude_beneficiaires';

    protected $fillable = [
        'voyage_id',
        'enseignant_id',
        'statut_justificatif',
        'dans_liste_definitive',
        'statut_autorisation',
        'autorisation_pdf',
    ];

    protected $casts = [
        'dans_liste_definitive' => 'boolean',
    ];

    public function voyage()
    {
        return $this->belongsTo(VoyageEtude::class, 'voyage_id');
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function justificatifs()
    {
        return $this->hasMany(VoyageEtudeJustificatif::class, 'beneficiaire_id');
    }

    public function avis()
    {
        return $this->hasMany(VoyageEtudeAvis::class, 'beneficiaire_id');
    }

    public function autorisationAbsence()
    {
        return $this->hasOne(AutorisationAbsence::class, 'beneficiaire_id');
    }
}