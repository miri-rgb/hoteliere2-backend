<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de réservation</title>
    <style>
        /* Style général */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        /* Conteneur principal */
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* En-tête avec couleur */
        .header {
            background-color: #2C3E50;
            color: white;
            text-align: center;
            padding: 30px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.8;
        }

        /* Badge de confirmation */
        .badge {
            background-color: #27AE60;
            color: white;
            text-align: center;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
        }

        /* Corps de l'email */
        .body {
            padding: 30px;
        }

        .body p {
            color: #555;
            line-height: 1.6;
        }

        /* Tableau des détails */
        .details {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .details table {
            width: 100%;
            border-collapse: collapse;
        }

        .details table tr {
            border-bottom: 1px solid #eee;
        }

        .details table tr:last-child {
            border-bottom: none;
        }

        .details table td {
            padding: 12px 8px;
            color: #555;
        }

        .details table td:first-child {
            font-weight: bold;
            color: #2C3E50;
            width: 40%;
        }

        /* Prix total mis en valeur */
        .prix-total {
            background-color: #2C3E50;
            color: white;
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 20px;
            font-weight: bold;
        }

        /* Bouton */
        .btn {
            display: block;
            width: 200px;
            margin: 20px auto;
            padding: 15px;
            background-color: #E74C3C;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
        }

        /* Pied de page */
        .footer {
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 12px;
        }
    </style>
</head>
<body>

<div class="container">

    {{-- ══ EN-TÊTE ══ --}}
    <div class="header">
        <h1>🏨 Hôtelière 2.0</h1>
        <p>Votre plateforme de réservation hôtelière au Maroc</p>
    </div>

    {{-- ══ BADGE CONFIRMATION ══ --}}
    <div class="badge">
        ✅ Réservation créée avec succès !
    </div>

    {{-- ══ CORPS ══ --}}
    <div class="body">

        <p>Bonjour <strong>{{ $reservation->user->prenom }} {{ $reservation->user->nom }}</strong>,</p>

        <p>
            Nous avons bien reçu votre réservation. Voici le récapitulatif
            de votre séjour :
        </p>

        {{-- ══ DÉTAILS DE LA RÉSERVATION ══ --}}
        <div class="details">
            <table>
                <tr>
                    <td>🏨 Hôtel</td>
                    <td>{{ $reservation->chambre->hotel->nom }}</td>
                </tr>
                <tr>
                    <td>📍 Ville</td>
                    <td>{{ $reservation->chambre->hotel->ville }}</td>
                </tr>
                <tr>
                    <td>🛏️ Chambre</td>
                    <td>
                        N° {{ $reservation->chambre->numero }}
                        ({{ $reservation->chambre->typeChambre->nom }})
                    </td>
                </tr>
                <tr>
                    <td>📅 Arrivée</td>
                    <td>{{ \Carbon\Carbon::parse($reservation->date_arrivee)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>📅 Départ</td>
                    <td>{{ \Carbon\Carbon::parse($reservation->date_depart)->format('d/m/Y') }}</td>
                </tr>
                <tr>
                    <td>🌙 Durée</td>
                    <td>
                        {{ \Carbon\Carbon::parse($reservation->date_arrivee)->diffInDays($reservation->date_depart) }}
                        nuit(s)
                    </td>
                </tr>
                <tr>
                    <td>👥 Personnes</td>
                    <td>{{ $reservation->nb_personnes }} personne(s)</td>
                </tr>
                <tr>
                    <td>📋 Statut</td>
                    <td>
                        @if($reservation->statut === 'en_attente')
                            ⏳ En attente de confirmation
                        @elseif($reservation->statut === 'confirmee')
                            ✅ Confirmée
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>🔖 Référence</td>
                    <td>#RES-{{ str_pad($reservation->id, 6, '0', STR_PAD_LEFT) }}</td>
                </tr>
            </table>
        </div>

        {{-- ══ PRIX TOTAL ══ --}}
        <div class="prix-total">
            💰 Prix Total : {{ number_format($reservation->prix_total, 2) }} MAD
        </div>

        <p>
            Notre équipe va traiter votre demande dans les plus brefs délais.
            Vous recevrez un email de confirmation dès que votre réservation
            sera validée.
        </p>

        <p>
            Pour toute question, n'hésitez pas à nous contacter à
            <a href="mailto:contact@hoteliere.ma">contact@hoteliere.ma</a>
        </p>

    </div>

    {{-- ══ PIED DE PAGE ══ --}}
    <div class="footer">
        <p>© 2026 Hôtelière 2.0 — Tous droits réservés</p>
        <p>Maroc — contact@hoteliere.ma</p>
    </div>

</div>

</body>
</html>