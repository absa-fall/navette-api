<?php

namespace App\Http\Controllers;

use App\Models\ArreteVoyage;
use App\Models\VoyageEtude;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;

class ArreteVoyageController extends Controller
{
    public function store(Request $request, $voyageId)
{
    $request->validate([
        'numero'             => 'required|string|max:50',
        'date_arrete'        => 'required|date',
        'visas'              => 'required|string',
        'montant_billet'     => 'required|numeric|min:0',
        'montant_indemnite'  => 'required|numeric|min:0',
    ]);

    $voyage = VoyageEtude::findOrFail($voyageId);

    $arrete = ArreteVoyage::create([
        'voyage_id'          => $voyage->id,
        'recteur_id'         => auth()->id(),
        'numero'             => $request->numero,
        'date_arrete'        => $request->date_arrete,
        'visas'              => $request->visas,
        'montant_billet'     => $request->montant_billet,
        'montant_indemnite'  => $request->montant_indemnite,
        'signe'              => true,
        'date_signature'     => now(),
    ]);

    $voyage->update(['arrete_recteur' => true]);

    $vr = User::where('role', 'vice_recteur')->first();
    if ($vr) {
        // Notification
        Notification::create([
            'user_id' => $vr->id,
            'type'    => 'arrete_signe',
            'titre'   => 'Arrete signe par le Recteur',
            'message' => 'L\'arrete n°' . $arrete->numero . ' pour le voyage a ' . $voyage->destination . ' a ete signe. Vous pouvez le transmettre aux enseignants beneficiaires.',
            'lu'      => false,
        ]);

        // ← AJOUT : Envoi du PDF par mail au VR
        try {
            \Mail::to($vr->email)->send(new \App\Mail\ArreteVoyageMail($arrete, null));
        } catch (\Exception $e) {
            \Log::error('Erreur envoi mail arrete au VR : ' . $e->getMessage());
        }
    }
    
// Envoyer l'arrêté par mail à chaque bénéficiaire définitif
    $beneficiairesDefinitifs = $voyage->beneficiaires()->where('dans_liste_definitive', true)->with('enseignant')->get();
    foreach ($beneficiairesDefinitifs as $b) {
        if ($b->enseignant && $b->enseignant->email) {
            try {
                \Mail::to($b->enseignant->email)->send(new \App\Mail\ArreteVoyageMail($arrete, $b->enseignant));
            } catch (\Exception $e) {
                \Log::error('Erreur envoi mail arrete enseignant : ' . $e->getMessage());
            }
        }
        Notification::create([
            'user_id' => $b->enseignant_id,
            'type'    => 'arrete_signe',
            'titre'   => 'Arrete de voyage signe',
            'message' => 'L\'arrete n°' . $arrete->numero . ' pour le voyage a ' . $voyage->destination . ' a ete signe. Vous pouvez le consulter.',
            'lu'      => false,
        ]);
    }

    return response()->json([
        'message' => 'Arrete redige, signe et envoye au Vice-Recteur par mail',
        'arrete'  => $arrete,
    ], 201);
}
public function destroy($id)
{
    $arrete = ArreteVoyage::findOrFail($id);
    $voyage = VoyageEtude::find($arrete->voyage_id);

    $arrete->delete();

    // On repasse le voyage en "non signé" pour qu'il redevienne éditable
    if ($voyage) {
        $voyage->update(['arrete_recteur' => false]);
    }

    return response()->json(['message' => 'Arrete supprime avec succes']);
}
    // Voir un arrêté (toutes les parties prenantes)
    public function show($id)
    {
        $arrete = ArreteVoyage::with(['voyage.beneficiaires.enseignant', 'recteur'])
            ->findOrFail($id);

        return response()->json($arrete);
    }

    // Voir l'arrêté d'un voyage donné
    public function showByVoyage($voyageId)
    {
        $arrete = ArreteVoyage::with(['voyage.beneficiaires.enseignant', 'recteur'])
            ->where('voyage_id', $voyageId)
            ->firstOrFail();

        return response()->json($arrete);
    }
public function mesArretes()
{
    $arretes = ArreteVoyage::with(['voyage.beneficiaires.enseignant', 'recteur'])
        ->where('recteur_id', auth()->id())
        ->latest()
        ->get();

    return response()->json($arretes);
}
    // VR — Envoyer l'arrêté par email aux bénéficiaires
    public function envoyerEmails($id)
    {
        $arrete = ArreteVoyage::with(['voyage.beneficiaires.enseignant'])->findOrFail($id);

        $beneficiairesDefinitifs = $arrete->voyage->beneficiaires->where('dans_liste_definitive', true);

        foreach ($beneficiairesDefinitifs as $b) {
            if ($b->enseignant && $b->enseignant->email) {
                \Mail::to($b->enseignant->email)->send(new \App\Mail\ArreteVoyageMail($arrete, $b->enseignant));
            }
        }

        $arrete->update(['date_envoi_emails' => now()]);

        return response()->json(['message' => 'Arrete envoye par email a tous les beneficiaires']);
    }
}