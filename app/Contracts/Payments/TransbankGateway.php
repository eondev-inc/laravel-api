<?php

namespace App\Contracts\Payments;

interface TransbankGateway
{
    /**
     * Initialize a Webpay Plus transaction.
     *
     * @param  string  $buyOrder  Unique buy order ID
     * @param  string  $sessionId  Session identifier
     * @param  float  $amount  Transaction amount
     * @param  string  $returnUrl  URL where Transbank will redirect after payment
     * @return array{token: string, url: string}
     */
    public function create(string $buyOrder, string $sessionId, float $amount, string $returnUrl): array;

    /**
     * Confirm/commit a Webpay Plus transaction.
     *
     * @param  string  $tokenWs  Token received from Transbank callback
     * @return array Transbank response with payment details
     */
    public function commit(string $tokenWs): array;
}
