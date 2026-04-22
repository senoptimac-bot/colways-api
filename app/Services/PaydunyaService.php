<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service PayDunya — passerelle de paiement sénégalaise.
 *
 * Supporte : Wave, Orange Money, Free Money, carte bancaire.
 * Documentation officielle : https://paydunya.com/developers
 *
 * ─── Activation ──────────────────────────────────────────────────────────────
 * Ajouter dans .env :
 *
 *   PAYDUNYA_MASTER_KEY=votre_master_key
 *   PAYDUNYA_PRIVATE_KEY=votre_private_key
 *   PAYDUNYA_TOKEN=votre_token
 *   PAYDUNYA_MODE=test          # "test" ou "live"
 *
 * Sans ces clés, le BoostController utilise automatiquement le paiement manuel.
 *
 * ─── Flux de paiement ────────────────────────────────────────────────────────
 * 1. createInvoice()     → retourne { token, checkout_url }
 * 2. App ouvre checkout_url dans le navigateur
 * 3. Utilisateur paie sur Wave/OM
 * 4. PayDunya appelle POST /api/webhooks/paydunya (IPN)
 * 5. verifyIPN() valide le hash
 * 6. Le boost est activé automatiquement
 */
class PaydunyaService
{
    // URLs des API PayDunya
    private const API_TEST = 'https://app.paydunya.com/sandbox-api/v1';
    private const API_LIVE = 'https://app.paydunya.com/api/v1';

    private string $masterKey;
    private string $privateKey;
    private string $token;
    private string $baseUrl;
    private bool   $isLive;

    public function __construct()
    {
        $this->masterKey  = config('services.paydunya.master_key', '');
        $this->privateKey = config('services.paydunya.private_key', '');
        $this->token      = config('services.paydunya.token', '');
        $this->isLive     = config('services.paydunya.mode', 'test') === 'live';
        $this->baseUrl    = $this->isLive ? self::API_LIVE : self::API_TEST;
    }

    /**
     * Vérifie si PayDunya est configuré (clés présentes dans .env).
     * Utilisé pour le switch automatique manuel ↔ PayDunya.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->masterKey)
            && ! empty($this->privateKey)
            && ! empty($this->token);
    }

    /**
     * Crée une facture de paiement PayDunya.
     *
     * @param array $params [
     *   'amount'      => int,    // Montant en FCFA
     *   'description' => string, // Ex : "Boost article 24h — Colways"
     *   'reference'   => string, // Ex : "BOOST-42"
     *   'callback_url'=> string, // Webhook URL (IPN)
     *   'return_url'  => string, // Redirection après paiement réussi
     *   'cancel_url'  => string, // Redirection après annulation
     * ]
     *
     * @return array ['token' => string, 'checkout_url' => string]
     * @throws \RuntimeException si l'API PayDunya retourne une erreur
     */
    public function createInvoice(array $params): array
    {
        $payload = [
            'invoice' => [
                'total_amount' => $params['amount'],
                'description'  => $params['description'],
            ],
            'store' => [
                'name'    => 'Colways',
                'tagline' => 'La friperie de Colobane',
                'phone'   => config('app.colways_phone', ''),
                'website' => 'https://colways.sn',
                'logo_url'=> config('app.url') . '/logo.png',
            ],
            'actions' => [
                'callback_url' => $params['callback_url'],
                'return_url'   => $params['return_url'],
                'cancel_url'   => $params['cancel_url'],
            ],
            'custom_data' => [
                'colways_reference' => $params['reference'],
            ],
        ];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->baseUrl}/checkout-invoice/create", $payload);

        if (! $response->successful()) {
            Log::error('PayDunya createInvoice échec', [
                'status'  => $response->status(),
                'body'    => $response->json(),
                'reference' => $params['reference'],
            ]);
            throw new \RuntimeException(
                'PayDunya indisponible. Veuillez réessayer dans quelques minutes.'
            );
        }

        $data = $response->json();

        if (($data['response_code'] ?? '') !== '00') {
            Log::error('PayDunya createInvoice erreur métier', ['data' => $data]);
            throw new \RuntimeException(
                $data['response_text'] ?? 'Erreur PayDunya inconnue.'
            );
        }

        return [
            'token'        => $data['token'],
            'checkout_url' => "https://app.paydunya.com/checkout/invoice/{$data['token']}",
        ];
    }

    /**
     * Vérifie la signature d'un webhook IPN PayDunya.
     *
     * PayDunya envoie le PAYDUNYA_MASTER_KEY en hash SHA512 dans le header
     * X-PAYDUNYA-MASTER-KEY pour prouver l'authenticité de la requête.
     *
     * @param string $receivedHash — valeur du header X-PAYDUNYA-MASTER-KEY
     * @return bool
     */
    public function verifyIPN(string $receivedHash): bool
    {
        $expected = hash('sha512', $this->masterKey);
        return hash_equals($expected, $receivedHash);
    }

    /**
     * Vérifie le statut d'une facture via son token.
     * Utilisé pour le polling depuis l'app ("Vérifier mon paiement").
     *
     * @param string $token — token retourné par createInvoice()
     * @return array ['status' => string, 'paid' => bool]
     */
    public function checkStatus(string $token): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->baseUrl}/checkout-invoice/confirm/{$token}");

        if (! $response->successful()) {
            return ['status' => 'unknown', 'paid' => false];
        }

        $data   = $response->json();
        $status = $data['status'] ?? 'pending';
        $paid   = $status === 'completed';

        return [
            'status' => $status,
            'paid'   => $paid,
        ];
    }

    /**
     * Headers requis par l'API PayDunya.
     */
    private function headers(): array
    {
        return [
            'PAYDUNYA-MASTER-KEY'  => $this->masterKey,
            'PAYDUNYA-PRIVATE-KEY' => $this->privateKey,
            'PAYDUNYA-TOKEN'       => $this->token,
            'Content-Type'         => 'application/json',
        ];
    }
}
