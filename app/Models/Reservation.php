<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'prenom',
        'categorie',
        'type_profil',
        'ufr',
        'ville_depart',
        'ville_arrivee',
        'date_reservation',
        'heure_reservation',
        'qr_code',
        'statut',
        'chauffeur_id',
        'validee_montee',
        'validee_descente',
        'montant_retenue',
    ];

    protected $casts = [
        'date_reservation' => 'date',
        'validee_montee' => 'boolean',
        'validee_descente' => 'boolean',
        'montant_retenue' => 'decimal:2',
    ];

    // Tarifs selon le trajet
    public static function getTarif($villeDepart, $villeArrivee)
    {
        $trajet = $villeDepart . ' - ' . $villeArrivee;
        $trajetInverse = $villeArrivee . ' - ' . $villeDepart;
        
        $tarifs = [
            'Dakar - Bambey' => 2000,
            'Bambey - Dakar' => 2000,
            'Thies - Bambey' => 1000,
            'Bambey - Thies' => 1000,
            'Bambey - Ngouniane' => 500,
            'Ngouniane - Bambey' => 500,
        ];
        
        return $tarifs[$trajet] ?? $tarifs[$trajetInverse] ?? 0;
    }

    // Vérifie si le passager doit payer
    public function doitPayer()
    {
        // Vacataire = gratuit (selon CDC)
        return $this->type_profil !== 'vacataire';
    }

    // Calcule le montant de la retenue
    public function calculerRetenue()
    {
        if (!$this->doitPayer()) {
            return 0;
        }
        
        return self::getTarif($this->ville_depart, $this->ville_arrivee);
    }

    public function chauffeur()
    {
        return $this->belongsTo(User::class, 'chauffeur_id');
    }
}