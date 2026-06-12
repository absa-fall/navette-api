<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresenceNavette extends Model
{
    use HasFactory;

    protected $table = 'presence_navettes';

    protected $fillable = [
        'registre_trajet_id',
        'passager_id',
        'statut_passager',
        'montant_retenue',
        'present',
    ];

    public function registreTrajet()
    {
        return $this->belongsTo(RegistreTrajet::class, 'registre_trajet_id');
    }

    public function passager()
    {
        return $this->belongsTo(User::class, 'passager_id');
    }
}