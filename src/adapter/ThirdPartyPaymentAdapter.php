<?php
// src/adapter/ThirdPartyPaymentAdapter.php
require_once __DIR__ . '/ThirdPartyPayment.php';

class ThirdPartyPaymentAdapter
{
    private $client;
    private $credentials;

    public function __construct(array $credentials = [])
    {
        $this->client = new ThirdPartyPayment();
        $this->credentials = $credentials;
    }

    // Interfaz que nuestro servicio de billing espera
    // Devuelve ['ok' => bool, 'transaction_id' => string|null, 'msg' => string|null]
    public function processPayment(int $orderId, float $amount): array
    {
        // Mapear a la interfaz del tercero
        $payload = [
            'order_reference' => $orderId,
            'amount' => $amount,
            'currency' => 'MXN'
        ];

        $response = $this->client->makePayment($this->credentials, $payload);

        if (!is_array($response) || !isset($response['status'])) {
            return ['ok' => false, 'transaction_id' => null, 'msg' => 'Invalid third party response'];
        }

        if ($response['status'] === 'SUCCESS') {
            return ['ok' => true, 'transaction_id' => $response['transaction_id'] ?? null, 'msg' => null];
        }

        return ['ok' => false, 'transaction_id' => null, 'msg' => $response['message'] ?? 'Payment failed'];
    }
}