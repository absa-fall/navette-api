<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

   protected $fillable = [
    'user_id',
    'navette_id',
    'groupe_id',
    'nom',
    'prenom',
    'categorie',
    'type_profil',
    'ufr',
    'ville_depart',
    'ville_arrivee',
    'type_trajet',
    'trajet_sens',
    'date_reservation',
    'heure_reservation',
    'statut',
    'motif_refus',
    'chauffeur_id',
    'validee_montee',
    'validee_descente',
    'montant_retenue',
    'present',
    'heure_presence',
    'notification_envoyee',
    'vehicule_id',
    'masquee_passager',
];

   protected $casts = [
    'date_reservation'     => 'date',
    'validee_montee'       => 'boolean',
    'validee_descente'     => 'boolean',
    'present'              => 'boolean',
    'notification_envoyee' => 'boolean',
    'montant_retenue'      => 'decimal:2',
    'heure_presence'       => 'datetime',
    'masquee_passager'     => 'boolean',
];
    //  Tarifs par trajet
    public static function getTarif($villeDepart, $villeArrivee)
    {
        $trajet        = $villeDepart . ' - ' . $villeArrivee;
        $trajetInverse = $villeArrivee . ' - ' . $villeDepart;

        $tarifs = [
            'Dakar - Bambey'     => 2000,
            'Bambey - Dakar'     => 2000,
            'Thies - Bambey'     => 1000,
            'Bambey - Thies'     => 1000,
            'Bambey - Ngouniane' => 500,
            'Ngouniane - Bambey' => 500,
            'Thies - Ngouniane'  => 1000,
            'Ngouniane - Thies'  => 1000,
        ];

        return $tarifs[$trajet] ?? $tarifs[$trajetInverse] ?? 0;
    }

    public function doitPayer()
    {
        return $this->type_profil !== 'vacataire';
    }

    public function calculerRetenue()
    {
        if (!$this->doitPayer()) return 0;
        return self::getTarif($this->ville_depart, $this->ville_arrivee);
    }

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }

    public function navette()
    {
        return $this->belongsTo(OrdreMission::class, 'navette_id');
    }

    //Retourne l'autre réservation du même groupe (aller ↔ retour)
    public function reservationLiee()
    {
        return $this->hasOne(Reservation::class, 'groupe_id', 'groupe_id')
                    ->where('id', '!=', $this->id);
    }
}