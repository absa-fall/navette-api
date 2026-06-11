<?php

namespace App\Http\Controllers;

use App\Models\OrdreMission;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdreMissionController extends Controller
{
    // DDL soumet une demande
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
            'heure_depart' => 'nullable|string', // ✅ plus required
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
            'heure_depart' => $request->heure_depart ?? '07:30', // ✅ valeur par défaut
            'date_retour' => $request->date_retour,
            'frais_transport' => $request->frais_transport ?? 'Appui en carburant',
            'indemnite_deplacement' => $request->indemnite_deplacement ?? 'Néant',
            'trajet' => $request->trajet,
            'trajet_autre' => $request->trajet_autre,
            'montant_trajet' => $montant,
            'motif' => $request->motif,
            'statut' => 'en_attente_drh',
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
            'message' => 'Demande soumise avec succès',
            'ordre' => $ordre
        ], 201);
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
            
            'admin' => OrdreMission::query(),
            
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

    // Chauffeur voit ses ordres
    public function pourChauffeur()
    {
        $ordres = OrdreMission::where('chauffeur_id', auth()->id())
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

    // Modifier un ordre
    public function update(Request $request, $id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->ddl_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($ordre->statut, ['en_attente_drh', 'rejete'])) {
            return response()->json(['message' => 'Impossible de modifier un ordre déjà traité'], 403);
        }

        $data = $request->all();
        if ($ordre->statut === 'rejete') {
            $data['statut'] = 'en_attente_drh';
            $data['commentaire_rejet'] = null;
            $data['drh_id'] = null;
        }

        $ordre->update($data);

        // Notifier le DRH qu'une demande modifiée est arrivée
        if ($ordre->statut === 'en_attente_drh') {
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
        }

        return response()->json([
            'message' => 'Ordre modifié avec succès. Il a été renvoyé au DRH pour approbation.',
            'ordre' => $ordre
        ]);
    }

    // Supprimer un ordre
    public function destroy($id)
    {
        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->ddl_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!in_array($ordre->statut, ['en_attente_drh', 'rejete'])) {
            return response()->json(['message' => 'Impossible de supprimer un ordre déjà traité'], 403);
        }

        $ordre->delete();

        return response()->json(['message' => 'Ordre supprimé avec succès']);
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

        $ordre->update([$champ => true]);

        return response()->json(['message' => 'Supprimé de votre historique']);
    }
}