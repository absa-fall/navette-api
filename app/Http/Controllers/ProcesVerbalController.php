<?php

namespace App\Http\Controllers;

use App\Models\ProcesVerbal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProcesVerbalController extends Controller
{
    // Rôles autorisés à rédiger / modifier le PV tant qu'il n'est pas finalisé
    private const ROLES_REDACTEURS = ['vice_recteur', 'commission'];

    // Rôles autorisés à lire le CONTENU complet du PV (lecture seule pour le Recteur)
    private const ROLES_LECTURE_CONTENU = ['recteur'];

    /**
     * Peut voir le contenu complet d'un PV (rédacteurs + Recteur).
     */
    private function peutVoirContenu($user): bool
    {
        return in_array($user->role, [...self::ROLES_REDACTEURS, ...self::ROLES_LECTURE_CONTENU]);
    }

    /**
     * Peut voir la LISTE des PV (métadonnées seulement), sans le contenu.
     * Inclut l'admin, en plus de tous ceux qui peuvent voir le contenu.
     */
    private function peutLister($user): bool
    {
        return $this->peutVoirContenu($user) || $user->role === 'admin';
    }

    /**
     * Vérifie que l'utilisateur peut rédiger/modifier (VR ou Commission).
     */
    private function peutRediger($user): bool
    {
        return in_array($user->role, self::ROLES_REDACTEURS);
    }

    /**
     * Liste tous les PV existants (historique par année).
     * L'admin voit la liste mais pas le contenu (comme pour les autres modules d'admin).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$this->peutLister($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $pvs = ProcesVerbal::with(['dernierModificateur:id,nom,prenom', 'finalisateur:id,nom,prenom'])
            ->orderByDesc('annee')
            ->get();

        // L'admin ne voit que les métadonnées, pas le contenu rédigé
        if ($user->role === 'admin') {
            $pvs = $pvs->map(function ($pv) {
                return [
                    'id' => $pv->id,
                    'annee' => $pv->annee,
                    'statut' => $pv->statut,
                    'derniere_modif_par' => $pv->dernierModificateur,
                    'finalise_par' => $pv->finalisateur,
                    'finalise_le' => $pv->finalise_le,
                    'created_at' => $pv->created_at,
                    'updated_at' => $pv->updated_at,
                ];
            });
        }

        return response()->json($pvs);
    }

    /**
     * Récupère le PV d'une année donnée (contenu complet). L'admin n'y a pas accès
     * (il passe uniquement par index() pour le listing).
     */
    public function show(Request $request, $annee)
    {
        $user = Auth::user();
        if (!$this->peutVoirContenu($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $pv = ProcesVerbal::with(['dernierModificateur:id,nom,prenom', 'finalisateur:id,nom,prenom'])
            ->where('annee', $annee)
            ->first();

        if (!$pv) {
            if (!$this->peutRediger($user)) {
                return response()->json(['message' => 'Aucun PV pour cette année.'], 404);
            }
            // Auto-création d'un brouillon vide pour que VR/Commission puissent commencer à écrire
            $pv = ProcesVerbal::create([
                'annee' => $annee,
                'contenu' => '',
                'statut' => 'brouillon',
                'derniere_modif_par' => $user->id,
            ]);
        }

        return response()->json($pv);
    }

    /**
     * Met à jour le contenu du PV (VR ou Commission), uniquement si pas encore finalisé.
     */
    public function update(Request $request, $annee)
    {
        $user = Auth::user();
        if (!$this->peutRediger($user)) {
            return response()->json(['message' => 'Seuls le Vice-Recteur et la Commission peuvent modifier le PV.'], 403);
        }

        $request->validate([
            'contenu' => 'required|string',
        ]);

        $pv = ProcesVerbal::firstOrCreate(
            ['annee' => $annee],
            ['statut' => 'brouillon']
        );

        if ($pv->estFinalise()) {
            return response()->json(['message' => 'Ce PV est finalisé et ne peut plus être modifié.'], 423);
        }

        $pv->update([
            'contenu' => $request->contenu,
            'derniere_modif_par' => $user->id,
        ]);

        return response()->json($pv->fresh(['dernierModificateur:id,nom,prenom']));
    }

    /**
     * Finalise le PV (verrouille définitivement). Réservé au Vice-Recteur.
     */
    public function finaliser(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'vice_recteur') {
            return response()->json(['message' => 'Seul le Vice-Recteur peut finaliser le PV.'], 403);
        }

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }

        if ($pv->estFinalise()) {
            return response()->json(['message' => 'Ce PV est déjà finalisé.'], 409);
        }

        if (empty(trim($pv->contenu ?? ''))) {
            return response()->json(['message' => 'Impossible de finaliser un PV vide.'], 422);
        }

        $pv->update([
            'statut' => 'finalise',
            'finalise_par' => $user->id,
            'finalise_le' => now(),
        ]);

        return response()->json($pv->fresh(['dernierModificateur:id,nom,prenom', 'finalisateur:id,nom,prenom']));
    }
}