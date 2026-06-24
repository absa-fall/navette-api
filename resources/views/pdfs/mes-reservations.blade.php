<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes réservations</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1a1a1a; padding: 30px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #1e3a8a; padding-bottom: 12px; }
        .header h1 { font-size: 16px; color: #1e3a8a; margin-bottom: 4px; }
        .header p { font-size: 11px; color: #555; }
        .user-info { background: #f3f6fb; border-radius: 6px; padding: 10px 14px; margin-bottom: 16px; }
        .user-info p { margin: 2px 0; }
        .user-info .label { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1e3a8a; color: white; padding: 6px 8px; text-align: left; font-size: 10px; }
        td { padding: 6px 8px; border-bottom: 1px solid #e2e2e2; font-size: 10px; }
        tr:nth-child(even) { background: #f8f9fb; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
        .badge-confirmee { background: #d1fae5; color: #065f46; }
        .badge-attente { background: #fef9c3; color: #854d0e; }
        .badge-terminee { background: #dbeafe; color: #1e40af; }
        .badge-refusee, .badge-annulee { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #888; border-top: 1px solid #ddd; padding-top: 10px; }
        .total-row { font-weight: bold; background: #eef2f9 !important; }
    </style>
</head>
<body>

    <div class="header">
        <h1>UNIVERSITE ALIOUNE DIOP DE BAMBEY</h1>
        <p>Historique des réservations de navette</p>
        <p>Genere le {{ \Carbon\Carbon::now()->format('d/m/Y à H:i') }}</p>
    </div>

    <div class="user-info">
        <p><span class="label">Nom :</span> <strong>{{ $user->prenom }} {{ $user->nom }}</strong></p>
        <p><span class="label">Email :</span> {{ $user->email }}</p>
        <p><span class="label">UFR :</span> {{ $user->ufr ?? 'N/A' }}</p>
        <p><span class="label">Categorie :</span> {{ $user->type_profil ?? 'N/A' }} ({{ $user->statut ?? 'N/A' }})</p>
        <p><span class="label">QR code personnel :</span> {{ $user->qr_code ?? 'N/A' }}</p>
        <p><span class="label">Nombre total de reservations :</span> {{ count($reservations) }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Heure</th>
                <th>Trajet</th>
                <th>Sens</th>
                <th>Statut</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach($reservations as $r)
            <tr>
                <td>{{ \Carbon\Carbon::parse($r->date_reservation)->format('d/m/Y') }}</td>
                <td>{{ $r->heure_reservation }}</td>
                <td>{{ $r->ville_depart }} &rarr; {{ $r->ville_arrivee }}</td>
                <td>{{ ucfirst($r->trajet_sens ?? $r->type_trajet) }}</td>
                <td>
                    @php
                        $badgeClass = match($r->statut) {
                            'confirmee' => 'badge-confirmee',
                            'terminee' => 'badge-terminee',
                            'refusee', 'annulee' => 'badge-refusee',
                            default => 'badge-attente',
                        };
                        $statutLabel = match($r->statut) {
                            'confirmee' => 'Confirmee',
                            'terminee' => 'Terminee',
                            'refusee' => 'Refusee',
                            'annulee' => 'Annulee',
                            default => 'En attente',
                        };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $statutLabel }}</span>
                </td>
                <td>{{ number_format($r->montant_retenue, 0, ',', ' ') }} FCFA</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="5" style="text-align: right;">Total general</td>
                <td>{{ number_format($reservations->sum('montant_retenue'), 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Tel. : (221) 33 973 30 86 // B.P. : 30 - Bambey (Republique du Senegal)<br>
        Internet : www.uadb.edu.sn // Courriel : contact@uadb.edu.sn
    </div>

</body>
</html>