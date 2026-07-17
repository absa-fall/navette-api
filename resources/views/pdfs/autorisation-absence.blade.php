<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 13px; color: #000; padding: 40px; }

        .top-section { display: table; width: 100%; margin-bottom: 20px; }
        .top-left { display: table-cell; width: 60%; vertical-align: top; font-size: 11px; line-height: 1.6; }
        .top-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; font-size: 12px; line-height: 2.2; }

        .logo-block { text-align: center; margin-top: 10px; }
        .logo-block img { height: 55px; }
        .logo-block .rectorat { font-weight: bold; font-size: 11px; margin-top: 4px; }
        .logo-block .ds { font-size: 9px; }

        .recteur-title { font-style: italic; font-weight: bold; font-size: 12px; line-height: 1.3; }

        .titre {
            text-align: center; font-size: 16px; font-weight: bold;
            text-decoration: underline; letter-spacing: 0.5px;
            margin: 25px 0 30px;
        }

        .field { margin-bottom: 10px; font-size: 13px; line-height: 1.6; display: table; width: 100%; }
        .field-label { display: table-cell; width: 220px; }
        .field-value { display: table-cell; font-weight: bold; }

        .paragraphe { margin: 30px 0; font-size: 13px; line-height: 1.7; text-align: justify; }

        .signature-recteur { text-align: right; margin-top: 60px; font-size: 12px; }
        .signature-recteur strong { font-style: italic; }

        .footer { margin-top: 60px; border-top: 1px solid #000; padding-top: 8px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>

    <div class="top-section">
        <div class="top-left">
            <strong>REPUBLIQUE DU SENEGAL</strong><br>
            <em>Un Peuple-Un But-Une Foi</em><br>
            Ministère de l'Enseignement supérieur,<br>
            de la Recherche et de l'Innovation<br>
            <br>
            <strong>UNIVERSITE ALIOUNE DIOP</strong><br>
            <em style="font-size: 10px;">« L'excellence est ma constance, l'éthique ma vertu »</em>

            <div class="logo-block">
                @php
                    $logo = base64_encode(file_get_contents(public_path('logo-uadb.png')));
                @endphp
                <img src="data:image/png;base64,{{ $logo }}" />
                <div class="rectorat">RECTORAT</div>
                <div class="ds">DD/ms</div>
            </div>
        </div>

        <div class="top-right">
            <strong>N° {{ $autorisation->numero }} UAD/R/SG/DRH</strong><br>
            Bambey, le {{ \Carbon\Carbon::parse($autorisation->date_signature_recteur ?? now())->locale('fr')->isoFormat('D MMMM YYYY') }}<br><br>
            <span class="recteur-title">
                Le Recteur,<br>
                Président du Conseil académique
            </span>
        </div>
    </div>

    <div class="titre">AUTORISATION DE SORTIE DU TERRITOIRE</div>

    <div class="field">
        <span class="field-label">Enseignant :</span>
        <span class="field-value">{{ $autorisation->nom_demandeur }}</span>
    </div>
    <div class="field">
        <span class="field-label">UFR / Département :</span>
        <span class="field-value">{{ $autorisation->ufr_departement }}</span>
    </div>
    <div class="field">
        <span class="field-label">Motif de la mission :</span>
        <span class="field-value">{{ $autorisation->motif_mission }}</span>
    </div>
    <div class="field">
        <span class="field-label">Lieu du déplacement :</span>
        <span class="field-value">{{ $autorisation->lieu_deplacement }}</span>
    </div>
    <div class="field">
        <span class="field-label">Période :</span>
        <span class="field-value">
            {{ \Carbon\Carbon::parse($autorisation->periode_debut)->format('d/m/Y') }}
            &rarr;
            {{ \Carbon\Carbon::parse($autorisation->periode_fin)->format('d/m/Y') }}
        </span>
    </div>
    <div class="field">
        <span class="field-label">Organisme prenant en charge :</span>
        <span class="field-value">{{ $autorisation->organisme_charge }}</span>
    </div>

    <div class="paragraphe">
        Monsieur/Madame <strong>{{ $autorisation->nom_demandeur }}</strong> est autorisé(e) à sortir du
        territoire sénégalais pour des raisons professionnelles. Par conséquent, les autorités civiles
        et militaires des localités traversées sont priées de lui faciliter l'accomplissement de son voyage.
    </div>

    <div class="signature-recteur">
        <strong>Le Recteur</strong><br><br><br>
        {{ $autorisation->recteur->prenom ?? '' }} {{ $autorisation->recteur->nom ?? '' }}
    </div>

    <div class="footer">
        (221) 33 973 30 86 // Fax : (221) 33 973 30 93 // B.P. : 30 – Bambey (République du Sénégal)<br>
        Internet : www.uadb.sn // Courriel : rectorat@uadb.edu.sn
    </div>

</body>
</html>