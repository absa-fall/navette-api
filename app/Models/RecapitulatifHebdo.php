<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecapitulatifHebdo extends Model
{
    protected $fillable = [
        'sg_vr_id',
        'semaine_debut',
        'semaine_fin',
        'montant_total',
        'statut',
        'date_generation',
    ];

    protected $casts = [
        'semaine_debut' => 'date',
        'semaine_fin' => 'date',
        'date_generation' => 'datetime',
        'montant_total' => 'decimal:2',
    ];

    public function sgVr()
    {
        return $this->belongsTo(User::class, 'sg_vr_id');
    }
}