<?php

namespace App\Http\Controllers;

use App\Models\OrdreMission;
use App\Models\VoyageEtude;
use App\Models\RapportVoyage;

class NotificationController extends Controller
{
    public function sidebar()
    {
        try {
            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'message' => 'Non authentifié'
                ], 401);
            }

            $role = $user->role;

            $notifications = [
                // DRH
                'drhOrdres' => 0,
                'drhOrdresApprouves' => 0,
                'drhOrdresRejetes' => 0,

                // SG DRH
                'sgDrhOrdres' => 0,
                'sgDrhSignes' => 0,
                'sgDrhTransmis' => 0,

                // Vice Recteur
                'viceRecteurVoyages' => 0,
                'viceRecteurRapports' => 0,

                // Chauffeur
                'trajetsAssignes' => 0,
                'enAttente' => 0,
                'trajetsEffectues' => 0,

                // DDL
                'mesDemandes' => 0,
                'mesDemandesRejetees' => 0,
            ];

            if ($role === 'drh') {
                $notifications['drhOrdres'] = OrdreMission::where('statut', 'en_attente_drh')->count();
                $notifications['drhOrdresApprouves'] = OrdreMission::where('statut', 'execute')->count();
                $notifications['drhOrdresRejetes'] = OrdreMission::where('statut', 'rejete')->count();
            }

            if ($role === 'sg_drh') {
                $notifications['sgDrhOrdres'] = OrdreMission::where('statut', 'approuve_drh')->count();
                $notifications['sgDrhSignes'] = OrdreMission::where('statut', 'execute')->count();
                $notifications['sgDrhTransmis'] = OrdreMission::where('statut', 'execute')->count();
            }

            if ($role === 'vice_recteur') {
                $notifications['viceRecteurVoyages'] = VoyageEtude::where('statut', 'en_attente')->count();
                $notifications['viceRecteurRapports'] = RapportVoyage::where('statut', 'soumis')->count();
            }

            if ($role === 'chauffeur') {
                $notifications['trajetsAssignes'] = OrdreMission::where('chauffeur_id', $user->id)->count();
                $notifications['enAttente'] = OrdreMission::where('chauffeur_id', $user->id)
                    ->where('statut', 'transmis_chauffeur')
                    ->count();
                $notifications['trajetsEffectues'] = OrdreMission::where('chauffeur_id', $user->id)
                    ->where('statut', 'execute')
                    ->count();
            }

            if ($role === 'ddl') {
                $notifications['mesDemandes'] = OrdreMission::where('ddl_id', $user->id)
                    ->where('statut', 'en_attente_drh')
                    ->count();

                $notifications['mesDemandesRejetees'] = OrdreMission::where('ddl_id', $user->id)
                    ->where('statut', 'rejete')
                    ->count();
            }

            return response()->json($notifications);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}