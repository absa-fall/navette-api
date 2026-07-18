<?php

namespace App\Http\Controllers;

use App\Models\ProcesVerbal;
use App\Models\ProcesVerbalHistorique;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ProcesVerbalHistoriqueMasquage;
class ProcesVerbalController extends Controller
{
    // Rôles autorisés à rédiger / modifier le PV tant qu'il n'est pas finalisé
    private const ROLES_REDACTEURS = ['vice_recteur', 'commission'];

    // Rôles autorisés à lire le CONTENU complet du PV (lecture seule pour le Recteur)
    private const ROLES_LECTURE_CONTENU = ['recteur'];

    /**
     * Enregistre une entrée dans l'historique du PV.
     */
    private function logHistorique(ProcesVerbal $pv, string $action, $user)
    {
        ProcesVerbalHistorique::create([
            'proces_verbal_id' => $pv->id,
            'user_id' => $user?->id,
            'action' => $action,
            'contenu_snapshot' => $pv->contenu,
        ]);
    }

    private function peutVoirContenu($user): bool
    {
        return in_array($user->role, [...self::ROLES_REDACTEURS, ...self::ROLES_LECTURE_CONTENU]);
    }

    private function peutLister($user): bool
    {
        return $this->peutVoirContenu($user) || $user->role === 'admin';
    }

    private function peutRediger($user): bool
    {
        return in_array($user->role, self::ROLES_REDACTEURS);
    }

    /**
     * Liste tous les PV existants (historique par année).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$this->peutLister($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $pvs = ProcesVerbal::with([
            'dernierModificateur:id,nom,prenom',
            'finalisateur:id,nom,prenom',
            'signataireVr:id,nom,prenom',
            'signataireCommission:id,nom,prenom',
            'signataireRecteur:id,nom,prenom',
        ])->orderByDesc('annee')->get();

        if ($user->role === 'admin') {
            $pvs = $pvs->map(function ($pv) {
                return [
                    'id' => $pv->id,
                    'annee' => $pv->annee,
                    'statut' => $pv->statut,
                    'derniere_modif_par' => $pv->dernierModificateur,
                    'finalise_par' => $pv->finalisateur,
                    'finalise_le' => $pv->finalise_le,
                    'signe_vr_par' => $pv->signataireVr,
                    'signe_commission_par' => $pv->signataireCommission,
                    'signe_recteur_par' => $pv->signataireRecteur,
                    'created_at' => $pv->created_at,
                    'updated_at' => $pv->updated_at,
                ];
            });
        }

        return response()->json($pvs);
    }

    /**
     * Récupère le PV d'une année donnée (contenu complet).
     */
    public function show(Request $request, $annee)
    {
        $user = Auth::user();
        if (!$this->peutVoirContenu($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $pv = ProcesVerbal::with([
            'dernierModificateur:id,nom,prenom',
            'finalisateur:id,nom,prenom',
            'signataireVr:id,nom,prenom',
            'signataireCommission:id,nom,prenom',
            'transmetteur:id,nom,prenom',
            'signataireRecteur:id,nom,prenom',
        ])->where('annee', $annee)->first();

        if (!$pv) {
            if (!$this->peutRediger($user)) {
                return response()->json(['message' => 'Aucun PV pour cette année.'], 404);
            }
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

        $request->validate(['contenu' => 'required|string']);

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

        $this->logHistorique($pv, 'modification', $user);

        return response()->json($pv->fresh(['dernierModificateur:id,nom,prenom']));
    }

    /**
     * Finalise le PV (verrouille le texte). Réservé au Vice-Recteur.
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

        $this->logHistorique($pv, 'finalisation', $user);

        return response()->json($pv->fresh(['dernierModificateur:id,nom,prenom', 'finalisateur:id,nom,prenom']));
    }

    /**
     * Signature du Vice-Recteur sur le PV finalisé.
     */
    public function signerVr(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'vice_recteur') {
            return response()->json(['message' => 'Seul le Vice-Recteur peut apposer cette signature.'], 403);
        }

        $request->validate(['signature' => 'required|string']);

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }
        if (!$pv->estFinalise()) {
            return response()->json(['message' => 'Le PV doit être finalisé avant signature.'], 422);
        }
        if ($pv->estSigneVr()) {
            return response()->json(['message' => 'Le Vice-Recteur a déjà signé ce PV.'], 409);
        }

        $pv->update([
            'signature_vr' => $request->signature,
            'signe_vr_par' => $user->id,
            'signe_vr_le' => now(),
        ]);

        $this->logHistorique($pv, 'signature_vr', $user);

        return response()->json($pv->fresh(['signataireVr:id,nom,prenom']));
    }

    /**
     * Signature de la Commission sur le PV finalisé.
     */
    public function signerCommission(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'commission') {
            return response()->json(['message' => 'Seule la Commission peut apposer cette signature.'], 403);
        }

        $request->validate(['signature' => 'required|string']);

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }
        if (!$pv->estFinalise()) {
            return response()->json(['message' => 'Le PV doit être finalisé avant signature.'], 422);
        }
        if ($pv->estSigneCommission()) {
            return response()->json(['message' => 'La Commission a déjà signé ce PV.'], 409);
        }

        $pv->update([
            'signature_commission' => $request->signature,
            'signe_commission_par' => $user->id,
            'signe_commission_le' => now(),
        ]);

        $this->logHistorique($pv, 'signature_commission', $user);

        return response()->json($pv->fresh(['signataireCommission:id,nom,prenom']));
    }

    /**
     * Transmission manuelle au Recteur, réservée au Vice-Recteur,
     * uniquement une fois que VR et Commission ont tous deux signé.
     */
    public function transmettre(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'vice_recteur') {
            return response()->json(['message' => 'Seul le Vice-Recteur peut transmettre le PV au Recteur.'], 403);
        }

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }
        if (!$pv->estSigneVr()) {
    return response()->json(['message' => 'Le Vice-Recteur doit signer avant transmission.'], 422);
}
        if ($pv->estTransmis()) {
            return response()->json(['message' => 'Ce PV a déjà été transmis au Recteur.'], 409);
        }

        $pv->update([
            'statut' => 'transmis_recteur',
            'transmis_par' => $user->id,
            'transmis_le' => now(),
        ]);

        $this->logHistorique($pv, 'transmission', $user);

        return response()->json($pv->fresh(['transmetteur:id,nom,prenom']));
    }

    /**
     * Signature finale du Recteur. Clôt le processus.
     */
    public function signerRecteur(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'recteur') {
            return response()->json(['message' => 'Seul le Recteur peut signer le PV.'], 403);
        }

        $request->validate(['signature' => 'required|string']);

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }
        if ($pv->statut !== 'transmis_recteur') {
            return response()->json(['message' => 'Ce PV n\'a pas encore été transmis par le Vice-Recteur.'], 422);
        }

        $pv->update([
            'statut' => 'signe',
            'signature_recteur' => $request->signature,
            'signe_recteur_par' => $user->id,
            'signe_recteur_le' => now(),
        ]);

        $this->logHistorique($pv, 'signature_recteur', $user);

        return response()->json($pv->fresh(['signataireRecteur:id,nom,prenom']));
    }

    /**
     * Historique complet des actions sur un PV.
     */
    public function historique(Request $request, $annee)
    {
        $user = Auth::user();
        if (!$this->peutVoirContenu($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }

        $historiques = $pv->historiques()
            ->with('user:id,nom,prenom')
            ->whereDoesntHave('masquagesPour', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->get();

        return response()->json($historiques);
    }
    /**
     * Supprime définitivement un PV (et son historique lié). Réservé au Vice-Recteur.
     */
    public function destroy(Request $request, $annee)
    {
        $user = Auth::user();
        if ($user->role !== 'vice_recteur') {
            return response()->json(['message' => 'Seul le Vice-Recteur peut supprimer un PV.'], 403);
        }

        $pv = ProcesVerbal::where('annee', $annee)->first();
        if (!$pv) {
            return response()->json(['message' => 'Aucun PV trouvé pour cette année.'], 404);
        }

        $pv->historiques()->delete();
        $pv->delete();

        return response()->json(['message' => 'PV supprimé avec succès.']);
    }
    public function masquerHistorique(Request $request, $historiqueId)
    {
        $user = Auth::user();
        if (!$this->peutVoirContenu($user)) {
            return response()->json(['message' => 'Accès non autorisé.'], 403);
        }

        $historique = ProcesVerbalHistorique::find($historiqueId);
        if (!$historique) {
            return response()->json(['message' => 'Entrée d\'historique introuvable.'], 404);
        }

        \App\Models\ProcesVerbalHistoriqueMasquage::firstOrCreate([
            'historique_id' => $historique->id,
            'user_id' => $user->id,
        ]);

        return response()->json(['message' => 'Masqué.']);
    }
}