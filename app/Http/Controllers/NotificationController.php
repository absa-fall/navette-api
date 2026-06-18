<?php

namespace App\Http\Controllers;

use App\Models\OrdreMission;
use App\Models\VoyageEtude;
use App\Models\RapportVoyage;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Non authentifie'], 401);
        }

        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(50)
            ->get();

        return response()->json($notifications);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'              => 'required|string',
            'titre'             => 'required|string',
            'message'           => 'required|string',
            'destinataire_role' => 'nullable|string',
            'ordre_id'          => 'nullable|integer|exists:ordres_mission,id',
            'motif_refus'       => 'nullable|string',
        ]);

        $destinataire = null;

        if ($request->has('destinataire_role') && $request->destinataire_role) {
            $destinataire = User::where('role', $request->destinataire_role)->first();
        }

        if (!$destinataire && $request->ordre_id) {
            $ordre = OrdreMission::find($request->ordre_id);
            if ($ordre && $ordre->ddl_id) {
                $destinataire = User::find($ordre->ddl_id);
            }
        }

        if (!$destinataire) {
            return response()->json(['message' => 'Aucun destinataire trouve'], 404);
        }

        $notification = Notification::create([
            'user_id'     => $destinataire->id,
            'type'        => $validated['type'],
            'titre'       => $validated['titre'],
            'message'     => $validated['message'],
            'ordre_id'    => $validated['ordre_id'] ?? null,
            'motif_refus' => $validated['motif_refus'] ?? null,
            'lu'          => false,
        ]);

        return response()->json([
            'message'      => 'Notification creee',
            'notification' => $notification
        ], 201);
    }

    public function marquerLu($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->update(['lu' => true]);

        return response()->json(['message' => 'Notification marquee comme lue']);
    }

    public function marquerToutesLues()
    {
        Notification::where('user_id', auth()->id())
            ->where('lu', false)
            ->update(['lu' => true]);

        return response()->json(['message' => 'Toutes les notifications ont ete marquees comme lues']);
    }

    public function destroy($id)
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $notification->delete();

        return response()->json(['message' => 'Notification supprimee']);
    }

    public function supprimerToutes()
    {
        Notification::where('user_id', auth()->id())->delete();

        return response()->json(['message' => 'Toutes les notifications ont ete supprimees']);
    }

    public function sidebar()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json(['message' => 'Non authentifie'], 401);
            }

            $role = $user->role;

            $notifications = [
                'drhOrdres'            => 0,
                'drhOrdresApprouves'   => 0,
                'drhOrdresRejetes'     => 0,
                'sgDrhOrdres'          => 0,
                'sgDrhSignes'          => 0,
                'sgDrhTransmis'        => 0,
                'viceRecteurVoyages'   => 0,
                'viceRecteurRapports'  => 0,
                'trajetsAssignes'      => 0,
                'enAttente'            => 0,
                'trajetsEffectues'     => 0,
                'trajetsRefuses'       => 0,
                'mesDemandes'          => 0,
                'mesDemandesRejetees'  => 0,
                'notificationsNonLues' => 0,
                'refusChauffeur'       => 0,
            ];

            $notifications['notificationsNonLues'] = Notification::where('user_id', $user->id)
                ->where('lu', false)
                ->count();

            // Roles sans compteurs specifiques
            if (in_array($role, ['chef_departement', 'directeur_ufr', 'recteur', 'usager', 'admin', 'sg_vr', 'enseignant'])) {
                return response()->json($notifications);
            }

            if ($role === 'drh') {
                $notifications['drhOrdres']          = OrdreMission::where('statut', 'en_attente_drh')->count();
                $notifications['drhOrdresApprouves'] = OrdreMission::where('statut', 'execute')->count();
                $notifications['drhOrdresRejetes']   = OrdreMission::where('statut', 'rejete')->count();
            }

            if ($role === 'sg_drh') {
                $notifications['sgDrhOrdres']   = OrdreMission::where('statut', 'approuve_drh')->count();
                $notifications['sgDrhSignes']   = OrdreMission::where('statut', 'execute')->count();
                $notifications['sgDrhTransmis'] = OrdreMission::where('statut', 'execute')->count();
            }

            if ($role === 'vice_recteur') {
                $notifications['viceRecteurVoyages']  = VoyageEtude::where('statut_liste', 'publiee')->count();
                $notifications['viceRecteurRapports'] = RapportVoyage::where('statut', 'soumis')->count();
            }

            if ($role === 'chauffeur') {
                $notifications['trajetsAssignes'] = OrdreMission::where('chauffeur_id', $user->id)->count();

                $notifications['enAttente'] = OrdreMission::where('chauffeur_id', $user->id)
                    ->where('statut', 'transmis_chauffeur')
                    ->where(function ($q) {
                        $q->whereNull('statut_chauffeur')
                          ->orWhere('statut_chauffeur', 'en_attente');
                    })
                    ->count();

                $notifications['trajetsEffectues'] = OrdreMission::where('chauffeur_id', $user->id)
                    ->where('statut', 'execute')
                    ->count();

                $notifications['trajetsRefuses'] = OrdreMission::where('chauffeur_id', $user->id)
                    ->where('statut_chauffeur', 'refuse')
                    ->count();
            }

            if ($role === 'ddl') {
                $notifications['mesDemandes'] = OrdreMission::where('ddl_id', $user->id)
                    ->where('statut', 'en_attente_drh')
                    ->count();

                $notifications['mesDemandesRejetees'] = OrdreMission::where('ddl_id', $user->id)
                    ->where('statut', 'rejete')
                    ->count();

                $notifications['refusChauffeur'] = Notification::where('user_id', $user->id)
                    ->where('type', 'refus_chauffeur')
                    ->where('lu', false)
                    ->count();
            }

            return response()->json($notifications);

        } catch (\Exception $e) {
            \Log::error('Sidebar error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
    }
}