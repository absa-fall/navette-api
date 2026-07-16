<?php

namespace App\Http\Controllers;

use App\Models\RegistreTrajet;
use App\Models\PresenceNavette;
use App\Models\OrdreMission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistreTrajetController extends Controller
{
    
    public function store(Request $request)
    {
        $request->validate([
            'ordre_mission_id' => 'required|exists:ordres_mission,id',
        ]);

        $ordre = OrdreMission::findOrFail($request->ordre_mission_id);

        if ($ordre->statut !== 'signe') {
            return response()->json([
                'message' => 'L\'ordre de mission doit etre signe par le SG DRH'
            ], 403);
        }

        // Trouver le SG du Vice-Recteur
        $sgVr = User::where('role', 'sg_vr')->first();

        $registre = RegistreTrajet::create([
            'ordre_mission_id' => $request->ordre_mission_id,
            'chauffeur_id' => auth()->id(),
            'sg_vr_id' => $sgVr?->id,
            'date_trajet' => now()->toDateString(),
            'statut' => 'ouvert',
        ]);

        // Generer les QR codes pour chaque passager de l'OM
        $this->genererQRCodes($registre, $ordre);

        return response()->json([
            'message' => 'Registre ouvert. QR codes generes pour les passagers.',
            'registre' => $registre->load('presences.passager')
        ], 201);
    }

    // Generer les QR codes pour chaque passager
    private function genererQRCodes(RegistreTrajet $registre, OrdreMission $ordre)
    {
        $passagers = User::whereIn('id', [$ordre->ddl_id])->get();

        foreach ($passagers as $passager) {
            $estVacataire = $passager->statut === 'vacataire';
            $montant = $estVacataire ? 0 : OrdreMission::getMontantTrajet($ordre->trajet);

            PresenceNavette::create([
                'registre_id' => $registre->id,
                'passager_id' => $passager->id,
                'statut_passager' => $estVacataire ? 'vacataire' : 'permanent',
                'montant_retenue' => $montant,
                'qr_code' => 'QR-' . strtoupper(Str::random(8)),
                'validee_montee' => false,
                'validee_descente' => false,
            ]);
        }
    }

    // Liste des registres selon le role
    public function index()
    {
        $user = auth()->user();

        $registres = match($user->role) {
            'chauffeur' => RegistreTrajet::where('chauffeur_id', $user->id)
                ->with(['ordreMission', 'presences.passager'])
                ->latest()->get(),
            'sg_vr' => RegistreTrajet::where('statut', 'transmis')
                ->with(['ordreMission', 'chauffeur', 'presences.passager'])
                ->latest()->get(),
            'admin' => RegistreTrajet::with(['ordreMission', 'chauffeur', 'presences.passager'])
                ->latest()->get(),
            default => collect()
        };

        return response()->json($registres);
    }

    // Voir un registre
    public function show($id)
    {
        $registre = RegistreTrajet::with([
            'ordreMission',
            'chauffeur',
            'presences.passager'
        ])->findOrFail($id);

        return response()->json($registre);
    }

    // NOUVEAU : Passager valide sa montee (scan QR ou code)
    public function validerMontee(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string|size:11',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $presence = PresenceNavette::where('qr_code', $request->qr_code)
            ->where('validee_montee', false)
            ->first();

        if (!$presence) {
            return response()->json([
                'message' => 'QR code invalide ou deja valide'
            ], 404);
        }

        $presence->update([
            'validee_montee' => true,
            'heure_montee' => now(),
            'latitude_montee' => $request->latitude,
            'longitude_montee' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Montee validee avec succes',
            'presence' => $presence->load('passager', 'registre.ordreMission')
        ]);
    }

    // NOUVEAU : Passager valide sa descente
    public function validerDescente(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string|size:11',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $presence = PresenceNavette::where('qr_code', $request->qr_code)
            ->where('validee_montee', true)
            ->where('validee_descente', false)
            ->first();

        if (!$presence) {
            return response()->json([
                'message' => 'QR code invalide, montee non validee ou deja descendu'
            ], 404);
        }

        $presence->update([
            'validee_descente' => true,
            'heure_descente' => now(),
            'latitude_descente' => $request->latitude,
            'longitude_descente' => $request->longitude,
        ]);

        return response()->json([
            'message' => 'Descentee validee avec succes',
            'presence' => $presence->load('passager', 'registre.ordreMission'),
            'montant_retenue' => $presence->montant_retenue
        ]);
    }

    // NOUVEAU : Verifier le statut d'une validation
    public function verifierValidation($qrCode)
    {
        $presence = PresenceNavette::where('qr_code', $qrCode)
            ->with(['passager', 'registre.ordreMission'])
            ->first();

        if (!$presence) {
            return response()->json([
                'message' => 'QR code non trouve'
            ], 404);
        }

        return response()->json([
            'passager' => $presence->passager->nom_complet,
            'trajet' => $presence->registre->ordreMission->trajet,
            'validee_montee' => $presence->validee_montee,
            'validee_descente' => $presence->validee_descente,
            'heure_montee' => $presence->heure_montee,
            'heure_descente' => $presence->heure_descente,
            'montant_retenue' => $presence->montant_retenue,
        ]);
    }

    // Chauffeur cloture le registre (tous les passagers sont descendus)
    public function cloturer($id)
    {
        $registre = RegistreTrajet::with('presences')->findOrFail($id);

        if ($registre->statut !== 'ouvert') {
            return response()->json([
                'message' => 'Registre deja cloture'
            ], 403);
        }

        // Verifier que tous les passagers ont valide leur descente
        $nonValides = $registre->presences->where('validee_descente', false)->count();

        if ($nonValides > 0) {
            return response()->json([
                'message' => $nonValides . ' passager(s) n\'ont pas valide leur descente',
                'passagers_non_valides' => $registre->presences->where('validee_descente', false)
                    ->pluck('passager.nom_complet')
            ], 403);
        }

        $registre->update([
            'statut' => 'transmis',
            'date_cloture' => now(),
        ]);

        return response()->json([
            'message' => 'Registre cloture et transmis au SG du Vice-Recteur',
            'registre' => $registre
        ]);
    }

    // Ancienne methode : plus utilisee (remplacee par validerMontee/validerDescente)
    public function ajouterPassager(Request $request, $id)
    {
        return response()->json([
            'message' => 'Cette methode est remplacee par la validation auto du passager'
        ], 410);
    }

    public function retirerPassager($registreId, $passagerId)
    {
        return response()->json([
            'message' => 'Cette methode est remplacee par la validation auto du passager'
        ], 410);
    }
}