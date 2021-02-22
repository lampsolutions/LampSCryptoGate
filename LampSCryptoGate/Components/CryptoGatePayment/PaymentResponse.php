<?php

namespace LampSCryptoGate\Components\CryptoGatePayment;

class PaymentResponse
{
    /**
     * @var int
     */
    public $transactionId;

    /**
     * @var string
     */
    public $token;

    /**
     * @var string
     */
    public $status;

    /**
     * @var int
     */
    public $inBlock;
}
