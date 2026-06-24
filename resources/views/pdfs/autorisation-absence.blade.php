<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 13px; color: #000; padding: 40px; }

        .header { text-align: center; margin-bottom: 20px; line-height: 1.8; }
        .divider { border-top: 1px solid #000; margin: 12px 0; }

        .titre { text-align: center; font-size: 15px; font-weight: bold; margin: 20px 0 5px; }
        .numero { text-align: center; font-weight: bold; font-size: 13px; margin-bottom: 5px; }
        .separator { text-align: center; font-weight: bold; margin-bottom: 20px; }

        .field { margin-bottom: 12px; font-size: 13px; line-height: 1.7; }
        .field-label { font-weight: bold; }

        .avis-section { margin-top: 30px; }
        .avis-title { text-align: center; font-weight: bold; font-size: 12px; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 5px 0; margin-bottom: 20px; }

        .avis-grid { display: table; width: 100%; margin-bottom: 20px; }
        .avis-col { display: table-cell; width: 50%; vertical-align: top; padding: 0 10px; }
        .avis-col-title { font-weight: bold; text-decoration: underline; margin-bottom: 10px; font-size: 12px; }
        .avis-content { font-size: 11px; line-height: 1.8; }

        .signature-right { text-align: right; margin-top: 30px; font-size: 12px; }

        .footer { margin-top: 50px; border-top: 1px solid #000; padding-top: 8px; text-align: center; font-size: 10px; color: #555; }
    </style>
</head>
<body>

    <!-- En-tête -->
   <div class="header">
   @php
    $logo = base64_encode(file_get_contents(public_path('logo-uadb.png')));
@endphp
<img src="data:image/png;base64,{{ $logo }}" style="height: 70px; margin-bottom: 8px;" />
    <strong>RÉPUBLIQUE DU SÉNÉGAL</strong><br>
        Un Peuple - Un But - Une Foi<br>
        Ministère de l'Enseignement supérieur,<br>
        de la Recherche et de l'Innovation<br>
        ******<br>
        <strong>UNIVERSITÉ ALIOUNE DIOP</strong><br>
        <em>« L'excellence est ma constance, l'éthique ma vertu »</em><br><br>
        <strong>{{ $autorisation->ufr_departement }}</strong>
    </div>

    <div class="divider"></div>

    <!-- Titre -->
    <div class="titre">DEMANDE D'AUTORISATION D'ABSENCE N°</div>
    <div class="numero">{{ $autorisation->numero }}</div>
    <div class="separator">----------------</div>

    <!-- Corps -->
    <div class="field">
        <span class="field-label">DEMANDE D'AUTORISATION PRÉSENTÉE LE</span> :
        {{ \Carbon\Carbon::parse($autorisation->date_presentation)->locale('fr')->isoFormat('D MMMM YYYY') }}
    </div>

    <div class="field">
        <span class="field-label">PAR</span> : {{ $autorisation->nom_demandeur }}
    </div>

    <div class="field">
        <span class="field-label">FONCTION</span> : {{ $autorisation->fonction }}
    </div>

    <div class="field">
        <span class="field-label">UFR/DÉPARTEMENT</span> : {{ $autorisation->ufr_departement }}
    </div>

    <div class="field">
        <span class="field-label">MOTIF DE LA MISSION</span> : {{ $autorisation->motif_mission }}
    </div>

    <div class="field">
        <span class="field-label">LIEU DU DÉPLACEMENT</span> : {{ $autorisation->lieu_deplacement }}
    </div>

    <div class="field">
        <span class="field-label">PÉRIODE DU DÉPLACEMENT</span> :
        du {{ \Carbon\Carbon::parse($autorisation->periode_debut)->format('d/m/Y') }}
        au {{ \Carbon\Carbon::parse($autorisation->periode_fin)->format('d/m/Y') }}
    </div>

    <div class="field">
        <span class="field-label">ORGANISME PRENANT EN CHARGE LES FRAIS DE TRANSPORT ET DE SÉJOUR</span> :<br>
        <span style="padding-left: 20px;">{{ $autorisation->organisme_charge }}</span>
    </div>

    <!-- Signature enseignant -->
    <div class="signature-right">
        <strong>Signature</strong><br><br><br>
        {{ $autorisation->signature_enseignant ? $autorisation->nom_demandeur : '' }}
    </div>

    <!-- Avis -->
    <div class="avis-section">
        <div class="avis-title">AVIS DU</div>

        <div class="avis-grid">
            <div class="avis-col">
                <div class="avis-col-title">CHEF DE DÉPARTEMENT</div>
                <div class="avis-content">
                    Avis :
                    @if($autorisation->avis_chef_departement === 'favorable')
                        <strong>FAVORABLE</strong>
                    @elseif($autorisation->avis_chef_departement === 'defavorable')
                        <strong>DÉFAVORABLE</strong>
                    @else
                        En attente
                    @endif
                    <br>
                    @if($autorisation->commentaire_chef_departement)
                        "{{ $autorisation->commentaire_chef_departement }}"<br>
                    @endif
                    <br>
                    {{ $autorisation->chefDepartement->prenom ?? '' }} {{ $autorisation->chefDepartement->nom ?? '' }}<br>
                    @if($autorisation->date_avis_chef_departement)
                        {{ \Carbon\Carbon::parse($autorisation->date_avis_chef_departement)->format('d/m/Y') }}
                    @endif
                </div>
            </div>

            <div class="avis-col">
                <div class="avis-col-title">DIRECTEUR DE L'UFR</div>
                <div class="avis-content">
                    Avis :
                    @if($autorisation->avis_directeur_ufr === 'favorable')
                        <strong>FAVORABLE</strong>
                    @elseif($autorisation->avis_directeur_ufr === 'defavorable')
                        <strong>DÉFAVORABLE</strong>
                    @else
                        En attente
                    @endif
                    <br>
                    @if($autorisation->commentaire_directeur_ufr)
                        "{{ $autorisation->commentaire_directeur_ufr }}"<br>
                    @endif
                    <br>
                    {{ $autorisation->directeurUfr->prenom ?? '' }} {{ $autorisation->directeurUfr->nom ?? '' }}<br>
                    @if($autorisation->date_avis_directeur_ufr)
                        {{ \Carbon\Carbon::parse($autorisation->date_avis_directeur_ufr)->format('d/m/Y') }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Signature Recteur -->
        <div class="signature-right">
            <strong>LE RECTEUR</strong><br><br><br>
            {{ isset($autorisation->recteur) ? $autorisation->recteur->prenom . ' ' . $autorisation->recteur->nom : 'En attente de signature' }}<br>
            @if($autorisation->date_signature_recteur)
                {{ \Carbon\Carbon::parse($autorisation->date_signature_recteur)->format('d/m/Y') }}
            @endif
        </div>
    </div>

    <!-- Pied de page -->
    <div class="footer">
        (221) 33 973 30 86 // Fax : (221) 33 973 30 93 // B.P. : 30 – Bambey (République du Sénégal)<br>
        Internet : www.uadb.sn // Courriel : contact@uadb.edu.sn
    </div>

</body>
</html>