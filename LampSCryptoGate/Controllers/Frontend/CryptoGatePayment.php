<?php

use Shopware\Components\CSRFWhitelistAware;
use LampSCryptoGate\Components\CryptoGatePayment\PaymentResponse;
use LampSCryptoGate\Components\CryptoGatePayment\CryptoGatePaymentService;


class Shopware_Controllers_Frontend_CryptoGatePayment extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware {

    private $paymentData=[];

    /**
     * @var $cryptoGateService CryptoGatePaymentService
     */
    private $cryptoGateService;

    public function preDispatch() {
        $this->cryptoGateService = Shopware()->Container()->get('crypto_gate.crypto_gate_payment_service');

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

    public function indexAction() {
        $iframeEnabled = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'pay_iframe');
        $action = $iframeEnabled ? 'gateway' : 'direct';

        switch ($this->getPaymentShortName()) {
            case 'cryptogate_payment':
            case 'cryptogate_payment_btc':
            case 'cryptogate_payment_ltc':
            case 'cryptogate_payment_dash':
            case 'cryptogate_payment_bch':
                $this->redirect(['action' => $action, 'forceSecure' => true]);
                break;
            default:
                $this->redirect(['controller' => 'checkout']);
                break;
        }
    }

    public function gatewayAction() {
        $orderDetails = $this->createOrder();
        if(!$orderDetails) return;

        $this->redirect(['action' => 'gatewayFrame', 'forceSecure' => true, 'uuid' => $orderDetails['uuid']]);
    }

    public function gatewayFrameAction() {
        $orderDetails = $this->getCryptoOrderDetailsByUuid($this->request()->get('uuid'));
        if(!$orderDetails) {
            $this->handlePaymentError('OrderNotFound');
            return;
        }


        $this->View()->assign('gatewayUrl', $orderDetails['url']);
    }

    private function createOrder() {
        $paymentToken = $this->cryptoGateService->createPaymentToken($this->getPaymentData());
        $paymentData = $this->createCryptoPayment();
        if(!$paymentData){
            $this->handlePaymentError('CouldNotConnectToCryptoGate');
            return false;
        }

        $orderNumber = $this->saveOrder(
            $paymentData['uuid'],
            $paymentToken
        );

        if(!$orderNumber) {
            $this->handlePaymentError('OrderCouldNotBeCreated');
            return false;
        }

        if(!$this->storeCryptoPaymentDetailsToOrder($orderNumber, $paymentToken, $paymentData['uuid'], $paymentData['payment_url'])) {
            $this->handlePaymentError('OrderAttributesCouldNotBeStored');
            return false;
        }

        return array(
            'orderNumber' => $orderNumber,
            'token' => $paymentToken,
            'uuid' => $paymentData['uuid'],
            'url' => $paymentData['payment_url']
        );
    }

    public function directAction() {
        $orderDetails = $this->createOrder();
        if(!$orderDetails) return;
        $this->redirect($orderDetails['url']);
    }

    private function handlePaymentError($errorKey) {
        $baseUrl = $this->Front()->Router()->assemble([
            'controller' => 'checkout',
            'action' => 'cart'
        ]);

        $this->redirect(sprintf(
            '%s?%s=1',
            $baseUrl,
            $errorKey
        ));
    }

    private function storeCryptoPaymentDetailsToOrder($orderNumber, $paymentToken, $paymentUuid, $paymentUrl) {
        $order = Shopware()->Models()->getRepository('Shopware\Models\Order\Order')->findOneBy(array('number' => $orderNumber));
        if(!$order) return false;
        $orderAttributeModel = Shopware()->Models()->getRepository('Shopware\Models\Attribute\Order')->findOneBy(array('orderId' => $order->getId()));
        if(!$orderAttributeModel) return false;

        if ($orderAttributeModel instanceof \Shopware\Models\Attribute\Order) {
            $orderAttributeModel->setLampscryptogateToken($paymentToken);
            $orderAttributeModel->setLampscryptogateUuid($paymentUuid);
            $orderAttributeModel->setLampscryptogateUrl($paymentUrl);
            Shopware()->Models()->persist($orderAttributeModel);
            Shopware()->Models()->flush();
            return true;
        }
        return false;
    }

    public function callbackAction() {
        $this->forward('notify');
    }

    /**
     * Return action method
     *
     * Reads the transactionResult and represents it for the customer.
     */
    public function returnAction() {
        /** @var PaymentResponse $response */
        $response = $this->cryptoGateService->createPaymentResponse($this->Request());

        if(empty($response->token) || empty($response->transactionId)) {
            $this->jsonCallbackResponse(array('status' => -1, 'msg' => 'Not a valid notify request'));
            return;
        }

        $orderDetails = $this->getCryptoOrderDetailsByUuid($response->transactionId);


        if(empty($orderDetails)) {
            $this->forward('cancel');
            return;
        }

        if (!$this->cryptoGateService->isValidToken($response, $orderDetails['token'])) {
            $this->forward('cancel');
            return;
        }

        if (!$this->cryptoGateService->validatePayment($response)) {
            $this->forward('cancel');
            return;
        }

        if($response->status != 'Paid') {
            $this->forward('cancel');
            return;
        }

        $paymentState = $response->inBlock ? $this->cryptoGateService->getPaymentStatusInBlock() : $this->cryptoGateService->getPaymentStatusMemPool();

        $emailSendEnabled = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'send_paid_email');
        $sendEmail = $paymentState == $this->cryptoGateService->getPaymentStatusInBlock() && $emailSendEnabled;
        $this->savePaymentStatus($response->transactionId, $response->token, $paymentState, $sendEmail);

        $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
    }

    /**
     * Cancel action method
     */
    public function cancelAction() {
    }

    public function notifyAction() {
        /** @var PaymentResponse $response */
        $response = $this->cryptoGateService->createPaymentResponse($this->Request());

        if(empty($response->token) || empty($response->transactionId)) {
            $this->jsonCallbackResponse(array('status' => -1, 'msg' => 'Not a valid notify request'));
            return;
        }

        $orderDetails = $this->getCryptoOrderDetailsByUuid($response->transactionId);

        if(empty($orderDetails)) {
            $this->jsonCallbackResponse(array('status' => -2, 'msg' => 'Order not found'));
            return;
        }

        if (!$this->cryptoGateService->isValidToken($response, $orderDetails['token'])) {
            $this->jsonCallbackResponse(array('status' => -3, 'msg' => 'Invalid token'));
            return;
        }

        if (!$this->cryptoGateService->validatePayment($response)) {
            $this->jsonCallbackResponse(array('status' => -4, 'msg' => 'Callback payment could not be validated'));
            return;
        }

        if($response->status != 'Paid') {
            $this->jsonCallbackResponse(array('status' => -5, 'msg' => 'Status is not paid'));
            return;
        }

        $paymentState = $response->inBlock ? $this->cryptoGateService->getPaymentStatusInBlock() : $this->cryptoGateService->getPaymentStatusMemPool();

        $emailSendEnabled = Shopware()->Config()->getByNamespace('LampsCryptoGate', 'send_paid_email');
        $sendEmail = $paymentState == $this->cryptoGateService->getPaymentStatusInBlock() && $emailSendEnabled;
        $this->savePaymentStatus($response->transactionId, $response->token, $paymentState, $sendEmail);
        $this->jsonCallbackResponse(array('status' => 0, 'msg' => 'ok', 'paymentState' => $paymentState, 'token' => $response->token, 'transactionId' => $response->transactionId, 'inBlock' =>$response->inBlock));
    }

    /**
     * Creates the url parameters
     */
    private function getPaymentData($generateToken=true)
    {

        if(!empty($this->paymentData)){
            return $this->paymentData;
        }
        $router = $this->Front()->Router();
        $user = $this->getUser();

        $paymentId = $user['additional']['payment']['id'];
        $billing = $user['billingaddress'];

        $version = Shopware()->Config()->get( 'Version' );
        $returnParameters = [
            'action' => 'return',
            'forceSecure' => true,
        ];
        $callbackParameters = [
            'action' => 'callback',
            'forceSecure' => true,
        ];
        $notifyParameters = [
            'action' => 'notify',
            'forceSecure' => true,
        ];

        $parameter = [
            'amount' => $this->getAmount(),
            'currency' => $this->getCurrencyShortName(),
            'first_name' => $billing['firstname'],
            'last_name' => $billing['lastname'],
            'payment_id' => $paymentId,
            'email' => @$user['additional']['user']['email'],
            'return_url' => $router->assemble($returnParameters),
            'callback_url' => $router->assemble($callbackParameters),
            'ipn_url' => $router->assemble($notifyParameters),
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
        $this->paymentData=$parameter;

        return $parameter;

    }

    /**
     * Returns the URL of the payment provider. This has to be replaced with the real payment provider URL
     *
     * @return array
     */
    protected function createCryptoPayment() {
        $payment = $this->cryptoGateService->createPayment($this->getPaymentData(),$this->getVersion());
        return $payment;
    }

    public function getWhitelistedCSRFActions() {
        return [
            'return','callback','cancel','notify'
        ];
    }

    /**
     * returns shopware model manager
     * @return \Shopware\Components\Model\ModelManager
     */
    public function getEntityManager()
    {
        return Shopware()->Models();
    }

    private function getCryptoOrderDetailsByUuid($uuid) {
        $orderAttributeModel = Shopware()->Models()->getRepository('Shopware\Models\Attribute\Order')->findOneBy(array('lampscryptogateUuid' => $uuid));
        if ($orderAttributeModel instanceof \Shopware\Models\Attribute\Order) {
            return array(
                'orderId' => $orderAttributeModel->getOrderId(),
                'uuid' => $orderAttributeModel->getLampscryptogateUuid(),
                'token' => $orderAttributeModel->getLampscryptogateToken(),
                'url' => $orderAttributeModel->getLampscryptogateUrl()
            );
        }
        return false;
    }


    private function jsonCallbackResponse($data) {
        $this->cryptoGateService->logger->info('CryptoGate Callbacks: '.\json_encode($data));
        $this->container->get('front')->Plugins()->ViewRenderer()->setNoRender(); // Disable template loading
        echo \json_encode($data);
    }

}
