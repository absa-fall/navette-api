<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutorisationAbsence extends Model
{
    protected $table = 'autorisations_absence';

    protected $fillable = [
        'beneficiaire_id',
        'enseignant_id',
        'numero',
        'date_presentation',
        'nom_demandeur',
        'fonction',
        'ufr_departement',
        'motif_mission',
        'justificatif',
        'lieu_deplacement',
        'periode_debut',
        'periode_fin',
        'organisme_charge',
        'signature_enseignant',
'signature_enseignant_image',
'signature_chef_departement_image',
'signature_directeur_ufr_image',
'signature_recteur_image',
        'chef_departement_id',
        'avis_chef_departement',
        'commentaire_chef_departement',
        'date_avis_chef_departement',
        'directeur_ufr_id',
        'avis_directeur_ufr',
        'commentaire_directeur_ufr',
        'date_avis_directeur_ufr',
        'recteur_id',
        'date_signature_recteur',
        'vr_id',
        'date_transmission_vr',
        'statut',
        'masque_chef_departement',
'masque_directeur_ufr',
'masque_recteur',
'masque_enseignant',
    ];

    protected $casts = [
        'date_presentation'            => 'date',
        'periode_debut'                => 'date',
        'periode_fin'                  => 'date',
        'signature_enseignant'         => 'boolean',
        'date_avis_chef_departement'   => 'datetime',
        'date_avis_directeur_ufr'      => 'datetime',
        'date_signature_recteur'       => 'datetime',
        'date_transmission_vr'         => 'datetime',
    ];

    protected $attributes = [
        'fonction' => 'Enseignant-Chercheur',
        'statut'   => 'soumise',
    ];
protected $appends = ['justificatif_url'];

public function getJustificatifUrlAttribute()
{
    return $this->justificatif ? asset('storage/' . $this->justificatif) : null;
}
    public function beneficiaire()
    {
        return $this->belongsTo(VoyageEtudeBeneficiaire::class, 'beneficiaire_id');
    }

    public function enseignant()
    {
        return $this->belongsTo(User::class, 'enseignant_id');
    }

    public function chefDepartement()
    {
        return $this->belongsTo(User::class, 'chef_departement_id');
    }

    public function directeurUfr()
    {
        return $this->belongsTo(User::class, 'directeur_ufr_id');
    }

    public function recteur()
    {
        return $this->belongsTo(User::class, 'recteur_id');
    }

    public function vr()
    {
        return $this->belongsTo(User::class, 'vr_id');
    }
}