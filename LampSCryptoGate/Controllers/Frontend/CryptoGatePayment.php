<?php

use LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse;
use LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService;


class Shopware_Controllers_Frontend_CryptoGatePayment extends Shopware_Controllers_Frontend_Payment
{
    const PAYMENTSTATUSPAID = 12;

    public function preDispatch()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSCryptoGate'];
        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    public function getVersion(){
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSCryptoGate'];
        $filename=$plugin->getPath().'/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        /**
         * Check if one of the payment methods is selected. Else return to default controller.
         */
        switch ($this->getPaymentShortName()) {
            case 'cryptogate_payment':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            case 'cryptogate_payment_btc':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            case 'cryptogate_payment_ltc':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            case 'cryptogate_payment_dash':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            case 'cryptogate_payment_bch':
                return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
            default:
                return $this->redirect(['controller' => 'checkout']);
        }
    }

    /**
     * Gateway action method.
     *
     * Collects the payment information and transmit it to the payment provider.
     */
    public function gatewayAction()
    {
        $paymentUrl = $this->getPaymentUrl();

        $this->redirect($paymentUrl);
    }

    /**
     * Direct action method.
     *
     * Collects the payment information and transmits it to the payment provider.
     */
    public function directAction()
    {
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

        $paymentUrl = $this->getPaymentUrl();


        $version = Shopware()->Config()->get( 'Version' );
        if($version < '5.6') {
            $this->saveOrder(
                end(explode("/", $paymentUrl)),
                $service->createPaymentToken($this->getPaymentData())
            );
        }
        $this->redirect($paymentUrl);
    }

    public function callbackAction(){

        /** @var CryptoGatePaymentService $service */
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

        /** @var PaymentResponse $response */
        $response = $service->createPaymentResponse($this->Request());
        $token = $service->createPaymentToken($this->getPaymentData());


        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');
            return;
        }

        if (!$service->validatePayment($response)) {
            $this->forward('cancel');
            return;
        }

        switch ($response->status) {
            case 'Paid':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    /**
     * Return action method
     *
     * Reads the transactionResult and represents it for the customer.
     */
    public function returnAction()
    {
        /** @var CryptoGatePaymentService $service */
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

        /** @var PaymentResponse $response */
        $response = $service->createPaymentResponse($this->Request());
        $token = $service->createPaymentToken($this->getPaymentData());

        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');
            return;
        }

        if (!$service->validatePayment($response)) {
            $this->forward('cancel');
            return;
        }

        switch ($response->status) {
            case 'Paid':
                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    /**
     * Cancel action method
     */
    public function cancelAction()
    {
    }

    /**
     * Creates the url parameters
     */
    private function getPaymentData()
    {

        $router = $this->Front()->Router();
        $user = $this->getUser();

        $paymentId = $user['additional']['payment']['id'];
        $billing = $user['billingaddress'];

        $version = Shopware()->Config()->get( 'Version' );
        $returnParameters = [
            'action' => 'return',
            'forceSecure' => true,
        ];
        if($version >= '5.6') {
            $shopware_token = $this->get('shopware\components\cart\paymenttokenservice')->generate();
            $returnParameters[\Shopware\Components\Cart\PaymentTokenService::TYPE_PAYMENT_TOKEN]=$shopware_token;
        }


        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'first_name' => $billing['firstname'],
            'last_name' => $billing['lastname'],
            'payment_id' => $paymentId,
            'email' => @$user['additional']['user']['email'],
            'return_url' => $router->assemble($returnParameters),
            'callback_url' => $router->assemble(['action' => 'callback', 'forceSecure' => true]),
            'cancel_url' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'seller_name' => Shopware()->Config()->get('company'),
            'memo' => 'Ihr Einkauf bei '.$_SERVER['SERVER_NAME']
        ];


        switch ($this->getPaymentShortName()) {
            case 'cryptogate_payment_btc':
                $parameter['selected_currencies'] = 'BTC';
                break;
            case 'cryptogate_payment_ltc':
                $parameter['selected_currencies'] = 'LTC';
                break;
            case 'cryptogate_payment_dash':
                $parameter['selected_currencies'] = 'DASH';
                break;
            case 'cryptogate_payment_bch':
                $parameter['selected_currencies'] = 'BCH';
                break;
        }

        return $parameter;
    }

    /**
     * Returns the URL of the payment provider. This has to be replaced with the real payment provider URL
     *
     * @return string
     */
    protected function getPaymentUrl()
    {
        /** @var CryptoGatePaymentService $service */
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');
        $payment_url = $service->createPaymentUrl($this->getPaymentData(),$this->getVersion());
        return $payment_url;
    }
}