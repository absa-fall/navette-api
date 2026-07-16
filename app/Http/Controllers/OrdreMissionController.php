<?php

namespace App\Http\Controllers;

use App\Models\OrdreMission;
use App\Models\Vehicule;
use App\Models\User;
use App\Models\Notification;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdreMissionController extends Controller
{
    // DDL enregistre une demande en BROUILLON (aucune notification envoyée ici)
    public function store(Request $request)
    {
        $request->validate([
            'chauffeur_id' => 'required|exists:users,id',
            'chauffeur_nom' => 'required|string|max:100',
            'chauffeur_prenom' => 'required|string|max:100',
            'nationalite' => 'nullable|string|max:50',
            'grade_fonction' => 'nullable|string|max:100',
            'destination' => 'required|string|max:200',
            'objet_mission' => 'nullable|string|max:200',
            'moyen_transport' => 'nullable|string|max:200',
            'vehicule_id' => 'nullable|exists:vehicules,id',
            'date_depart' => 'required|date',
            'heure_depart' => 'nullable|string', 
          'date_retour' => 'required|date|after_or_equal:date_depart',
            'frais_transport' => 'nullable|string|max:200',
            'indemnite_deplacement' => 'nullable|string|max:200',
            'trajet' => 'nullable|in:dakar_bambey,thies_bambey,bambey_ngouniane,autres',
            'trajet_autre' => 'nullable|string|max:200',
            'motif' => 'nullable|string',
        ]);

        $montant = $request->trajet ? OrdreMission::getMontantTrajet($request->trajet) : 0;

        $ordre = OrdreMission::create([
            'ddl_id' => auth()->id(),
            'chauffeur_id' => $request->chauffeur_id,
            'chauffeur_nom' => $request->chauffeur_nom,
            'chauffeur_prenom' => $request->chauffeur_prenom,
            'nationalite' => $request->nationalite ?? 'Sénégalaise',
            'grade_fonction' => $request->grade_fonction ?? 'Chauffeur',
            'destination' => $request->destination,
            'objet_mission' => $request->objet_mission ?? 'conduit la navette de l\'UAD',
            'moyen_transport' => $request->moyen_transport,
            'vehicule_id' => $request->vehicule_id,
            'date_depart' => $request->date_depart,
            'heure_depart' => $request->heure_depart ?? '06:00', 
            'date_retour' => $request->date_retour,
            'frais_transport' => $request->frais_transport ?? 'Appui en carburant',
            'indemnite_deplacement' => $request->indemnite_deplacement ?? 'Néant',
            'trajet' => $request->trajet,
            'trajet_autre' => $request->trajet_autre,
            'montant_trajet' => $montant,
            'motif' => $request->motif,
            'statut' => 'brouillon',
        ]);

        // NOTE : aucune notification au DRH ici — elle part uniquement à la transmission (voir transmettre())

        return response()->json([
            'message' => 'Brouillon enregistré',
            'ordre' => $ordre
        ], 201);
    }

    // DDL transmet un brouillon (ou une demande rejetée) au DRH — seule cette étape notifie le DRH
    public function transmettre($id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->ddl_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($ordre->statut, ['brouillon', 'rejete'])) {
            return response()->json(['message' => 'Cette demande a déjà été transmise'], 403);
        }

        $ordre->update([
            'statut' => 'en_attente_drh',
            'commentaire_rejet' => null,
            'drh_id' => null,
        ]);

        // Notifier le DRH qu'une nouvelle demande est arrivée
        $drh = User::where('role', 'drh')->first();
        if ($drh) {
            Notification::create([
                'user_id' => $drh->id,
                'type' => 'nouvelle_demande',
                'titre' => 'Nouvelle demande d\'ordre de mission',
                'message' => "Une nouvelle demande d'ordre de mission pour {$ordre->destination} a été soumise par le DDL.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Demande transmise au DRH avec succès',
            'ordre' => $ordre
        ]);
    }
// Chauffeur signale un incident (a tout moment pendant sa mission)
public function signalerIncident(Request $request, $id)
{
    $request->validate([
        'motif' => 'required|string|max:1000',
    ]);

    $ordre = OrdreMission::findOrFail($id);

    if ($ordre->chauffeur_id !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    if ($ordre->statut_chauffeur !== 'accepte') {
        return response()->json(['message' => 'Aucune mission en cours pour signaler un incident'], 403);
    }

    $chauffeur = auth()->user();

    $ordre->update([
        'incident' => true,
        'incident_motif' => $request->motif,
        'incident_date' => now(),
        'incident_transmis_drh' => false,
        'incident_repondu_drh' => false,
        'reponse_drh' => null,
        'reponse_drh_date' => null,
        'statut' => 'incident',
    ]);

    if ($ordre->vehicule_id) {
        Vehicule::where('id', $ordre->vehicule_id)->update(['etat' => 'en_panne']);
    }

    if ($ordre->ddl_id) {
        Notification::create([
            'user_id' => $ordre->ddl_id,
            'type' => 'incident_mission',
            'titre' => 'Incident signalé par le chauffeur',
            'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a signalé un incident sur la mission {$ordre->destination} : {$request->motif}",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }

    $sgVr = User::where('role', 'sg_vr')->first();
    if ($sgVr) {
        Notification::create([
            'user_id' => $sgVr->id,
            'type' => 'incident_mission',
            'titre' => 'Incident sur une mission',
            'message' => "Incident signalé sur la mission {$ordre->destination} (chauffeur {$chauffeur->prenom} {$chauffeur->nom}) : {$request->motif}",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }

    Notification::create([
        'user_id' => $chauffeur->id,
        'type' => 'incident_recu',
        'titre' => 'Incident reçu',
        'message' => "Votre signalement d'incident pour la mission {$ordre->destination} a bien été reçu par le DDL. Il va être transmis au DRH.",
        'ordre_id' => $ordre->id,
        'lu' => false,
    ]);

    // ✅ NOUVEAU : Notifier tous les passagers ayant une réservation active
    // (aller et/ou retour) pour cette mission, afin qu'ils sachent que le
    // bus est immobilisé et que leur trajet est impacté.
    $passagersIds = Reservation::whereDate('date_reservation', $ordre->date_depart)
        ->whereIn('statut', ['en_attente_confirmation', 'confirmee', 'en_cours'])
        ->pluck('user_id')
        ->unique();

    foreach ($passagersIds as $passagerId) {
        Notification::create([
            'user_id' => $passagerId,
            'type'    => 'incident_mission_passager',
            'titre'   => 'Incident sur votre navette',
            'message' => "Le bus prévu vers {$ordre->destination} a rencontré un incident. Votre trajet pourrait être retardé ou annulé. Motif : {$request->motif}",
            'ordre_id'=> $ordre->id,
            'lu'      => false,
        ]);
    }

    return response()->json([
        'message' => 'Incident signalé. Le DDL, le SG VR et les passagers ont été notifiés.',
        'ordre' => $ordre,
    ]);
}
// DDL voit les incidents de ses missions, pas encore transmis au DRH
public function mesIncidents()
{
    $incidents = OrdreMission::where('ddl_id', auth()->id())
        ->where('incident', true)
        ->where('incident_transmis_drh', false)
        ->with(['chauffeur', 'vehicule'])
        ->orderByDesc('incident_date')
        ->get();

    return response()->json($incidents);
}

public function transmettreIncidentDrh($id)
{
    $ordre = OrdreMission::findOrFail($id);

    if ($ordre->ddl_id !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    if (!$ordre->incident) {
        return response()->json(['message' => 'Aucun incident à transmettre'], 403);
    }

    $ordre->update(['incident_transmis_drh' => true]);

    $drh = User::where('role', 'drh')->first();
    if ($drh) {
        Notification::create([
            'user_id' => $drh->id,
            'type' => 'incident_mission',
            'titre' => 'Incident transmis par le DDL',
            'message' => "Le DDL a transmis un incident sur la mission {$ordre->destination} : {$ordre->incident_motif}. Une réponse est nécessaire pour permettre au DDL de rédiger un nouvel ordre.",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }

    // Confirmation au DDL lui-meme
    Notification::create([
        'user_id' => $ordre->ddl_id,
        'type' => 'incident_transmis_confirmation',
        'titre' => 'Incident transmis au DRH',
        'message' => "Votre signalement d'incident pour la mission {$ordre->destination} a bien été transmis au DRH. Vous serez notifié dès sa réponse.",
        'ordre_id' => $ordre->id,
        'lu' => false,
    ]);

    return response()->json([
        'message' => 'Incident transmis au DRH',
        'ordre' => $ordre,
    ]);
}
public function maMissionActive()
{
    $ordre = OrdreMission::where('chauffeur_id', auth()->id())
        ->where('statut_chauffeur', 'accepte')
        ->whereNotIn('statut', ['execute'])
        ->with('vehicule')
        ->orderBy('date_depart')
        ->first();

    return response()->json($ordre);
}

// Incidents transmis au DRH, en attente de reponse
public function incidentsEnAttenteDrh()
{
    $incidents = OrdreMission::where('incident_transmis_drh', true)
        ->where('incident_repondu_drh', false)
        ->with(['chauffeur', 'ddl'])
        ->orderByDesc('incident_date')
        ->get();

    return response()->json($incidents);
}

public function repondreIncidentDdl(Request $request, $id)
{
    $request->validate([
        'message' => 'required|string|max:1000',
    ]);

    $ordre = OrdreMission::findOrFail($id);

    if (!$ordre->incident || !$ordre->incident_transmis_drh) {
        return response()->json(['message' => 'Aucun incident transmis pour cet ordre'], 403);
    }

   $ordre->update([
    'incident_repondu_drh' => true,
    'reponse_drh' => $request->message,
    'reponse_drh_date' => now(),
    'statut' => 'transmis_chauffeur',  
]);

    // Le vehicule redevient disponible automatiquement
    if ($ordre->vehicule_id) {
        Vehicule::where('id', $ordre->vehicule_id)->update(['etat' => 'disponible']);
    }

    if ($ordre->ddl_id) {
        Notification::create([
            'user_id' => $ordre->ddl_id,
            'type' => 'reponse_drh_incident',
            'titre' => 'Réponse du DRH concernant l\'incident',
            'message' => "Concernant l'incident sur la mission {$ordre->destination} : {$request->message}",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }
if ($ordre->chauffeur_id) {
        Notification::create([
            'user_id' => $ordre->chauffeur_id,
            'type' => 'reponse_drh_incident',
            'titre' => 'Réponse du DRH à votre incident',
            'message' => "Le DRH a répondu à votre signalement pour la mission {$ordre->destination} : {$request->message}",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }
     return response()->json([
        'message' => 'Réponse envoyée au DDL',
        'ordre' => $ordre,
    ]);
}
    // Liste des ordres selon le rôle avec filtre par statut
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = match($user->role) {
            'ddl' => OrdreMission::where('ddl_id', $user->id)
                ->where('masque_ddl', false),
            
            'drh' => OrdreMission::where('masque_drh', false),
            
            'sg_drh' => OrdreMission::where('masque_sg_drh', false),
            
            'chauffeur' => OrdreMission::where('chauffeur_id', $user->id)
                ->where('masque_chauffeur', false),
            'admin' => OrdreMission::query()->where('masque_admin', false),
            
            
            default => null
        };

        if (!$query) {
            return response()->json([]);
        }

        // FILTRE PAR STATUT si présent dans la requête
        if ($request->has('statut') && $request->statut) {
            $query->where('statut', $request->statut);
        }

        $ordres = $query->with(['ddl', 'vehicule', 'chauffeur', 'sgDrh'])
            ->latest()
            ->get();

        return response()->json($ordres);
    }

    // Voir un ordre
    public function show($id)
    {
        $ordre = OrdreMission::with(['ddl', 'sgDrh', 'chauffeur', 'vehicule'])->findOrFail($id);
        return response()->json($ordre);
    }

    // DRH approuve
    public function approuverDRH(Request $request, $id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->statut !== 'en_attente_drh') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $ordre->update([
            'statut' => 'approuve_drh',
            'drh_id' => auth()->id(),
        ]);

        // Notifier le SG DRH
        $sgDrh = User::where('role', 'sg_drh')->first();
        if ($sgDrh) {
            Notification::create([
                'user_id' => $sgDrh->id,
                'type' => 'demande_approuvee_drh',
                'titre' => 'Ordre approuvé par le DRH',
                'message' => "L'ordre de mission pour {$ordre->destination} a été approuvé par le DRH et est prêt à être signé.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        // Notifier le DDL
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'demande_approuvee_drh',
                'titre' => 'Votre demande a été approuvée',
                'message' => "Votre demande d'ordre de mission pour {$ordre->destination} a été approuvée par le DRH. En attente de signature du SG DRH.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Ordre approuvé et transmis au SG DRH',
            'ordre' => $ordre
        ]);
    }

    // DRH rejette
    public function rejeterDRH(Request $request, $id)
    {
        $request->validate([
            'commentaire_rejet' => 'required|string',
        ]);

        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->statut !== 'en_attente_drh') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $ordre->update([
            'statut' => 'rejete',
            'drh_id' => auth()->id(),
            'commentaire_rejet' => $request->commentaire_rejet,
        ]);

        // Notifier le DDL du rejet
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'demande_rejetee_drh',
                'titre' => 'Votre demande a été rejetée',
                'message' => "Votre demande d'ordre de mission pour {$ordre->destination} a été rejetée par le DRH. Motif : {$request->commentaire_rejet}",
                'ordre_id' => $ordre->id,
                'motif_refus' => $request->commentaire_rejet,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Ordre rejeté',
            'ordre' => $ordre
        ]);
    }

    // SG DRH signe et assigne chauffeur
    public function signer(Request $request, $id)
    {
        $request->validate([
            'chauffeur_id' => 'nullable|exists:users,id',
        ]);

        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->statut !== 'approuve_drh') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $chauffeurId = $request->chauffeur_id;
        if (!$chauffeurId) {
            $chauffeur = User::where('role', 'chauffeur')
                ->where('is_active', true)
                ->first();
            $chauffeurId = $chauffeur ? $chauffeur->id : null;
        }

        $ordre->update([
            'statut' => 'transmis_chauffeur',
            'statut_chauffeur' => 'en_attente',
            'sg_drh_id' => auth()->id(),
            'signature_sg_drh' => true,
            'date_signature' => now(),
            'chauffeur_id' => $chauffeurId,
        ]);

        // Notifier le chauffeur
        if ($chauffeurId) {
            Notification::create([
                'user_id' => $chauffeurId,
                'type' => 'nouvelle_mission',
                'titre' => 'Nouvelle mission assignée',
                'message' => "Une nouvelle mission vous a été assignée pour {$ordre->destination} le " . 
                    \Carbon\Carbon::parse($ordre->date_depart)->format('d/m/Y') . 
                    ". Veuillez approuver ou refuser.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        // Notifier le DDL que c'est signé
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'mission_signee',
                'titre' => 'Ordre de mission signé',
                'message' => "Votre ordre de mission pour {$ordre->destination} a été signé par le SG DRH et transmis au chauffeur.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Ordre signé et transmis au chauffeur',
            'ordre' => $ordre
        ]);
    }

   public function pourChauffeur()
{
    $ordres = OrdreMission::where('chauffeur_id', auth()->id())
        ->where('masque_chauffeur', false)
        ->whereIn('statut', ['transmis_chauffeur', 'execute'])
        ->with(['ddl', 'vehicule'])
        ->latest()
        ->get();

    return response()->json($ordres);
}
    // Chauffeur marque comme exécuté
    public function marquerRecu($id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->chauffeur_id !== auth()->id()) {
            return response()->json(['message' => 'Ce trajet ne vous est pas assigné'], 403);
        }

        if ($ordre->statut !== 'transmis_chauffeur') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        if ($ordre->statut_chauffeur !== 'accepte') {
            $statutLabel = match($ordre->statut_chauffeur) {
                'refuse' => 'refusée',
                'en_attente', null => 'en attente',
                default => 'non approuvée'
            };
            
            return response()->json([
                'message' => "Impossible d'exécuter : la mission a été {$statutLabel}. Vous devez d'abord approuver la mission."
            ], 403);
        }

        $ordre->update(['statut' => 'execute']);

        // Notifier le DDL que c'est exécuté
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'mission_executee',
                'titre' => 'Mission exécutée',
                'message' => "L'ordre de mission pour {$ordre->destination} a été marqué comme exécuté par le chauffeur.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        // Notifier le DRH
        $drh = User::where('role', 'drh')->first();
        if ($drh) {
            Notification::create([
                'user_id' => $drh->id,
                'type' => 'mission_executee',
                'titre' => 'Mission exécutée',
                'message' => "L'ordre de mission pour {$ordre->destination} a été exécuté.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Trajet marqué comme exécuté',
            'ordre' => $ordre
        ]);
    }
   
    // Chauffeur approuve la mission
    public function accepterMission($id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->chauffeur_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($ordre->statut !== 'transmis_chauffeur') {
            return response()->json(['message' => 'Mission non disponible'], 403);
        }

        $chauffeur = auth()->user();

        $ordre->update([
            'statut_chauffeur' => 'accepte',
        ]);

        // Notifier le DDL que le chauffeur a approuvé
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'approbation_chauffeur',
                'titre' => 'Ordre de mission approuvé par le chauffeur',
                'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a approuvé l'ordre de mission pour {$ordre->destination}. La mission peut maintenant être exécutée.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }

        // Notifier le SG DRH
        $sgDrh = User::where('role', 'sg_drh')->first();
        if ($sgDrh) {
            Notification::create([
                'user_id' => $sgDrh->id,
                'type' => 'approbation_chauffeur',
                'titre' => 'Mission approuvée par le chauffeur',
                'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a approuvé la mission pour {$ordre->destination}.",
                'ordre_id' => $ordre->id,
                'lu' => false,
            ]);
        }
 $passagers = User::whereIn('role', ['enseignant', 'usager'])->get();
    $dateDepart = \Carbon\Carbon::parse($ordre->date_depart)->format('d/m/Y');
    foreach ($passagers as $passager) {
        Notification::create([
            'user_id' => $passager->id,
            'type' => 'navette_disponible',
            'titre' => 'Navette confirmée',
            'message' => "Une navette est prévue le {$dateDepart} à {$ordre->heure_depart} vers {$ordre->destination}. Vous pouvez réserver votre place.",
            'ordre_id' => $ordre->id,
            'lu' => false,
        ]);
    }
        return response()->json([
            'message' => 'Mission approuvée par le chauffeur. Le DDL a été notifié.'
        ]);
    }

    // Chauffeur refuse la mission
    public function refuserMission(Request $request, $id)
    {
        $request->validate([
            'motif_refus' => 'required|string|max:1000'
        ]);

        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->chauffeur_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($ordre->statut !== 'transmis_chauffeur') {
            return response()->json(['message' => 'Mission non disponible'], 403);
        }

        $chauffeur = auth()->user();

        $ordre->update([
            'statut_chauffeur' => 'refuse',
            'motif_refus' => $request->motif_refus,
        ]);

        // Notifier le DDL
        if ($ordre->ddl_id) {
            Notification::create([
                'user_id' => $ordre->ddl_id,
                'type' => 'refus_chauffeur',
                'titre' => 'Ordre de mission refusé par le chauffeur',
                'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a refusé l'ordre de mission pour {$ordre->destination}. Motif : {$request->motif_refus}",
                'ordre_id' => $ordre->id,
                'motif_refus' => $request->motif_refus,
                'lu' => false,
            ]);
        }

        // Notifier le DRH pour réassignation
        $drh = User::where('role', 'drh')->first();
        if ($drh) {
            Notification::create([
                'user_id' => $drh->id,
                'type' => 'refus_chauffeur',
                'titre' => 'Chauffeur a refusé une mission',
                'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a refusé la mission pour {$ordre->destination}. Motif : {$request->motif_refus}. Un nouveau chauffeur doit être assigné.",
                'ordre_id' => $ordre->id,
                'motif_refus' => $request->motif_refus,
                'lu' => false,
            ]);
        }

        // Notifier le SG DRH
        $sgDrh = User::where('role', 'sg_drh')->first();
        if ($sgDrh) {
            Notification::create([
                'user_id' => $sgDrh->id,
                'type' => 'refus_chauffeur',
                'titre' => 'Mission refusée par le chauffeur',
                'message' => "Le chauffeur {$chauffeur->prenom} {$chauffeur->nom} a refusé la mission pour {$ordre->destination}. Motif : {$request->motif_refus}.",
                'ordre_id' => $ordre->id,
                'motif_refus' => $request->motif_refus,
                'lu' => false,
            ]);
        }

        return response()->json([
            'message' => 'Refus enregistré. Le DDL, le DRH et le SG DRH ont été notifiés.'
        ]);
    }

    // DDL voit ses ordres
    public function mesOrdres()
    {
        $ordres = OrdreMission::where('ddl_id', auth()->id())
            ->with(['vehicule', 'chauffeur'])
            ->latest()
            ->get();

        return response()->json($ordres);
    }

    // SG DRH voit les ordres à signer
    public function aSigner()
    {
        $ordres = OrdreMission::where('statut', 'approuve_drh')
            ->with(['ddl', 'vehicule'])
            ->latest()
            ->get();

        return response()->json($ordres);
    }

    // Modifier un ordre (brouillon, en attente DRH, ou rejeté)
    public function update(Request $request, $id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->ddl_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($ordre->statut, ['brouillon', 'rejete'])) {
            return response()->json(['message' => 'Impossible de modifier un ordre déjà traité'], 403);
        }

        // On ignore un éventuel "statut" envoyé par le client : le statut est géré ici, pas par le frontend
        $ancienStatut = $ordre->statut;
        $data = $request->except(['statut']);

        if ($ancienStatut === 'rejete') {
            // Comportement inchangé : modifier une demande rejetée la renvoie directement au DRH
            $data['statut'] = 'en_attente_drh';
            $data['commentaire_rejet'] = null;
            $data['drh_id'] = null;
        }
        // Si 'brouillon' ou 'en_attente_drh' : le statut ne change pas, pas de notification

        $ordre->update($data);

        if ($ancienStatut === 'rejete') {
            $drh = User::where('role', 'drh')->first();
            if ($drh) {
                Notification::create([
                    'user_id' => $drh->id,
                    'type' => 'demande_modifiee',
                    'titre' => 'Demande d\'ordre modifiée',
                    'message' => "Une demande d'ordre de mission pour {$ordre->destination} a été modifiée et renvoyée pour approbation.",
                    'ordre_id' => $ordre->id,
                    'lu' => false,
                ]);
            }

            return response()->json([
                'message' => 'Ordre modifié avec succès. Il a été renvoyé au DRH pour approbation.',
                'ordre' => $ordre
            ]);
        }

        return response()->json([
            'message' => 'Ordre mis à jour',
            'ordre' => $ordre
        ]);
    }

    public function destroy($id)
{
    $ordre = OrdreMission::findOrFail($id);
    $user = auth()->user();

    // Admin : masquage uniquement, jamais de suppression réelle
    if ($user->role === 'admin') {
        $ordre->update(['masque_admin' => true]);
        return response()->json(['message' => 'Ordre supprimé avec succès']);
    }

    // Comportement original pour le ddl (propriétaire)
    if ($ordre->ddl_id !== $user->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }
 if ($ordre->statut === 'incident') {
        $ordre->update(['masque_ddl' => true]);
        return response()->json(['message' => 'Ordre supprimé avec succès']);
    }
    if (!in_array($ordre->statut, ['brouillon', 'en_attente_drh', 'rejete'])) {
        return response()->json(['message' => 'Impossible de supprimer un ordre déjà traité'], 403);
    }

    $ordre->delete();

    return response()->json(['message' => 'Ordre supprimé avec succès']);
}
// Prochaines navettes confirmées (visibles par tous les passagers)
public function prochainesNavettes()
{
    $navettes = OrdreMission::where('statut_chauffeur', 'accepte')
        ->where('date_depart', '>=', now()->toDateString())
        ->orderBy('date_depart')
        ->orderBy('heure_depart')
        ->select('id', 'destination', 'date_depart', 'heure_depart', 'trajet', 'trajet_autre')
        ->get();

    return response()->json($navettes);
}
  public function supprimerHistorique(Request $request, $id)
{
    $ordre = OrdreMission::findOrFail($id);
    $user = auth()->user();

    if ($user->role === 'ddl' && $ordre->ddl_id === $user->id) {
        $ordre->update(['masque_ddl' => true]);
        return response()->json(['message' => 'Demande masquée de votre vue']);
    }

    $champ = match($user->role) {
        'drh' => 'masque_drh',
        'sg_drh' => 'masque_sg_drh',
        'chauffeur' => 'masque_chauffeur',
        default => null
    };

    if (!$champ) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    // Empêche de masquer un incident tant que le DRH n'y a pas répondu
    if ($champ === 'masque_drh' && $ordre->incident && !$ordre->incident_repondu_drh) {
        return response()->json([
            'message' => 'Vous devez répondre à l\'incident avant de pouvoir le masquer'
        ], 403);
    }

    $ordre->update([$champ => true]);

    return response()->json(['message' => 'Supprimé de votre historique']);
}
}