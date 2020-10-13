<?php

namespace LampSCryptoGate\Controllers\Backend;

use LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Components\Model\ModelManager;

class Shopware_Controllers_Backend_CryptoGatePaymentTest extends \Shopware_Controllers_Backend_ExtJs implements CSRFWhitelistAware {




    public function testAction()
    {


        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');

        if($_GET["apiToken"]){
            $service->setOverrideToken($_GET["apiToken"]);
        }
        if($_GET["apiUrl"]){
            $service->setOverrideUrl(urldecode($_GET["apiUrl"]));
        }

        $paymentUrl = $this->getPaymentUrl();


        if(false===$paymentUrl || filter_var($paymentUrl, FILTER_VALIDATE_URL)===false){
            $this->View()->assign('response', 'Oh no! Something went wrong :(');
            if($service->getLastError()){
                $this->View()->assign('response', $service->getLastError()->getMessage());
            }
        }
        else {

            /** @var PaymentResponse $response */
            $response = new \LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse();
            $response->transactionId = end(explode("/", $paymentUrl));
            $response->token = $service->createPaymentToken($this->getPaymentData());


            $this->View()->assign('response', 'Success!');
        }


    }

    public function getWhitelistedCSRFActions() {
        return [
            'test',
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
            'cancel_url' => "__not_set__",
            'seller_name' => Shopware()->Config()->get('company'),
            'memo' => ''.$_SERVER['SERVER_NAME']
        ];
        return $parameter;

    }


    protected function getPaymentUrl()
    {
        /** @var CryptoGatePaymentService $service */
        $service = $this->container->get('crypto_gate.crypto_gate_payment_service');
        $payment_url = $service->createPaymentUrl($this->getPaymentData(),$this->getVersion());
        return $payment_url;
    }

    public function getVersion(){
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['LampSCryptoGate'];
        $filename=$plugin->getPath().'/plugin.xml';
        $xml = simplexml_load_file($filename);
        return (string)$xml->version;
    }
}