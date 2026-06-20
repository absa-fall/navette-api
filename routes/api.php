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

    // Scan QR
    Route::post('/scan/bus', [ReservationController::class, 'scannerBus']);
    Route::middleware('role:chauffeur')->group(function () {
        Route::post('/scan/passager', [ReservationController::class, 'scannerPassager']);
    });

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // ============================================
    // RAPPORTS DE VOYAGE
    // ============================================
    Route::get('/rapports', [RapportVoyageController::class, 'index']);
    Route::get('/rapports/{id}', [RapportVoyageController::class, 'show']);
    Route::get('/rapports/{id}/download', [RapportVoyageController::class, 'download']);

    Route::middleware('role:enseignant')->group(function () {
        Route::post('/rapports', [RapportVoyageController::class, 'store']);
        Route::post('/rapports/{id}/resoumettre', [RapportVoyageController::class, 'resoumettre']);
    });

    Route::middleware('role:vice_recteur')->group(function () {
        Route::patch('/rapports/{id}/valider', [RapportVoyageController::class, 'valider']);
        Route::patch('/rapports/{id}/rejeter', [RapportVoyageController::class, 'rejeter']);
    });

    // ============================================
    // NOTIFICATIONS
    // ============================================
    Route::get('/notifications/sidebar', [NotificationController::class, 'sidebar']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications', [NotificationController::class, 'store']);
    Route::patch('/notifications/lu-toutes', [NotificationController::class, 'marquerToutesLues']);
    Route::delete('/notifications/toutes', [NotificationController::class, 'supprimerToutes']);
    Route::patch('/notifications/{id}/lu', [NotificationController::class, 'marquerLu']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // ============================================
    // USERS (admin)
    // ============================================
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    });

    Route::get('/chauffeurs', [UserController::class, 'chauffeurs']);
    Route::get('/drhs', [UserController::class, 'drhs']);
    Route::get('/enseignants-permanents', [UserController::class, 'enseignantsPermanents']);

    // ============================================
    // VEHICULES
    // ============================================
    Route::get('/vehicules/disponibles', [VehiculeController::class, 'disponibles']);
    Route::get('/vehicules', [VehiculeController::class, 'index']);
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

    Route::get('/mes-reservations', [ReservationController::class, 'mesReservations']);
    Route::delete('/mes-reservations/{id}', [ReservationController::class, 'supprimerMaReservation']);
    Route::patch('/reservations/{id}/montant', [ReservationController::class, 'updateMontant']);

    // ============================================
    // ORDRES DE MISSION
    // ============================================
    Route::middleware('role:ddl')->group(function () {
        Route::post('/ordres-mission', [OrdreMissionController::class, 'store']);
        Route::get('/mes-ordres', [OrdreMissionController::class, 'mesOrdres']);
        Route::delete('/ordres-mission/{id}', [OrdreMissionController::class, 'destroy']);
        Route::put('/ordres-mission/{id}', [OrdreMissionController::class, 'update']);
    });

    Route::middleware('role:drh')->group(function () {
        Route::patch('/ordres-mission/{id}/approuver-drh', [OrdreMissionController::class, 'approuverDRH']);
        Route::patch('/ordres-mission/{id}/rejeter-drh', [OrdreMissionController::class, 'rejeterDRH']);
    });

    Route::middleware('role:sg_drh')->group(function () {
        Route::get('/ordres-mission-a-signer', [OrdreMissionController::class, 'aSigner']);
        Route::patch('/ordres-mission/{id}/signer', [OrdreMissionController::class, 'signer']);
    });

    Route::middleware('role:chauffeur')->group(function () {
        Route::get('/ordres-mission-chauffeur', [OrdreMissionController::class, 'pourChauffeur']);
        Route::post('/ordres-mission/{id}/accepter', [OrdreMissionController::class, 'accepterMission']);
        Route::post('/ordres-mission/{id}/refuser', [OrdreMissionController::class, 'refuserMission']);
        Route::patch('/ordres-mission/{id}/marquer-recu', [OrdreMissionController::class, 'marquerRecu']);
        Route::patch('/reservations/{id}/confirmer', [ReservationController::class, 'confirmer']);
        Route::patch('/reservations/{id}/refuser', [ReservationController::class, 'refuser']);
    });

    Route::get('/ordres-mission', [OrdreMissionController::class, 'index']);
    Route::get('/ordres-mission/{id}', [OrdreMissionController::class, 'show']);
    Route::delete('/ordres-mission/{id}/historique', [OrdreMissionController::class, 'supprimerHistorique']);
    Route::post('/ordres-mission/{id}/masquer', [OrdreMissionController::class, 'supprimerHistorique']);

    // ============================================
    // VOYAGES D'ÉTUDES — ordre critique 
    // ============================================

    // Éligibilité — tous les authentifiés
    Route::get('/voyages/eligibilite', [VoyageEtudeController::class, 'verifierEligibilite']);

    // --- ENSEIGNANT ---
    Route::middleware('role:enseignant')->group(function () {
        Route::get('/mes-voyages-etudes', [VoyageEtudeController::class, 'mesVoyages']);
        Route::post('/voyages-etudes/beneficiaire/{id}/justificatifs', [VoyageEtudeController::class, 'soumettreJustificatifs']);
        Route::post('/voyages-etudes/beneficiaire/{id}/justificatif-rapport', [VoyageEtudeController::class, 'justificatifDepuisRapport']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/demander-autorisation', [VoyageEtudeController::class, 'demanderAutorisation']);
    });

    // --- CHEF DE DÉPARTEMENT + DIRECTEUR UFR — route partagée ---
    Route::middleware('role:chef_departement,directeur_ufr,recteur')->group(function () {
        Route::get('/voyages-etudes/dossiers-departement', [VoyageEtudeController::class, 'dossiersDepartement']);
    });

    // --- CHEF DE DÉPARTEMENT uniquement ---
    Route::middleware('role:chef_departement')->group(function () {
        Route::post('/voyages-etudes/{id}/notifier-enseignants', [VoyageEtudeController::class, 'notifierEnseignants']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/envoyer-vr', [VoyageEtudeController::class, 'envoyerAuVR']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/autorisation-sortie', [VoyageEtudeController::class, 'autorisationSortie']);
    });

    // --- DIRECTEUR UFR uniquement ---
    Route::middleware('role:directeur_ufr')->group(function () {
        Route::patch('/voyages-etudes/beneficiaire/{id}/envoyer-autorisation-recteur', [VoyageEtudeController::class, 'envoyerAutorisationRecteur']);
    });

    // --- VR + COMMISSION — dossiers à valider (AVANT le groupe VR seul) ---
    Route::middleware('role:vice_recteur,commission')->group(function () {
        Route::get('/voyages-etudes/dossiers-a-valider', [VoyageEtudeController::class, 'dossiersAValider']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/avis', [VoyageEtudeController::class, 'donnerAvis']);
    });

    // --- RECTEUR + VICE-RECTEUR — route partagée index ---
    Route::middleware('role:recteur,vice_recteur')->group(function () {
        Route::get('/voyages-etudes', [VoyageEtudeController::class, 'index']);
    });

    // --- RECTEUR ---
    Route::middleware('role:recteur')->group(function () {
        Route::patch('/voyages-etudes/{id}/signer-arrete', [VoyageEtudeController::class, 'signerArrete']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/approuver-autorisation-recteur', [VoyageEtudeController::class, 'approuverAutorisationRecteur']);
    });

    // --- VICE-RECTEUR SEUL — routes avec {id} EN DERNIER pour éviter conflits ---
    Route::middleware('role:vice_recteur')->group(function () {
        Route::post('/voyages-etudes', [VoyageEtudeController::class, 'publierListe']);
        Route::post('/voyages-etudes/{id}/ajouter-beneficiaire', [VoyageEtudeController::class, 'ajouterBeneficiaire']);
        Route::post('/voyages-etudes/{id}/liste-definitive', [VoyageEtudeController::class, 'publierListeDefinitive']);
        Route::post('/voyages-etudes/{id}/notifier-beneficiaires', [VoyageEtudeController::class, 'notifierBeneficiairesDefinitifs']);
        Route::get('/voyages-etudes/{id}', [VoyageEtudeController::class, 'show']);
    });

});