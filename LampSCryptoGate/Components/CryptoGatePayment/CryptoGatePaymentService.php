<?php

namespace LampSCryptoGate\Components\CryptoGatePayment;


use GuzzleHttp\Exception\RequestException;
use http\Client\Request;

class CryptoGatePaymentService
{
    protected static $api_endpoint_verify = '/api/shopware/verify';
    protected static $api_endpoint_create = 'api/shopware/create';
    private $error=null;

    private $overrideUrl=false;
    private $overrideToken=false;

    /**
     * @var $logger Logger
     */
    public $logger;

    /**
     * @param $overrideUrl
     */
    public function setOverrideUrl($overrideUrl)
    {
        $this->overrideUrl = $overrideUrl;
    }

    /**
     * @param $overrideToken
     */
    public function setOverrideToken($overrideToken)
    {
        $this->overrideToken = $overrideToken;
    }

    /**
     * @param $logger \Shopware\Components\Logger
     */
    public function __construct(\Shopware\Components\Logger $logger)
    {
        $this->logger = $logger;
    }


    /**
     * @param $request \Enlight_Controller_Request_Request
     * @return PaymentResponse
     */
    public function createPaymentResponse(\Enlight_Controller_Request_Request $request)
    {
        $response = new PaymentResponse();
        $response->transactionId = $request->getParam('uuid', null);
        $response->status = $request->getParam('status', null);
        $response->token = $request->getParam('token', null);

        return $response;
    }

    /**
     * @param PaymentResponse $response
     * @param string $token
     * @return bool
     */
    public function isValidToken(PaymentResponse $response, $token)
    {
        return hash_equals($token, $response->token);
    }

    /**
     * @param array $payment_data
     * @return string
     */
    public function createPaymentToken($payment_data)
    {
        unset($payment_data["return_url"]);
        unset($payment_data["callback_url"]);

        return sha1(implode('|', $payment_data));
    }

    public function createPaymentUrl($parameters=array(),$version) {


        $api_url = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_url');
        if(empty($api_url)){
            $this->logger->error("Cryptogate-Payment-Error: Missing API Key");
            return false;
        }


        $api_key = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_token');

        if($this->overrideToken){
            $api_key=$this->overrideToken;
        }

       if(empty($api_key)){
            Shopware()->PluginLogger()->error("Cryptogate-Payment-Error: Missing API Key");
            return false;
        }

        $parameters['token'] = $this->createPaymentToken($parameters);
        $parameters['api_key'] = $api_key;
        $parameters["plugin_version"] = $version;



        if(empty($parameters['selected_currencies'])) {
            $parameters['selected_currencies'] = implode(",",
                Shopware()->Config()->getByNamespace('LampsCryptoGate', 'selected_currencies'));
        }

        if(Shopware()->Config()->getByNamespace('LampsCryptoGate', 'transmit_customer_data')===false){
            $parameters["first_name"] = "";
            $parameters["last_name"] = "";
            $parameters["email"] = "";
        }

        $client = new \GuzzleHttp\Client();

        $version = Shopware()->Config()->get( 'Version' );
        $body_name="form_params";
        if($version < '5.7') {
           $body_name="body";
        }

        try {
            $response = $client->post($api_url.$this::$api_endpoint_create,[$body_name => $parameters]);
        }catch (RequestException $e) {
            $this->logger->warning("Cryptogate-Payment-Error:".$e->getMessage());
           // $this->error=$e;

            return false;
        }


        return json_decode($response->getBody(), true)['payment_url'];
    }

    public function validatePayment(PaymentResponse $paymentResponse) {
        $api_url = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_url');
        if($this->overrideUrl){
            $api_url=$this->overrideUrl;
        }
        if(empty($api_url)){
            $this->logger->error("Cryptogate-Payment-Error: Missing API Key");
            return false;
        }

        $api_key = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_token');
        if($this->overrideToken){
            $api_key=$this->overrideToken;
        }
        if(empty($api_key)){
            $this->logger->error("Cryptogate-Payment-Error: Missing API Key");
            return false;
        }

        $client = new \GuzzleHttp\Client();

        $version = Shopware()->Config()->get( 'Version' );
        $body_name="form_params";
        if($version < '5.7') {
            $body_name="body";
        }

        try {
            $response = $client->post($api_url.$this::$api_endpoint_verify,
                [$body_name => [
                    'uuid' => $paymentResponse->transactionId,
                    'token' => $paymentResponse->token,
                    'api_key' => $api_key ]
                ]);
        } catch (\Exception $e) {
            $this->logger->warning("Cryptogate-Payment-Error:".$e->getMessage());
            $this->error=$e;
        }

        $verify = json_decode($response->getBody(), true);

        if($verify['token'] == $paymentResponse->token && !empty($paymentResponse->token) && !empty($verify['token'])) {
            return true;
        }

        return false;
    }
    public function getLastError(){
        return $this->error;
    }
}
