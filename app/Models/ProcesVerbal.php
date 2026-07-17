<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesVerbal extends Model
{
    use HasFactory;

    protected $fillable = [
        'annee',
        'contenu',
        'statut',
        'derniere_modif_par',
        'finalise_par',
        'finalise_le',
    ];

    protected $casts = [
        'finalise_le' => 'datetime',
    ];

    public function dernierModificateur()
    {
        return $this->belongsTo(User::class, 'derniere_modif_par');
    }

    public function finalisateur()
    {
        return $this->belongsTo(User::class, 'finalise_par');
    }

    public function estFinalise(): bool
    {
        return $this->statut === 'finalise';
    }
}