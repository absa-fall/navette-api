<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculeController;
use App\Http\Controllers\OrdreMissionController;
use App\Http\Controllers\VoyageEtudeController;
use App\Http\Controllers\RapportVoyageController;
use App\Http\Controllers\RecapitulatifHebdoController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AutorisationAbsenceController;
use App\Http\Controllers\ArreteVoyageController;

// ============================================
// ROUTES PUBLIQUES
// ============================================

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::post('/reservations', [ReservationController::class, 'store']);
Route::post('/validation/montee', [ReservationController::class, 'validerMontee']);

Route::get('/validation/verifier/{qrCode}', [ReservationController::class, 'verifierQR']);
Route::get('/navettes/prochaines', [OrdreMissionController::class, 'prochainesNavettes']);
Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->middleware('auth:sanctum');
Route::get('/profile/me', [ProfileController::class, 'me'])->middleware('auth:sanctum');
Route::delete('/profile/avatar', [ProfileController::class, 'deleteAvatar'])->middleware('auth:sanctum');
Route::put('/profile/password', [ProfileController::class, 'changePassword'])->middleware('auth:sanctum');
// ============================================
// ROUTES PROTÉGÉES
// ============================================

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/reservations/{id}/statut', [ReservationController::class, 'statut']);
    
    
    Route::post('/reservations', [ReservationController::class, 'store']);

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Scan QR
    Route::post('/scan/bus', [ReservationController::class, 'scannerBus']);
    Route::middleware('role:chauffeur')->group(function () {
        Route::post('/scan/passager', [ReservationController::class, 'scannerPassager']);
    });

    // ============================================
    // RECAPITULATIFS HEBDO
    // ============================================
    Route::middleware('role:sg_vr')->group(function () {
        Route::get('/recapitulatifs', [RecapitulatifHebdoController::class, 'index']);
        Route::get('/recapitulatifs/{id}', [RecapitulatifHebdoController::class, 'show']);
        Route::post('/recapitulatifs/generer', [RecapitulatifHebdoController::class, 'generer']);
        Route::patch('/recapitulatifs/{id}/valider', [RecapitulatifHebdoController::class, 'valider']);
        Route::delete('/recapitulatifs/supprimer-selection', [RecapitulatifHebdoController::class, 'supprimerSelection']);
        Route::get('/reservations/sgvr', [ReservationController::class, 'pourSGVR']);
    });

    // ============================================
    // RAPPORTS DE VOYAGE
    // ============================================
    Route::get('/rapports', [RapportVoyageController::class, 'index']);
    Route::get('/rapports/{id}', [RapportVoyageController::class, 'show']);
    Route::get('/rapports/{id}/download', [RapportVoyageController::class, 'download']);

    Route::middleware('role:enseignant')->group(function () {
        Route::post('/rapports', [RapportVoyageController::class, 'store']);
        
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
Route::middleware('role:admin,ddl')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
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
    Route::middleware('role:ddl')->group(function () {
        Route::post('/vehicules', [VehiculeController::class, 'store']);
        Route::put('/vehicules/{id}', [VehiculeController::class, 'update']);
        Route::delete('/vehicules/{id}', [VehiculeController::class, 'destroy']);
    });

    // ============================================
    // RESERVATIONS
    // ============================================
    Route::get('/reservations/chauffeur', [ReservationController::class, 'pourChauffeur']);
    Route::get('/mes-reservations', [ReservationController::class, 'mesReservations']);
    Route::get('/mes-reservations/export-pdf', [ReservationController::class, 'exporterPdf']);
    Route::delete('/mes-reservations/{id}', [ReservationController::class, 'supprimerMaReservation']);
    Route::patch('/reservations/{id}/montant', [ReservationController::class, 'updateMontant']);
Route::post('/reservations/{id}/annuler', [ReservationController::class, 'annuler']);
    Route::middleware('role:sg_vr,chauffeur,drh,sg_drh,ddl')->group(function () {
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);
    });

   Route::get('/ordres-mission/ma-mission-active', [OrdreMissionController::class, 'maMissionActive']);
Route::get('/ordres-mission/mes-incidents', [OrdreMissionController::class, 'mesIncidents']);
Route::get('/ordres-mission/incidents-en-attente-drh', [OrdreMissionController::class, 'incidentsEnAttenteDrh']);
Route::get('/ordres-mission', [OrdreMissionController::class, 'index']);
Route::delete('/ordres-mission/{id}/historique', [OrdreMissionController::class, 'supprimerHistorique']);
Route::post('/ordres-mission/{id}/masquer', [OrdreMissionController::class, 'supprimerHistorique']);
Route::post('/ordres-mission/{id}/signaler-incident', [OrdreMissionController::class, 'signalerIncident']);
Route::post('/ordres-mission/{id}/transmettre-incident-drh', [OrdreMissionController::class, 'transmettreIncidentDrh']);
Route::post('/ordres-mission/{id}/repondre-incident-ddl', [OrdreMissionController::class, 'repondreIncidentDdl']);
Route::get('/ordres-mission/{id}', [OrdreMissionController::class, 'show']);

    Route::middleware('role:ddl')->group(function () {
        Route::post('/ordres-mission', [OrdreMissionController::class, 'store']);
        Route::post('/ordres-mission/{id}/transmettre', [OrdreMissionController::class, 'transmettre']);
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
    Route::post('/reservations/{id}/reactiver', [ReservationController::class, 'reactiver']); 
    Route::post('/reservations/{id}/annuler-chauffeur', [ReservationController::class, 'annulerChauffeur']);
});

    // ============================================
    // VOYAGES D'ÉTUDES
    
    // ============================================

    Route::get('/voyages/eligibilite', [VoyageEtudeController::class, 'verifierEligibilite']);

    // Autorisations d'absence — lecture accessible à tous les authentifiés
    Route::get('/autorisations-absence', [AutorisationAbsenceController::class, 'index']);
    Route::get('/autorisations-absence/{id}', [AutorisationAbsenceController::class, 'show']);

    // Arrêtés — lecture accessible à tous
    Route::get('/arretes/{id}', [ArreteVoyageController::class, 'show']);
   Route::middleware('role:recteur,vice_recteur,enseignant,chef_departement,directeur_ufr')->group(function () {
    Route::get('/voyages-etudes/{voyageId}/arrete', [ArreteVoyageController::class, 'showByVoyage']);
});

  Route::middleware('role:enseignant')->group(function () {
    Route::get('/mes-voyages-etudes', [VoyageEtudeController::class, 'mesVoyages']);
    Route::post('/voyages-etudes/beneficiaire/{id}/justificatifs', [VoyageEtudeController::class, 'soumettreJustificatifs']);

    Route::patch('/voyages-etudes/beneficiaire/{id}/demander-autorisation', [VoyageEtudeController::class, 'demanderAutorisation']);
    Route::post('/voyages-etudes/beneficiaire/{id}/autorisation-absence', [AutorisationAbsenceController::class, 'store']);
    Route::patch('/voyages-etudes/beneficiaire/{id}/masquer', [VoyageEtudeController::class, 'masquerVoyage']);
    Route::patch('/autorisations-absence/{id}/signer', [AutorisationAbsenceController::class, 'signer']);
    Route::patch('/autorisations-absence/{id}/transmettre-vers-chef', [AutorisationAbsenceController::class, 'transmettreVersChefDepartement']);
});
    // --- CHEF DE DÉPARTEMENT ---
    Route::middleware('role:chef_departement')->group(function () {
        Route::post('/voyages-etudes/{id}/notifier-enseignants', [VoyageEtudeController::class, 'notifierEnseignants']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/envoyer-vr', [VoyageEtudeController::class, 'envoyerAuVR']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/autorisation-sortie', [VoyageEtudeController::class, 'autorisationSortie']);
        Route::get('/voyages-etudes/{id}/beneficiaires', [VoyageEtudeController::class, 'beneficiaires']);
        Route::patch('/autorisations-absence/{id}/avis-chef-departement', [AutorisationAbsenceController::class, 'avisChefDepartement']);
    });
// --- SUPPRESSION HISTORIQUE AUTORISATION (multi-rôles) ---
Route::middleware('role:chef_departement,directeur_ufr,recteur,enseignant')->group(function () {
    Route::delete('/autorisations-absence/{id}', [AutorisationAbsenceController::class, 'destroy']);
});
    // --- DIRECTEUR UFR ---
    Route::middleware('role:directeur_ufr')->group(function () {
        Route::patch('/voyages-etudes/beneficiaire/{id}/envoyer-autorisation-recteur', [VoyageEtudeController::class, 'envoyerAutorisationRecteur']);
        Route::patch('/autorisations-absence/{id}/avis-directeur-ufr', [AutorisationAbsenceController::class, 'avisDirecteurUfr']);
    });

    // --- CHEF DÉPARTEMENT + DIRECTEUR UFR + RECTEUR ---
    
    Route::middleware('role:chef_departement,directeur_ufr,recteur')->group(function () {
        Route::get('/voyages-etudes/dossiers-departement', [VoyageEtudeController::class, 'dossiersDepartement']);
    });

    
Route::middleware('role:vice_recteur,commission')->group(function () {
    Route::get('/voyages-etudes/dossiers-a-valider', [VoyageEtudeController::class, 'dossiersAValider']);
    Route::patch('/voyages-etudes/beneficiaire/{id}/avis', [VoyageEtudeController::class, 'donnerAvis']);
    Route::get('/voyages-etudes/listes-publiees', [VoyageEtudeController::class, 'listesPubliees']);
});
    // --- VOIR AUTORISATION SORTIE (multi-rôles) ---
    Route::middleware('role:enseignant,chef_departement,directeur_ufr,recteur,vice_recteur')->group(function () {
        Route::get('/voyages-etudes/beneficiaire/{id}/autorisation-sortie', [VoyageEtudeController::class, 'voirAutorisationSortie']);
    });

    // --- RECTEUR + VICE-RECTEUR ---
    Route::middleware('role:recteur,vice_recteur')->group(function () {
        Route::get('/voyages-etudes', [VoyageEtudeController::class, 'index']);
    });

    // --- SUPPRESSION voyage et dossier — chef_departement + recteur + vice_recteur ---
    Route::middleware('role:chef_departement,recteur,vice_recteur,commission')->group(function () {
        Route::delete('/voyages-etudes/beneficiaire/{id}/dossier', [VoyageEtudeController::class, 'destroyBeneficiaire']);
        Route::delete('/voyages-etudes/{id}', [VoyageEtudeController::class, 'destroy']);
    });

    // --- RECTEUR ---
    Route::middleware('role:recteur')->group(function () {
        Route::post('/voyages-etudes/{id}/arrete', [ArreteVoyageController::class, 'store']);
        Route::patch('/voyages-etudes/beneficiaire/{id}/approuver-autorisation-recteur', [VoyageEtudeController::class, 'approuverAutorisationRecteur']);
        Route::patch('/autorisations-absence/{id}/signer-recteur', [AutorisationAbsenceController::class, 'signerRecteur']);
        Route::post('/autorisations-absence/{id}/envoyer-email', [AutorisationAbsenceController::class, 'envoyerEmail']);
        Route::get('/arretes', [ArreteVoyageController::class, 'mesArretes']);
        Route::delete('/arretes/{id}', [ArreteVoyageController::class, 'destroy']);
    });

   // --- VICE-RECTEUR SEUL ---
    Route::middleware('role:vice_recteur')->group(function () {
        Route::post('/voyages-etudes', [VoyageEtudeController::class, 'publierListe']);
        Route::patch('/voyages-etudes/{id}/transmettre', [VoyageEtudeController::class, 'transmettreListe']);
        Route::post('/voyages-etudes/{id}/ajouter-beneficiaire', [VoyageEtudeController::class, 'ajouterBeneficiaire']);
        Route::post('/voyages-etudes/{id}/liste-definitive', [VoyageEtudeController::class, 'publierListeDefinitive']);
        Route::post('/voyages-etudes/{id}/notifier-beneficiaires', [VoyageEtudeController::class, 'notifierBeneficiairesDefinitifs']);
        Route::patch('/autorisations-absence/{id}/transmettre-enseignant', [AutorisationAbsenceController::class, 'transmettreEnseignant']);
        Route::post('/arretes/{id}/envoyer-emails', [ArreteVoyageController::class, 'envoyerEmails']);
    });

    // --- VOIR UN VOYAGE (détail) — VR + Commission + Chef Département ---
    Route::middleware('role:vice_recteur,commission,chef_departement')->group(function () {
        Route::get('/voyages-etudes/{id}', [VoyageEtudeController::class, 'show']);
    });

});