<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\Hotel;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ChatbotService
{
    private const MOTS_CLES_AVIS = [
        'laisser un avis', 'publier un avis', 'donner un avis',
        'mon avis', 'mon expérience', 'mon experience',
        'mon séjour', 'mon sejour', 'commentaire',
    ];

    private Client $http;

    public function __construct()
    {
        $this->http = new Client();
    }

    // ════════════════════════════════════════════════════════
    // POINT D'ENTRÉE : détecte l'intention et route
    // ════════════════════════════════════════════════════════

    public function traiter(string $message, ?User $user): array
    {
        if ($this->estUnAvis($message)) {
            return $this->traiterAvis($message, $user);
        }

        return $this->traiterRecommandation($message);
    }

    // ════════════════════════════════════════════════════════
    // DÉTECTION : le message parle-t-il d'un avis ?
    // ════════════════════════════════════════════════════════

    private function estUnAvis(string $message): bool
    {
        $lower = mb_strtolower($message);
        foreach (self::MOTS_CLES_AVIS as $mot) {
            if (str_contains($lower, $mot)) {
                return true;
            }
        }
        return false;
    }

    // ════════════════════════════════════════════════════════
    // CAS 1 : Soumission d'un avis via le chatbot
    // ════════════════════════════════════════════════════════

    private function traiterAvis(string $message, ?User $user): array
    {
        $hotels = Hotel::where('is_active', true)->get(['id', 'nom']);

        $listeHotels = $hotels->map(fn($h) => "ID {$h->id} : {$h->nom}")->implode("\n");

        $systemPrompt = "Tu es un assistant qui analyse les avis hôteliers.
L'utilisateur veut laisser un avis sur un hôtel.

Voici la liste des hôtels disponibles :
{$listeHotels}

Ta tâche :
1. Identifie l'hôtel mentionné (cherche le nom le plus proche dans la liste)
2. Extrais la note sur 5 (1 à 5). Si non précisée, déduis-la du ton du message.
3. Rédige un commentaire professionnel de 2-3 phrases basé sur le message.

Retourne UNIQUEMENT ce JSON strict, sans texte autour :
{
  \"type\": \"avis\",
  \"hotel_id\": <nombre>,
  \"note\": <nombre entre 1 et 5>,
  \"commentaire\": \"...\"
}";

        $body = $this->appelGroq($systemPrompt, $message);

        // Nettoyer et décoder le JSON
        $content = preg_replace('/```json\s*|\s*```/', '', $body['choices'][0]['message']['content']);
        $parsed  = json_decode(trim($content), true);

        if (
            !isset($parsed['type'], $parsed['hotel_id'], $parsed['note'], $parsed['commentaire'])
            || $parsed['type'] !== 'avis'
        ) {
            return [
                'success' => false,
                'message' => "Je n'ai pas pu analyser votre avis. Pouvez-vous préciser le nom de l'hôtel et votre note sur 5 ?",
                'hotels'  => [],
            ];
        }

        $hotel = Hotel::find((int) $parsed['hotel_id']);

        if (!$hotel) {
            return [
                'success' => false,
                'message' => "L'hôtel mentionné est introuvable dans notre système.",
                'hotels'  => [],
            ];
        }

        $note = max(1, min(5, (int) $parsed['note']));

        Avis::updateOrCreate(
            ['user_id' => $user?->id, 'hotel_id' => $hotel->id],
            ['note'    => $note,      'commentaire' => $parsed['commentaire']]
        );

        Log::info("Avis chatbot publié : user={$user?->id} hotel={$hotel->id} note={$note}");

        return [
            'success' => true,
            'message' => "Merci ! Votre avis a bien été publié sur l'hôtel **{$hotel->nom}** avec la note de {$note}/5. 🌟",
            'hotels'  => [],
        ];
    }

    // ════════════════════════════════════════════════════════
    // CAS 2 : Recommandation hôtelière (comportement normal)
    // ════════════════════════════════════════════════════════

    private function traiterRecommandation(string $message): array
    {
        $hotels = Hotel::with(['typesChambre', 'services', 'avis'])
            ->where('is_active', true)
            ->get()
            ->map(fn($hotel) => [
                'id'           => $hotel->id,
                'nom'          => $hotel->nom,
                'ville'        => $hotel->ville,
                'categorie'    => $hotel->categorie,
                'description'  => $hotel->description_detaillee,
                'prix_min'     => $hotel->typesChambre->min('prix_base'),
                'prix_max'     => $hotel->typesChambre->max('prix_base'),
                'services'     => $hotel->services->pluck('nom'),
                'note_moyenne' => round($hotel->avis->avg('note'), 1),
            ]);

        $systemPrompt = "Tu es un assistant de réservation hôtelière
pour l'application Hôtelière 2.0 au Maroc.
Tu aides les clients à trouver le meilleur hôtel
selon leurs besoins.

Voici les hôtels disponibles :
" . $hotels->toJson() . "

RÈGLES :
- Réponds TOUJOURS en français
- Recommande MAXIMUM 3 hôtels
- Explique pourquoi chaque hôtel correspond
- Sois chaleureux et professionnel

FORMAT DE RÉPONSE OBLIGATOIRE (JSON strict) :
{
  \"message\": \"Ta réponse ici\",
  \"hotels_ids\": [1, 3, 7]
}
Ne retourne QUE ce JSON, rien d'autre.";

        $body = $this->appelGroq($systemPrompt, $message);

        $content = preg_replace('/```json\s*|\s*```/', '', $body['choices'][0]['message']['content']);
        $parsed  = json_decode(trim($content), true);

        $hotelsRecommandes = Hotel::with(['typesChambre', 'services'])
            ->whereIn('id', $parsed['hotels_ids'] ?? [])
            ->get();

        return [
            'success' => true,
            'message' => $parsed['message'] ?? 'Voici mes recommandations.',
            'hotels'  => $hotelsRecommandes,
        ];
    }

    // ════════════════════════════════════════════════════════
    // UTILITAIRE : appel Groq API
    // ════════════════════════════════════════════════════════

    private function appelGroq(string $systemPrompt, string $userMessage): array
    {
        $response = $this->http->post('https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => 'llama-3.3-70b-versatile',
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userMessage],
                ],
                'max_tokens'  => 1024,
                'temperature' => 0.7,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }
}
