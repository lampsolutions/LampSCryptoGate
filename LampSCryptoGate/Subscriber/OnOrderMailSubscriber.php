<?php

namespace LampSCryptoGate\Subscriber;

use Enlight\Event\SubscriberInterface;
use LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService;
use Shopware\Components\CacheManager;
use Shopware\Components\StateTranslatorService;
use Shopware_Controllers_Backend_Config;

class OnOrderMailSubscriber implements SubscriberInterface {

    public function __construct() {}

    public static function getSubscribedEvents() {
        return [
            'Shopware_Modules_Order_SendMail_FilterVariables' => 'onSaveOrder'
        ];
    }

    public function onSaveOrder(\Enlight_Event_EventArgs $args) {
        $orderAttributes = $args->getReturn();

        // Check for CryptoGate Payment Method
        switch (@$orderAttributes['additional']['payment']['name']) {
            case 'cryptogate_payment':
            case 'cryptogate_payment_btc':
            case 'cryptogate_payment_ltc':
            case 'cryptogate_payment_dash':
            case 'cryptogate_payment_bch':
                break;
            default:
                $args->setReturn($orderAttributes);
                return;
        }

        // Feature enabled or not
        $buttonEnabled = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'pay_now_btn');
        if(!$buttonEnabled) {
            $args->setReturn($orderAttributes);
            return;
        }

        /**
         * @var $cryptoGateService CryptoGatePaymentService
         */
        $cryptoGateService = Shopware()->Container()->get('crypto_gate.crypto_gate_payment_service');

        try {
            // Build Payment Url
            $apiUrl = rtrim(Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_url'), "/");
            $cryptoGateUUid = $orderAttributes['sBookingID'];
            $paymentUrl = $apiUrl.'/payments/'.$cryptoGateUUid;

            if(@$orderAttributes['additional']['payment']['additionaldescription']) {
                $data = $orderAttributes['additional']['payment']['additionaldescription'];
                $data .= '<br><a href="'.$paymentUrl.'"><img src="data:image/gif;base64,R0lGODlhyQBCANUAABDVnBjVnBjenCDenCDepCnepDHepDnepDnerEHerErerErmrErmtFLmtFrmtFrmvWLmvWrmvXPmxXvmxXvuxYPuxYPuzYvuzZTuzZTu1Zzu1aTu1aTu3qT23qz23rT23r325sX25s325s327tX27tX/7t7/7t7/9ub/9u7/9u7///b//////wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAEAAC0ALAAAAADJAEIAAAb/wIBwSCwaj8ikcslsOp/QqHRKrVqv2Kx2y+16v+CweEwum8/otHrNbrvf8Lh8Tq/b7/i8fs/v+/+AgYKDhIWGh4iJiouMjY6PkJGSk5SVlpeYmZqbnJ2en6BMHiykpSkiFwZro6UaRyGkI6FhrKW2KxFqtaQIRrAssrNfu7alDWnEIL6xwsOlEwoNEyilIcjFLMdEv8HNXLXaAQm21ywppSRF3N5d4ETUpKpCCRghKSsrJhsKRCOlEkUwlNqQhNUHEf+2MSuiYIMJfCT2EdlQip+QArYADjnHwoQmdxtLFRASAVupC0MkVCtiomJBUiAUmBowZB0RDSZZYBgC4eSQ/5KlPgyRSWpnJpABHJgaMoDjChQrbFlsKnLIOFLpXrIQEeBDKaMBbAoRmFOnkAFRtw6hWArFEAvGPj5TAEFDWhYEh0zQYDEAWRauhOAkpTHAhVIVlLBKZyDtCnliDdjCoCDB4VK9wpaiGYAaChC8hPxasYlYsRTykEiGaXWlkJakRmr16DeoENDAhlzGS+Sv0QmlHAQ4QOoDXBYAB9iWmxNFXyEILoAwcc+WtSEI44lbPvssRxb8cHfzSircdlJcAyBoFUAlC2ikPCQtlYt5qacgJHAeMjjndZLPGBbcEqzQ1l4pspDXTXYsZHZRW0PAlg55CQQQlVt/yXZUXEm4F//fBBAAxcJ/AVClFgmkpCAKKQYGgCIpEbC1IGZEYESKW4KV0hkLpAWQXQG/ZGWfeUYwWNhqIxbRH1FmEcgiEUrdKOMQ5GVDxFUsCBllNqRcR5YDHKFUGodIwMNCaj11WcR68dHoZEdF4MbjQgEAR0peY7F3VlqDiRnlkpwgdcSLLNSngJkkCpFdWum92eJ5tnRTwF0YJGCZLRUOgRts2tgIm4qBknlEfyyg4JSaRYhIWBMFGsEWgkTYmROeQshKyn6w3dmJoEYUYOZXFaBaxHcr7KfYk0VMGmkRwZpEqxBI5raWLRDsKuoRBmxATQogCLdaojnqyiqyRRxHp1UakHBbjwkeEBmhnkJ4yIKx7Fjxl7v1WtIYuflaogACEeQ6Qb+XRIsVwQUXQ0JqCE9SwEMriDBwwxRXbPHFGGes8cYcd+zxxyCHLPLIJJds8skop6zyyiy37PLLMPsRBAA7" /></a>';
                $orderAttributes['additional']['payment']['additionaldescription'] = $data;
            }
        } catch (\Exception $e) {
            if($cryptoGateService) {
                $cryptoGateService->logger->crit('CryptoGate-Error: '.$e->getMessage());
            }
        }

        $args->setReturn($orderAttributes);
    }

}