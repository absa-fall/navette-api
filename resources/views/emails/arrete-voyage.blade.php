<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">

    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">

        <div style="text-align: center; margin-bottom: 24px;">
            <p style="font-weight: bold; margin: 0;">UNIVERSITÉ ALIOUNE DIOP</p>
            <p style="font-size: 13px; color: #6b7280; margin: 2px 0;">Rectorat</p>
        </div>

        <p>Bonjour {{ $enseignant->prenom }} {{ $enseignant->nom }},</p>

        <p>
            Nous avons le plaisir de vous informer que l'arrêté n° <strong>{{ $arrete->numero }}</strong>
            portant attribution de voyage d'études vous concernant a été signé par le Recteur
            le {{ \Carbon\Carbon::parse($arrete->date_arrete)->locale('fr')->isoFormat('D MMMM YYYY') }}.
        </p>

        <p>
            Destination : <strong>{{ $arrete->voyage->destination }}</strong><br>
            Période : du {{ \Carbon\Carbon::parse($arrete->voyage->date_debut)->format('d/m/Y') }}
            au {{ \Carbon\Carbon::parse($arrete->voyage->date_fin)->format('d/m/Y') }}
        </p>

        <p>
            Vous bénéficiez à ce titre d'une autorisation d'absence pour la période du voyage,
            d'une contribution de {{ number_format($arrete->montant_billet, 0, ',', ' ') }} francs CFA
            pour l'achat du billet, ainsi que d'une indemnité forfaitaire de
            {{ number_format($arrete->montant_indemnite, 0, ',', ' ') }} francs CFA.
        </p>

        <p>
            Vous pouvez consulter et télécharger le document officiel depuis votre espace personnel
            sur la plateforme.
        </p>

        <p style="margin-top: 32px;">Cordialement,<br>Le Rectorat — Université Alioune Diop</p>

        <hr style="margin-top: 32px; border: none; border-top: 1px solid #e5e7eb;">
        <p style="font-size: 11px; color: #9ca3af; text-align: center;">
            (221) 33 973 30 86 — B.P. : 30 – Bambey (République du Sénégal) — www.uadb.sn
        </p>

    </div>

</body>
</html>