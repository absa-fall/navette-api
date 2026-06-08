<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdreMission extends Model
{
    protected $table = 'ordres_mission';

    protected $fillable = [
        'ddl_id',
        'drh_id',
        'sg_drh_id',
        'chauffeur_id',
        'vehicule_id',
        // Champs ordre de mission
        'chauffeur_nom',
        'chauffeur_prenom',
        'nationalite',
        'grade_fonction',
        'destination',
        'objet_mission',
        'moyen_transport',
        'date_depart',
        'heure_depart',
        'date_retour',
        'frais_transport',
        'indemnite_deplacement',
        // Champs calcul et statut
        'trajet',
        'trajet_autre',
        'montant_trajet',
        'motif',
        'statut',
        'signature_sg_drh',
        'date_signature',
        'commentaire_rejet',
    ];

    protected $casts = [
        'signature_sg_drh' => 'boolean',
        'date_depart' => 'date',
        'date_retour' => 'date',
        'date_signature' => 'datetime',
    ];

    protected $attributes = [
        'statut' => 'en_attente_drh',
        'signature_sg_drh' => false,
        'nationalite' => 'Sénégalaise',
        'grade_fonction' => 'Chauffeur',
        'objet_mission' => 'conduit la navette de l\'UAD',
        'frais_transport' => 'Appui en carburant',
        'indemnite_deplacement' => 'Néant',
    ];

    public static function getMontantTrajet(string $trajet): float
    {
        return match($trajet) {
            'dakar_bambey' => 2000,
            'thies_bambey' => 1000,
            'bambey_ngouniane' => 500,
            default => 0,
        };
    }

    public function ddl()
    {
        return $this->belongsTo(User::class, 'ddl_id');
    }

    public function drh()
    {
        return $this->belongsTo(User::class, 'drh_id');
    }

    public function sgDrh()
    {
        return $this->belongsTo(User::class, 'sg_drh_id');
    }

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function vehicule()
    {
        return $this->belongsTo(Vehicule::class);
    }
}