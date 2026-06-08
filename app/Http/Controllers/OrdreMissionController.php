<?php

namespace App\Http\Controllers;

use App\Models\OrdreMission;
use App\Models\User;
use Illuminate\Http\Request;

class OrdreMissionController extends Controller
{
    // DDL soumet une demande
   // DDL soumet une ademande
    public function store(Request $request)
    {
        $request->validate([
            // Champs ordre de mission
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
            'heure_depart' => 'required',
            'date_retour' => 'required|date|after_or_equal:date_depart',
            'frais_transport' => 'nullable|string|max:200',
            'indemnite_deplacement' => 'nullable|string|max:200',
            // Champs calcul
            'trajet' => 'nullable|in:dakar_bambey,thies_bambey,bambey_ngouniane,autres',
            'trajet_autre' => 'nullable|string|max:200',
            'motif' => 'nullable|string',
        ]);

        $montant = $request->trajet ? OrdreMission::getMontantTrajet($request->trajet) : 0;

        $ordre = OrdreMission::create([
            'ddl_id' => auth()->id(),
            // Champs ordre de mission
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
            'heure_depart' => $request->heure_depart,
            'date_retour' => $request->date_retour,
            'frais_transport' => $request->frais_transport ?? 'Appui en carburant',
            'indemnite_deplacement' => $request->indemnite_deplacement ?? 'Néant',
            // Champs calcul et statut
            'trajet' => $request->trajet,
            'trajet_autre' => $request->trajet_autre,
            'montant_trajet' => $montant,
            'motif' => $request->motif,
            'statut' => 'en_attente_drh',
        ]);

        return response()->json([
            'message' => 'Demande soumise avec succès',
            'ordre' => $ordre
        ], 201);
    }

    // Liste des ordres selon le rôle
    public function index(Request $request)
    {
        $user = auth()->user();

        $ordres = match($user->role) {
            'ddl' => OrdreMission::where('ddl_id', $user->id)
                ->with(['vehicule', 'chauffeur'])
                ->latest()
                ->get(),
            'drh' => OrdreMission::with(['ddl', 'vehicule', 'chauffeur', 'sgDrh'])
    ->latest()
    ->get(),
            'sg_drh' => OrdreMission::with(['ddl', 'vehicule', 'chauffeur', 'sgDrh'])
    ->latest()
    ->get(),
            'chauffeur' => OrdreMission::where('chauffeur_id', $user->id)
    ->with(['ddl', 'vehicule', 'sgDrh'])
    ->latest()
    ->get(),
            'admin' => OrdreMission::with(['ddl', 'sgDrh', 'chauffeur', 'vehicule'])
                ->latest()
                ->get(),
            default => collect()
        };

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

        return response()->json([
            'message' => 'Ordre approuvé et transmis au SG DRH',
            'ordre' => $ordre
        ]);
    }
    public function supprimerHistorique($id)
{
    $ordre = OrdreMission::findOrFail($id);
    $user = auth()->user();

    // Vérifier que l'ordre appartient à l'utilisateur selon son rôle
    if ($user->role === 'ddl' && $ordre->ddl_id !== $user->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }
    if ($user->role === 'chauffeur' && $ordre->chauffeur_id !== $user->id) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    $ordre->delete();
    return response()->json(['message' => 'Supprimé avec succès']);
}
    public function update(Request $request, $id)
{
    $ordre = OrdreMission::findOrFail($id);

    if ($ordre->ddl_id !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    if ($ordre->statut !== 'en_attente_drh') {
        return response()->json(['message' => 'Impossible de modifier un ordre déjà traité'], 403);
    }

    $ordre->update($request->all());

    return response()->json(['message' => 'Ordre modifié avec succès', 'ordre' => $ordre]);
}
    public function destroy($id)
{
    $ordre = OrdreMission::findOrFail($id);

    if ($ordre->ddl_id !== auth()->id()) {
        return response()->json(['message' => 'Non autorisé'], 403);
    }

    if ($ordre->statut !== 'en_attente_drh') {
        return response()->json(['message' => 'Impossible de supprimer un ordre déjà traité'], 403);
    }

    $ordre->delete();

    return response()->json(['message' => 'Ordre supprimé avec succès']);
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

        return response()->json([
            'message' => 'Ordre rejeté',
            'ordre' => $ordre
        ]);
    }

    // SG DRH signe et assigne chauffeur automatiquement
    public function signer(Request $request, $id)
    {
        $request->validate([
            'chauffeur_id' => 'nullable|exists:users,id',
        ]);

        $ordre = OrdreMission::findOrFail($id);

        if ($ordre->statut !== 'approuve_drh') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        // Assigner chauffeur automatiquement si non fourni
        $chauffeurId = $request->chauffeur_id;
        if (!$chauffeurId) {
            $chauffeur = User::where('role', 'chauffeur')
                ->where('active', true)
                ->first();
            $chauffeurId = $chauffeur ? $chauffeur->id : null;
        }

        $ordre->update([
            'statut' => 'transmis_chauffeur',
            'sg_drh_id' => auth()->id(),
            'signature_sg_drh' => true,
            'date_signature' => now(),
            'chauffeur_id' => $chauffeurId,
        ]);

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

        if ($ordre->statut !== 'transmis_chauffeur') {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        if ($ordre->chauffeur_id !== auth()->id()) {
            return response()->json(['message' => 'Ce trajet ne vous est pas assigné'], 403);
        }

        $ordre->update(['statut' => 'execute']);

        return response()->json([
            'message' => 'Trajet marqué comme exécuté',
            'ordre' => $ordre
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
}