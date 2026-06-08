<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresenceNavette extends Model
{
    protected $fillable = [
        'registre_id',
        'passager_id',
        'statut_passager',
        'heure_enregistrement',
        'heure_montee',
        'heure_descente',
        'latitude_montee',
        'longitude_montee',
        'latitude_descente',
        'longitude_descente',
        'montant_retenue',
        'qr_code',
        'validee_montee',
        'validee_descente',
    ];

    protected $casts = [
        'heure_enregistrement' => 'datetime',
        'heure_montee' => 'datetime',
        'heure_descente' => 'datetime',
        'montant_retenue' => 'decimal:2',
        'validee_montee' => 'boolean',
        'validee_descente' => 'boolean',
    ];

    public function registre()
    {
        return $this->belongsTo(RegistreTrajet::class, 'registre_id');
    }

    public function passager()
    {
        return $this->belongsTo(User::class, 'passager_id');
    }

    public function isComplete()
    {
        return $this->validee_montee && $this->validee_descente;
    }

    public function getDureeTrajet()
    {
        if ($this->heure_montee && $this->heure_descente) {
            return $this->heure_montee->diffInMinutes($this->heure_descente);
        }
        return null;
    }
}