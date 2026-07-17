<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcesVerbalHistorique extends Model
{
    protected $table = 'proces_verbal_historiques';

    protected $fillable = [
        'proces_verbal_id',
        'user_id',
        'action',
        'contenu_snapshot',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function procesVerbal()
    {
        return $this->belongsTo(ProcesVerbal::class);
    }
}