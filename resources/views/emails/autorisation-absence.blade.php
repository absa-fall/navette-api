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

        <p>Bonjour {{ $autorisation->nom_demandeur }},</p>

        <p>
            Nous avons le plaisir de vous informer que votre demande d'autorisation d'absence
            N° <strong>{{ $autorisation->numero }}</strong> a été approuvée et signée par le Recteur.
        </p>

        <p>
            Lieu du déplacement : <strong>{{ $autorisation->lieu_deplacement }}</strong><br>
            Période : du {{ \Carbon\Carbon::parse($autorisation->periode_debut)->format('d/m/Y') }}
            au {{ \Carbon\Carbon::parse($autorisation->periode_fin)->format('d/m/Y') }}<br>
            Motif : {{ $autorisation->motif_mission }}
        </p>

        <p>
            Veuillez trouver en pièce jointe le document officiel de votre autorisation d'absence.
            Vous pouvez également le consulter et le télécharger depuis votre espace personnel sur la plateforme.
        </p>

        <p style="margin-top: 32px;">Cordialement,<br>Le Rectorat — Université Alioune Diop</p>

        <hr style="margin-top: 32px; border: none; border-top: 1px solid #e5e7eb;">
        <p style="font-size: 11px; color: #9ca3af; text-align: center;">
            (221) 33 973 30 86 — B.P. : 30 – Bambey (République du Sénégal) — www.uadb.sn
        </p>

    </div>

</body>
</html>