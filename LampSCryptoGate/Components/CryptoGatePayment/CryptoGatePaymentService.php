<?php

namespace LampSCryptoGate\Components\CryptoGatePayment;


use GuzzleHttp\Exception\RequestException;

class CryptoGatePaymentService
{
    protected static $api_endpoint_verify = '/api/shopware/verify';
    protected static $api_endpoint_create = '/api/shopware/create';
    private $error=null;

    private $overrideUrl=false;
    private $overrideToken=false;

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
        if($this->overrideUrl){
            $api_url=$this->overrideUrl;
        }

        if(empty($api_url)) throw new \Exception('[LampsCryptoGate] Missing Api URL');

        $api_key = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_token');

        if($this->overrideToken){
            $api_key=$this->overrideToken;
        }

        if(empty($api_key)) throw new \Exception('[LampsCryptoGate] Missing Api Token');


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
        $request = $client->createRequest(
            'POST',
            $api_url.$this::$api_endpoint_create,
            [
                'body' => $parameters,
            ]
        );

        try {
            $response = $client->send($request);
        }catch (RequestException $e) {
            Shopware()->PluginLogger()->warn("Cryptogate-Payment-Error:".$e->getMessage());
            $this->error=$e;

            return false;
            //throw new \Exception('[CryptoGate] Gateway Api Error');
        }


        return json_decode($response->getBody(), true)['payment_url'];
    }

    public function validatePayment(PaymentResponse $paymentResponse) {
        $api_url = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_url');
        if($this->overrideUrl){
            $api_url=$this->overrideUrl;
        }
        if(empty($api_url)) throw new \Exception('[LampsCryptoGate] Missing Api URL');

        $api_key = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'api_token');
        if($this->overrideToken){
            $api_key=$this->overrideToken;
        }
        if(empty($api_key)) throw new \Exception('[LampsCryptoGate] Missing Api Token');

        $client = new \GuzzleHttp\Client();
        $request = $client->createRequest(
            'POST',
            $api_url.$this::$api_endpoint_verify,
            [
                'body' => [ 'uuid' => $paymentResponse->transactionId, 'token' => $paymentResponse->token, 'api_key' => $api_key ],
            ]
        );

        try {
            $response = $client->send($request);
        } catch (\Exception $e) {
            Shopware()->PluginLogger()->warn("Cryptogate-Payment-Error:".$e->getMessage());
            $this->error=$e;

            //throw new \Exception('[LampsCryptoGate] Gateway Api Error');
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
