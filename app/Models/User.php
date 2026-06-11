<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'password',
        'tel',
        'matricule',
        'type_profil',
        'statut',
        'ufr',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // Nettoyer l'email : supprimer espaces + forcer minuscules
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->email = strtolower(trim($user->email));
        });

        static::updating(function ($user) {
            $user->email = strtolower(trim($user->email));
        });
    }

    // Relations
    public function ordresMissionDDL()
    {
        return $this->hasMany(OrdreMission::class, 'ddl_id');
    }

    public function voyagesEtudes()
    {
        return $this->hasMany(VoyageEtude::class, 'enseignant_id');
    }
}