<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageEtudeJustificatif extends Model
{
    protected $fillable = [
        'beneficiaire_id',
        'fichier_pdf',
        'nom_original',
    ];

    public function beneficiaire()
    {
        return $this->belongsTo(VoyageEtudeBeneficiaire::class, 'beneficiaire_id');
    }
}