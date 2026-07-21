<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecapitulatifHebdo extends Model
{
    use HasFactory;

    protected $table = 'recapitulatifs_hebdo';

   protected $fillable = [
    'sg_vr_id',
    'semaine_debut',
    'semaine_fin',
    'montant_total',
    'statut',
    'date_generation',
    'signature_sg_vr',   
];

    protected $casts = [
        'semaine_debut' => 'date',
        'semaine_fin' => 'date',
        'date_generation' => 'datetime',
    ];

    public function sgVr()
    {
        return $this->belongsTo(User::class, 'sg_vr_id');
    }
}