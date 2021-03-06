<?php

use LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;

class Shopware_Controllers_Backend_CryptoGatePaymentCheck extends \Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware
{


    public function preDispatch()
    {
        $this->get('template')->addTemplateDir(__DIR__ . '/../../Resources/views/');
    }

    public function indexAction()
    {
        if ($_POST["check_now"] == "check_now") {
            /**
             * @var $service \LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService
             */
            $service = $this->container->get('crypto_gate.crypto_gate_payment_service');
            $paymentData = $this->getPayment();

            if (false === $paymentData || filter_var($paymentData['payment_url'], FILTER_VALIDATE_URL) === false) {
                $this->View()->assign(['error' => "Could not generate Payment-URL please see logfile for possible Exceptions"]);
            } else {

                $this->View()->assign(['success' => "Payment-URL could be generated"]);
                $this->View()->assign(['payment_url' => $paymentData['payment_url']]);


                /** @var PaymentResponse $response */
                $response = new \LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse();
                $response->transactionId = $paymentData['uuid'];
                $response->token = $service->createPaymentToken($this->getPaymentData());


                $this->View()->assign(['status' => $service->validatePayment($response)]);
            }

        }
    }

    public function getWhitelistedCSRFActions()
    {
        return [
            'index','test'
        ];
    }

    /**
     * Creates the url parameters
     */
    private function getPaymentData()
    {

        $parameter = [
            'amount' => 1.00,
            'currency' => "EUR",
            'first_name' => "first_name",
            'last_name' => "last_name",
            'payment_id' => 42,
            'email' => "test@example.com",
            'return_url' => "__not_set__",
            'callback_url' => "__not_set__",
            'ipn_url' => "__not_set__",
            'cancel_url' => "__not_set__",
            'seller_name' => Shopware()->Config()->get('company'),
            'memo' => '' . $_SERVER['SERVER_NAME']
        ];
        return $parameter;

    }


    protected function getPayment()
    {
        /** @var \LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService $service */
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');
        $paymentData = $service->createPayment($this->getPaymentData(), $this->getVersion());
        return $paymentData;
    }

    public function getVersion()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSCryptoGate'];
        $filename = $plugin->getPath() . '/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }

    public function testAction()
    {


        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

        if ($_GET["apiToken"]) {
            $service->setOverrideToken($_GET["apiToken"]);
        }
        if ($_GET["apiUrl"]) {
            $service->setOverrideUrl(urldecode($_GET["apiUrl"]));
        }

        $paymentData = $this->getPayment();

        $this->View()->setTemplate();

        if (false === $paymentData || filter_var($paymentData['payment_url'], FILTER_VALIDATE_URL) === false) {
            header("HTTP/1.0 200 Not Okay");
            $result = "Could not generate Payment-URL please see logfile for possible Exceptions";
        } else {

            /** @var PaymentResponse $response */
            $response = new \LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse();
            $response->transactionId = $paymentData['uuid'];
            $response->token = $service->createPaymentToken($this->getPaymentData());


            $result='Success!';
        }

        echo $result;
        die();
    }


}


