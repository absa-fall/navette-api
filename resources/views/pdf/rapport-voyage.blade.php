<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; }
        .entete { width: 100%; margin-bottom: 14px; }
        .entete td { vertical-align: top; font-size: 10px; }
        .entete .droite { text-align: right; font-size: 11px; }
        .logo-bloc { text-align: center; margin-bottom: 6px; }
        .logo-bloc img { width: 60px; }
        .vice-rectorat { text-align: center; font-weight: bold; font-size: 11px; margin-bottom: 10px; }
        .vice-rectorat span { font-weight: normal; font-size: 10px; }
        hr { border: none; border-top: 1px solid #1e293b; margin-bottom: 16px; }
        h1 { font-size: 15px; text-align: center; text-decoration: underline; margin-bottom: 24px; }
        .info-row { margin-bottom: 8px; }
        .info-row .label { display: inline-block; width: 180px; }
        .info-row .valeur { font-weight: bold; border-bottom: 1px solid #1e293b; }
        .objet { margin-bottom: 20px; }
        .paragraphe { text-align: justify; margin-bottom: 16px; }
        .section { margin-top: 16px; border-left: 2px solid #1e293b; padding-left: 10px; }
        .section h3 { font-size: 10.5px; text-transform: uppercase; margin-bottom: 4px; color: #1e293b; }
        .section p { text-align: justify; white-space: pre-line; }
        .rejet { margin-top: 20px; padding: 10px; border: 1px solid #dc2626; background: #fef2f2; }
        .signature-zone { text-align: right; margin-top: 30px; }
        .signature-zone img { width: 140px; margin-top: 6px; }
        .pied { text-align: center; font-size: 9px; color: #475569; border-top: 1px solid #cbd5e1; padding-top: 8px; margin-top: 24px; }
    </style>
</head>
<body>
    <table class="entete">
        <tr>
            <td style="width:70%;">
                <strong>REPUBLIQUE DU SENEGAL</strong><br>
                <em>Un Peuple-Un But-Une Foi</em><br>
                Ministère de l'Enseignement supérieur,<br>
                de la Recherche et de l'Innovation<br><br>
                <strong>UNIVERSITE ALIOUNE DIOP</strong><br>
                <em>« L'excellence est ma constance, l'éthique ma vertu »</em>
            </td>
            <td class="droite" style="width:30%;">
                <strong>N° ______ UAD/VR/SG</strong><br><br>
                Bambey, le {{ isset($rapport) && $rapport->date_depot ? \Carbon\Carbon::parse($rapport->date_depot)->format('d/m/Y') : \Carbon\Carbon::parse($date)->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <div class="logo-bloc">
        <img src="{{ public_path('logo-uadb.png') }}">
    </div>

    <div class="vice-rectorat">
        VICE-RECTORAT<br>
        <span>Voyages d'études</span>
    </div>

    <hr>

    <h1>RAPPORT DE VOYAGE D'ÉTUDES</h1>

    <div class="info-row"><span class="label">Enseignant :</span> <span class="valeur">{{ $enseignant->prenom }} {{ $enseignant->nom }}</span></div>
    <div class="info-row"><span class="label">UFR :</span> <span class="valeur">{{ $enseignant->ufr ?? '___________' }}</span></div>
    <div class="info-row"><span class="label">Destination :</span> <span class="valeur">{{ $voyage->destination ?? '___________' }}</span></div>
    <div class="info-row"><span class="label">Date de départ :</span> <span class="valeur">{{ !empty($voyage->date_debut) ? \Carbon\Carbon::parse($voyage->date_debut)->format('d/m/Y') : '___________' }}</span></div>
    <div class="info-row"><span class="label">Date de retour :</span> <span class="valeur">{{ !empty($voyage->date_fin) ? \Carbon\Carbon::parse($voyage->date_fin)->format('d/m/Y') : '___________' }}</span></div>
    <div class="info-row"><span class="label">Date de dépôt du rapport :</span> <span class="valeur">{{ isset($rapport) && $rapport->date_depot ? \Carbon\Carbon::parse($rapport->date_depot)->format('d/m/Y') : '___________' }}</span></div>

    <div class="objet">
        <strong>Objet :</strong> Rapport de voyage d'études — {{ $voyage->destination ?? '___________' }}
    </div>

    <p>Monsieur le Vice-Recteur,</p>

    <p class="paragraphe">
        J'ai l'honneur de vous soumettre, par la présente, le rapport relatif au voyage d'études
        effectué du {{ !empty($voyage->date_debut) ? \Carbon\Carbon::parse($voyage->date_debut)->format('d/m/Y') : '___________' }}
        au {{ !empty($voyage->date_fin) ? \Carbon\Carbon::parse($voyage->date_fin)->format('d/m/Y') : '___________' }}
        à {{ $voyage->destination ?? '___________' }}, conformément à l'autorisation qui m'a été accordée.
    </p>

    <hr>

    <div class="section">
        <h3>Objectifs de la mission</h3>
        <p>{{ $donnees['objectifs'] ?? '' }}</p>
    </div>
    <div class="section">
        <h3>Déroulement du voyage</h3>
        <p>{{ $donnees['deroulement'] ?? '' }}</p>
    </div>
    <div class="section">
        <h3>Résultats et apprentissages</h3>
        <p>{{ $donnees['resultats'] ?? '' }}</p>
    </div>
    <div class="section">
        <h3>Recommandations</h3>
        <p>{{ $donnees['recommandations'] ?? '' }}</p>
    </div>

    <p class="paragraphe" style="margin-top:20px;">
        Je reste à votre disposition pour tout complément d'information utile
        et vous prie d'agréer, Monsieur le Vice-Recteur, l'expression de ma considération distinguée.
    </p>

    @if(isset($rapport) && $rapport->statut === 'rejete' && $rapport->commentaire_vr)
    <div class="rejet">
        <strong>Motif du rejet :</strong>
        <p>{{ $rapport->commentaire_vr }}</p>
    </div>
    @endif

    <p style="font-size:10px; color:#475569;">Je soussigné(e), certifie l'exactitude des informations contenues dans le présent rapport.</p>

    <div class="signature-zone">
        <strong>{{ $enseignant->prenom }} {{ $enseignant->nom }}</strong><br>
        @if(isset($rapport) && $rapport->signature_enseignant)
            <img src="{{ $rapport->signature_enseignant }}">
        @endif
    </div>

    <div class="pied">
        <p>Tél. : (221) 33 973 30 86. // Fax : (221) 33 973 30 93 // B.P. : 30 – Bambey (République du Sénégal)</p>
        <p>Internet : www.uadb.edu.sn // Courriel : rectorat@uadb.edu.sn</p>
    </div>
</body>
</html>