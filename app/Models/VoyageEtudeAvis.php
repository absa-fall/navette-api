<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoyageEtudeAvis extends Model
{
    protected $fillable = [
        'beneficiaire_id',
        'user_id',
        'avis',
        'commentaire',
    ];

    public function beneficiaire()
    {
        return $this->belongsTo(VoyageEtudeBeneficiaire::class, 'beneficiaire_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}