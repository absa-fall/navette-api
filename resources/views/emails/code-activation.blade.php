<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body style="font-family: Arial, sans-serif; background: #f8fafc; padding: 30px; margin: 0;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
        <div style="background: linear-gradient(135deg, #1e3a8a, #172554); padding: 24px; text-align: center;">
            <h1 style="color: #ffffff; font-size: 18px; margin: 0;">UADB Mobilité</h1>
            <p style="color: #bfdbfe; font-size: 12px; margin: 4px 0 0;">Université Alioune Diop de Bambey</p>
        </div>

        <div style="padding: 30px;">
            <p style="font-size: 14px; color: #1e293b;">
                Bonjour {{ $enseignant->prenom }} {{ $enseignant->nom }},
            </p>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                Le Vice-Recteur vous a ajouté sur la plateforme UADB Mobilité pour la gestion des voyages d'études.
                Utilisez le code ci-dessous pour activer votre compte et définir votre mot de passe.
            </p>

            <div style="background: #f1f5f9; border-radius: 12px; padding: 20px; text-align: center; margin: 24px 0;">
                <p style="font-size: 12px; color: #64748b; margin: 0 0 8px;">Votre code d'activation</p>
                <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #1d4ed8; margin: 0;">
                    {{ $code }}
                </p>
            </div>

            <p style="font-size: 13px; color: #94a3b8;">
                Ce code est valable 48 heures. Votre email de connexion sera : <strong>{{ $enseignant->email }}</strong>
            </p>

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ config('app.frontend_url', 'http://localhost:5173') }}/activer-compte"
                   style="display: inline-block; background: #1d4ed8; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 10px; font-size: 14px; font-weight: bold;">
                    Activer mon compte
                </a>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 16px; text-align: center; font-size: 11px; color: #94a3b8;">
            UADB Mobilité — Université Alioune Diop de Bambey
        </div>
    </div>
</body>
</html>