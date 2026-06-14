<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}