<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProcesVerbal extends Model
{
    use HasFactory;

    protected $table = 'proces_verbaux';

    protected $fillable = [
        'annee',
        'contenu',
        'statut',
        'derniere_modif_par',
        'finalise_par',
        'finalise_le',
        'signature_vr', 'signe_vr_par', 'signe_vr_le',
        'signature_commission', 'signe_commission_par', 'signe_commission_le',
        'transmis_par', 'transmis_le',
        'signature_recteur', 'signe_recteur_par', 'signe_recteur_le',
    ];

    protected $casts = [
        'finalise_le' => 'datetime',
        'signe_vr_le' => 'datetime',
        'signe_commission_le' => 'datetime',
        'transmis_le' => 'datetime',
        'signe_recteur_le' => 'datetime',
    ];

    public function dernierModificateur()
    {
        return $this->belongsTo(User::class, 'derniere_modif_par');
    }

    public function finalisateur()
    {
        return $this->belongsTo(User::class, 'finalise_par');
    }

    public function signataireVr()
    {
        return $this->belongsTo(User::class, 'signe_vr_par');
    }

    public function signataireCommission()
    {
        return $this->belongsTo(User::class, 'signe_commission_par');
    }

    public function transmetteur()
    {
        return $this->belongsTo(User::class, 'transmis_par');
    }

    public function signataireRecteur()
    {
        return $this->belongsTo(User::class, 'signe_recteur_par');
    }

    public function historiques()
    {
        return $this->hasMany(ProcesVerbalHistorique::class)->orderByDesc('created_at');
    }

    public function estFinalise(): bool
    {
        return in_array($this->statut, ['finalise', 'signe_vr', 'signe_commission', 'transmis_recteur', 'signe']);
    }

    public function estSigneVr(): bool
    {
        return !is_null($this->signe_vr_par);
    }

    public function estSigneCommission(): bool
    {
        return !is_null($this->signe_commission_par);
    }

    public function estTransmis(): bool
    {
        return !is_null($this->transmis_par);
    }

    public function estSigneRecteur(): bool
    {
        return $this->statut === 'signe';
    }
}