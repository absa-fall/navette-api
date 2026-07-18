<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcesVerbalHistoriqueMasquage extends Model
{
    protected $table = 'proces_verbal_historique_masquages';

    protected $fillable = ['historique_id', 'user_id'];
}