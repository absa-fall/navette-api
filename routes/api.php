<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculeController;
use App\Http\Controllers\OrdreMissionController;
use App\Http\Controllers\RegistreTrajetController;
use App\Http\Controllers\VoyageEtudeController;
use App\Http\Controllers\RapportVoyageController;
use App\Http\Controllers\RecapitulatifHebdoController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\NotificationController;

// ============================================
// ROUTES PUBLIQUES
// ============================================

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/reservations', [ReservationController::class, 'store']);
Route::post('/validation/montee', [ReservationController::class, 'validerMontee']);
Route::post('/validation/descente', [ReservationController::class, 'validerDescente']);
Route::get('/validation/verifier/{qrCode}', [ReservationController::class, 'verifierQR']);

// ============================================
// ROUTES PROTÉGÉES
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/rapports/{id}/download', [RapportVoyageController::class, 'download']);
    Route::get(
    '/notifications/sidebar',
    [NotificationController::class, 'sidebar']
    
);

    // Users (admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    });

    Route::get('/chauffeurs', [UserController::class, 'chauffeurs']);
    Route::get('/drhs', [UserController::class, 'drhs']);

    // Vehicules
    Route::get('/vehicules', [VehiculeController::class, 'index']);
    Route::get('/vehicules/disponibles', [VehiculeController::class, 'disponibles']);
    Route::get('/vehicules/{id}', [VehiculeController::class, 'show']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/vehicules', [VehiculeController::class, 'store']);
        Route::put('/vehicules/{id}', [VehiculeController::class, 'update']);
        Route::delete('/vehicules/{id}', [VehiculeController::class, 'destroy']);
    });

    // ============================================
    // RESERVATIONS
    // ============================================

    Route::get('/reservations/chauffeur', [ReservationController::class, 'pourChauffeur']);

    Route::middleware('role:sg_vr')->group(function () {
        Route::get('/reservations/sgvr', [ReservationController::class, 'pourSGVR']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    });

    // ============================================
    // ORDRES DE MISSION
    // ============================================
// DDL
Route::middleware('role:ddl')->group(function () {
    Route::post('/ordres-mission', [OrdreMissionController::class, 'store']);
    Route::get('/mes-ordres', [OrdreMissionController::class, 'mesOrdres']);
    Route::delete('/ordres-mission/{id}', [OrdreMissionController::class, 'destroy']);
    Route::put('/ordres-mission/{id}', [OrdreMissionController::class, 'update']);
});

// DRH
Route::middleware('role:drh')->group(function () {
    Route::patch('/ordres-mission/{id}/approuver-drh', [OrdreMissionController::class, 'approuverDRH']);
    Route::patch('/ordres-mission/{id}/rejeter-drh', [OrdreMissionController::class, 'rejeterDRH']);
});

// SG DRH
Route::middleware('role:sg_drh')->group(function () {
    Route::get('/ordres-mission-a-signer', [OrdreMissionController::class, 'aSigner']);
    Route::patch('/ordres-mission/{id}/signer', [OrdreMissionController::class, 'signer']);
});

// Chauffeur
Route::middleware('role:chauffeur')->group(function () {
    Route::get('/ordres-mission-chauffeur', [OrdreMissionController::class, 'pourChauffeur']);
    Route::patch('/ordres-mission/{id}/marquer-recu', [OrdreMissionController::class, 'marquerRecu']);
});

// Tous les authentifiés
Route::get('/ordres-mission', [OrdreMissionController::class, 'index']);
Route::get('/ordres-mission/{id}', [OrdreMissionController::class, 'show']);
    // ============================================
    // REGISTRES
    // ============================================


    
    Route::patch('/reservations/{id}/montant', [ReservationController::class, 'updateMontant']);

    // ============================================
    // VOYAGES D'ETUDES
    // ============================================

    Route::get('/voyages/eligibilite', [VoyageEtudeController::class, 'verifierEligibilite']);
    Route::get('/voyages', [VoyageEtudeController::class, 'index']);
    Route::get('/voyages/{id}', [VoyageEtudeController::class, 'show']);

    Route::middleware('role:enseignant')->group(function () {
        Route::post('/voyages', [VoyageEtudeController::class, 'store']);
    });

    Route::middleware('role:vice_recteur')->group(function () {
        Route::patch('/voyages/{id}/approuver', [VoyageEtudeController::class, 'approuver']);
        Route::patch('/voyages/{id}/rejeter', [VoyageEtudeController::class, 'rejeter']);
    });

    // ============================================
    // RAPPORTS
    // ============================================

    Route::get('/rapports', [RapportVoyageController::class, 'index']);
    Route::get('/rapports/{id}', [RapportVoyageController::class, 'show']);

    Route::middleware('role:enseignant')->group(function () {
        Route::post('/rapports', [RapportVoyageController::class, 'store']);
        Route::patch('/rapports/{id}/resoumettre', [RapportVoyageController::class, 'resoumettre']);
    });

    Route::middleware('role:vice_recteur')->group(function () {
        Route::patch('/rapports/{id}/valider', [RapportVoyageController::class, 'valider']);
        Route::patch('/rapports/{id}/rejeter', [RapportVoyageController::class, 'rejeter']);
    });

    // ============================================
    // RECAPITULATIFS
    // ============================================

    Route::get('/recapitulatifs', [RecapitulatifHebdoController::class, 'index']);
    Route::get('/recapitulatifs/{id}', [RecapitulatifHebdoController::class, 'show']);

    Route::middleware('role:sg_vr')->group(function () {
        Route::post('/recapitulatifs/generer', [RecapitulatifHebdoController::class, 'generer']);
        Route::patch('/recapitulatifs/{id}/valider', [RecapitulatifHebdoController::class, 'valider']);
    });
    Route::delete('/ordres-mission/{id}/historique', [OrdreMissionController::class, 'supprimerHistorique']);
       
    Route::post('/ordres-mission/{id}/masquer', [OrdreMissionController::class, 'supprimerHistorique']);
});

