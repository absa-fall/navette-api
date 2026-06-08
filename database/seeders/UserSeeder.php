<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'nom' => 'Diop',
            'prenom' => 'Administrateur',
            'email' => 'admin@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        // DDL (Directeur de la Logistique)
User::create([
    'nom' => 'Ndiaye',
    'prenom' => 'Cheikh',
    'email' => 'ddl@uadb.edu.sn',
    'password' => Hash::make('password'),
    'role' => 'ddl',
    'is_active' => true,
]);

       // Enseignant PER permanent
User::create([
    'nom' => 'Diop',
    'prenom' => 'Amadou',
    'email' => 'enseignant@uadb.edu.sn',
    'password' => Hash::make('password'),
    'role' => 'enseignant',
    'type_profil' => 'PER',
    'statut' => 'permanent',
    'ufr' => 'SATIC',
    'matricule' => 'PER003',
    'is_active' => true,
]);

// Enseignant vacataire
User::create([
    'nom' => 'Mbaye',
    'prenom' => 'Fatou',
    'email' => 'vacataire@uadb.edu.sn',
    'password' => Hash::make('password'),
    'role' => 'enseignant',
    'type_profil' => 'PER',
    'statut' => 'vacataire',
    'ufr' => 'SDD',
    'matricule' => 'PER004',
    'is_active' => true,
]);

        // DRH
        User::create([
            'nom' => 'Sow',
            'prenom' => 'Ibrahima',
            'email' => 'drh@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'drh',
            'is_active' => true,
        ]);

        // SG DRH
        User::create([
            'nom' => 'Ba',
            'prenom' => 'Fatou',
            'email' => 'sg.drh@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'sg_drh',
            'is_active' => true,
        ]);

        // Chauffeur
        User::create([
            'nom' => 'Diallo',
            'prenom' => 'Mamadou',
            'email' => 'chauffeur@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'chauffeur',
            'is_active' => true,
        ]);

        // SG Vice-Recteur
        User::create([
            'nom' => 'Sarr',
            'prenom' => 'Oumar',
            'email' => 'sg.vr@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'sg_vr',
            'is_active' => true,
        ]);

        // Vice-Recteur
        User::create([
            'nom' => 'Kane',
            'prenom' => 'Abdoulaye',
            'email' => 'vr@uadb.edu.sn',
            'password' => Hash::make('password'),
            'role' => 'vice_recteur',
            'is_active' => true,
        ]);
    }
}