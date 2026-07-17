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
    'justificatif_pdf',
    'statut_justificatif',
    'dans_liste_definitive',
    'statut_autorisation',
    'autorisation_pdf',
    'masque_enseignant',
    'masque_chef_departement',  
    'masque_directeur_ufr',     
    'masque_recteur',           
    'masque_vice_recteur',     
    'masque_commission',
];

   protected $casts = [
        'dans_liste_definitive'   => 'boolean',
        'date_limite_soumission'  => 'date',
        'alerte_delai_envoyee'    => 'boolean',
        'date_limite_rapport'     => 'date',
        'alerte_rapport_envoyee'  => 'boolean',
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