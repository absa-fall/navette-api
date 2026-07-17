<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageEtude extends Model
{
    protected $table = 'voyages_etudes';


    protected $fillable = [
    'vice_recteur_id',
    'destination',
    'date_debut',
    'date_fin',
    'description',
    'statut_liste',
    'masque_vr',
    'masque_chef_departement',
    'masque_recteur',          
    'masque_directeur_ufr',    
    'masque_commission',      
    'date_publication',        
    'motif',                   
    'arrete_recteur',
'enseignants_notifies',
'masque_admin',   
'signature_liste_definitive',
'signature_liste_preliminaire',
'date_liste_definitive',
];
protected $casts = [
    'date_debut'    => 'date',
    'date_fin'      => 'date',
    'arrete_recteur' => 'boolean',
    'masque_vr'     => 'boolean',  
    'masque_chef_departement' => 'boolean',
    'masque_recteur'          => 'boolean',
    'masque_directeur_ufr'    => 'boolean',
    'masque_commission'       => 'boolean',
    'masque_admin'            => 'boolean',  
];
public function viceRecteur()
    {
        return $this->belongsTo(User::class, 'vice_recteur_id');
    }

    public function beneficiaires()
    {
        return $this->hasMany(VoyageEtudeBeneficiaire::class, 'voyage_id');
    }

    public function arrete()
    {
        return $this->hasOne(ArreteVoyage::class, 'voyage_id');
    }
}