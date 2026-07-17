<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArreteVoyage extends Model
{
    protected $fillable = [
        'voyage_id',
        'recteur_id',
        'numero',
        'date_arrete',
        'visas',
        'montant_billet',
        'montant_indemnite',
        'signe',
'signature',
'date_signature',
        'date_envoi_emails',
    ];

    protected $casts = [
        'date_arrete'        => 'date',
        'signe'               => 'boolean',
        'date_signature'      => 'datetime',
        'date_envoi_emails'   => 'datetime',
    ];

    public function voyage()
    {
        return $this->belongsTo(VoyageEtude::class, 'voyage_id');
    }

    public function recteur()
    {
        return $this->belongsTo(User::class, 'recteur_id');
    }
}