<?php
// src/adapter/ThirdPartyPayment.php
// Simula una biblioteca de terceros con interfaz incompatible.

class ThirdPartyPayment
{
    // El proveedor espera credenciales y un "payload" y devuelve un array con status
    public function makePayment(array $credentials, array $payload)
    {
        // Simular procesamiento y respuesta
        if (empty($credentials['api_key'])) {
            return ['status' => 'ERROR', 'message' => 'Missing API key'];
        }

        if (($payload['amount'] ?? 0) <= 0) {
            return ['status' => 'ERROR', 'message' => 'Invalid amount'];
        }

        $tx = 'TX-' . uniqid();
        return ['status' => 'SUCCESS', 'transaction_id' => $tx];
    }
}