<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 13px; color: #000; padding: 40px; }

        .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .header-left { font-size: 11px; line-height: 1.8; }
        .header-right { text-align: right; font-size: 11px; }

        .titre { text-align: center; font-size: 15px; font-weight: bold; text-decoration: underline; margin: 25px 0; letter-spacing: 1px; }
        .sous-titre { text-align: center; font-size: 12px; margin-bottom: 20px; }

        .section { margin-bottom: 15px; }
        .section-titre { font-weight: bold; font-size: 12px; text-decoration: underline; margin-bottom: 8px; }

        .visas { margin-left: 20px; line-height: 2; font-size: 12px; }
        .article { margin-bottom: 10px; line-height: 1.7; font-size: 12px; }
        .article strong { font-weight: bold; }

        .beneficiaires-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
        .beneficiaires-table th { background: #f0f0f0; border: 1px solid #999; padding: 6px 8px; text-align: left; font-size: 11px; }
        .beneficiaires-table td { border: 1px solid #999; padding: 5px 8px; }

        .signature-section { margin-top: 40px; display: flex; justify-content: flex-end; }
        .signature-box { text-align: center; font-size: 12px; }

        .footer { margin-top: 50px; border-top: 1px solid #000; padding-top: 8px; text-align: center; font-size: 10px; color: #555; }
        .divider { border-top: 1px solid #000; margin: 15px 0; }
    </style>
</head>
<body>

    <!-- En-tête -->
    <div class="header">
        <div class="header-left">
            <strong>REPUBLIQUE DU SENEGAL</strong><br>
            Un Peuple - Un But - Une Foi<br>
            Ministère de l'Enseignement supérieur,<br>
            de la Recherche et de l'Innovation<br><br>
            <strong>UNIVERSITE ALIOUNE DIOP</strong><br>
            <em>« L'excellence est ma constance, l'éthique ma vertu »</em>
        </div>
        <div class="header-right">
            <strong>N° {{ $arrete->numero }}</strong><br><br>
            Bambey, le {{ \Carbon\Carbon::parse($arrete->date_arrete)->locale('fr')->isoFormat('D MMMM YYYY') }}
        </div>
    </div>

    <div class="divider"></div>

    <!-- Titre -->
    <div class="titre">ARRETE N° {{ $arrete->numero }}</div>
    <div class="sous-titre">
        Portant autorisation de voyage d'études à {{ $arrete->voyage->destination }}<br>
        du {{ \Carbon\Carbon::parse($arrete->voyage->date_debut)->format('d/m/Y') }}
        au {{ \Carbon\Carbon::parse($arrete->voyage->date_fin)->format('d/m/Y') }}
    </div>

    <!-- Visas -->
    <div class="section">
        <div class="section-titre">VU :</div>
        <div class="visas">
            {!! nl2br(e($arrete->visas)) !!}
        </div>
    </div>

    <!-- Articles -->
    <div class="divider"></div>

    <div class="article">
        <strong>Article 1 :</strong> Les enseignants ci-dessous désignés sont autorisés à effectuer un voyage d'études
        à <strong>{{ $arrete->voyage->destination }}</strong> du
        <strong>{{ \Carbon\Carbon::parse($arrete->voyage->date_debut)->format('d/m/Y') }}</strong> au
        <strong>{{ \Carbon\Carbon::parse($arrete->voyage->date_fin)->format('d/m/Y') }}</strong>.
    </div>

    <!-- Liste bénéficiaires -->
    <table class="beneficiaires-table">
        <thead>
            <tr>
                <th>N°</th>
                <th>Prénom et Nom</th>
                <th>UFR / Département</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 1; @endphp
            @foreach($arrete->voyage->beneficiaires as $b)
                @if($b->dans_liste_definitive)
                <tr>
                    <td>{{ $i++ }}</td>
                    <td>{{ $b->enseignant->prenom }} {{ $b->enseignant->nom }}</td>
                    <td>{{ $b->enseignant->ufr }}</td>
                </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <div class="article">
        <strong>Article 2 :</strong> Il leur sera alloué une contribution de
        <strong>{{ number_format($arrete->montant_billet, 0, ',', ' ') }} francs CFA</strong>
        pour l'achat du billet d'avion, ainsi qu'une indemnité forfaitaire de
        <strong>{{ number_format($arrete->montant_indemnite, 0, ',', ' ') }} francs CFA</strong>
        par personne.
    </div>

    <div class="article">
        <strong>Article 3 :</strong> Le présent arrêté sera enregistré et communiqué partout où besoin sera.
    </div>

    <!-- Signature -->
    <div class="signature-section">
        <div class="signature-box">
            <p><strong>LE RECTEUR</strong></p>
            <br><br><br>
            <p>{{ $arrete->recteur->prenom ?? '' }} {{ $arrete->recteur->nom ?? '' }}</p>
        </div>
    </div>

    <!-- Pied de page -->
    <div class="footer">
        Tél. : (221) 33 973 30 86 // Fax : (221) 33 973 30 93 // B.P. : 30 – Bambey (République du Sénégal)<br>
        Internet : www.uadb.sn // Courriel : rectorat@uadb.edu.sn
    </div>

</body>
</html>