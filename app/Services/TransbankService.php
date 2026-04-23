<?php

namespace App\Services;

use App\Contracts\Payments\TransbankGateway;
use Transbank\Webpay\WebpayPlus\Transaction;

class TransbankService implements TransbankGateway
{
    public function __construct(private Transaction $transaction) {}

    /**
     * Initialize a Webpay Plus transaction.
     *
     * @return array{token: string, url: string}
     */
    public function create(string $buyOrder, string $sessionId, float $amount, string $returnUrl): array
    {
        $response = $this->transaction->create($buyOrder, $sessionId, $amount, $returnUrl);

        return [
            'token' => $response->getToken(),
            'url' => $response->getUrl(),
        ];
    }

    /**
     * Confirm a Webpay Plus transaction.
     *
     * @return array<string, mixed>
     */
    public function commit(string $tokenWs): array
    {
        $response = $this->transaction->commit($tokenWs);

        return [
            'vci' => $response->getVci(),
            'amount' => $response->getAmount(),
            'status' => $response->getStatus(),
            'buy_order' => $response->getBuyOrder(),
            'session_id' => $response->getSessionId(),
            'response_code' => $response->getResponseCode(),
        ];
    }
}
