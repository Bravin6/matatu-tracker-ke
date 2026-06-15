<?php
// ============================================================
// includes/mpesa.php  — Safaricom Daraja API Helper
// Handles: STK Push, C2B callbacks, token generation
// ============================================================

class Mpesa {

    // ── Daraja credentials (set in config.php) ────────────────
    private string $consumerKey;
    private string $consumerSecret;
    private string $shortcode;
    private string $passkey;
    private string $callbackUrl;
    private bool   $sandbox;
    private string $baseUrl;

    public function __construct() {
        $this->consumerKey    = MPESA_CONSUMER_KEY;
        $this->consumerSecret = MPESA_CONSUMER_SECRET;
        $this->shortcode      = MPESA_SHORTCODE;
        $this->passkey        = MPESA_PASSKEY;
        $this->callbackUrl    = MPESA_CALLBACK_URL;
        $this->sandbox        = MPESA_SANDBOX;
        $this->baseUrl        = $this->sandbox
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    // ── 1. Generate OAuth access token ────────────────────────
    public function getAccessToken(): string {
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ["Authorization: Basic $credentials"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException("Daraja auth failed (HTTP $httpCode): $response");
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new RuntimeException('No access_token in Daraja response');
        }
        return $data['access_token'];
    }

    // ── 2. STK Push (Lipa Na M-PESA Online) ──────────────────
    // Prompts user's phone with a payment dialog
    public function stkPush(string $phone, int $amount, string $accountRef, string $description): array {
        $token     = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        // Normalise phone: 07xxxxxxxx → 2547xxxxxxxx
        $phone = $this->normalisePhone($phone);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline',
            'Amount'            => $amount,           // whole KES, no decimals
            'PartyA'            => $phone,
            'PartyB'            => $this->shortcode,
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->callbackUrl,
            'AccountReference'  => $accountRef,       // e.g. "MatatuTrack"
            'TransactionDesc'   => $description,      // e.g. "Wallet Top-Up"
        ];

        $ch = curl_init($this->baseUrl . '/mpesa/stkpush/v1/processrequest');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?? [];
        $data['_http_code'] = $httpCode;
        return $data;
    }

    // ── 3. Query STK Push transaction status ─────────────────
    public function stkQuery(string $checkoutRequestId): array {
        $token     = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $password  = base64_encode($this->shortcode . $this->passkey . $timestamp);

        $payload = [
            'BusinessShortCode' => $this->shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
        ];

        $ch = curl_init($this->baseUrl . '/mpesa/stkpushquery/v1/query');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer $token",
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    // ── Helpers ───────────────────────────────────────────────
    private function normalisePhone(string $phone): string {
        $phone = preg_replace('/\D/', '', $phone); // strip non-digits
        if (str_starts_with($phone, '0'))   return '254' . substr($phone, 1);
        if (str_starts_with($phone, '+254')) return substr($phone, 1);
        return $phone; // already 254...
    }
}
