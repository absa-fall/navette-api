<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'titre',
        'message',
        'ordre_id',
        'motif_refus',
        'lu',
    ];

    protected $casts = [
        'lu' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ordre()
    {
        return $this->belongsTo(OrdreMission::class, 'ordre_id');
    }
}