<?php

namespace Omnipay\Buckaroo\Message;

use Omnipay\Common\Exception\InvalidResponseException;

/**
 * Buckaroo Riverty (Afterpay) Abstract Request.
 */
abstract class RivertyAbstractRequest extends \Omnipay\Common\Message\AbstractRequest
{
    public $testEndpoint = 'https://testcheckout.buckaroo.nl/json/Transaction';
    public $liveEndpoint = 'https://checkout.buckaroo.nl/json/Transaction';

    const SERVICE_NAME = 'afterpay';
    const SERVICE_VERSION = 1;

    public function getWebsiteKey()
    {
        return $this->getParameter('websiteKey');
    }

    public function setWebsiteKey($value)
    {
        return $this->setParameter('websiteKey', $value);
    }

    public function getSecretKey()
    {
        return $this->getParameter('secretKey');
    }

    public function setSecretKey($value)
    {
        return $this->setParameter('secretKey', $value);
    }

    public function getEndpoint()
    {
        return $this->getTestMode() ? $this->testEndpoint : $this->liveEndpoint;
    }

    /**
     * A previous transaction's Buckaroo key required for Capture/CancelAuthorize/Refund.
     *
     * @return string|null
     */
    public function getOriginalTransactionKey()
    {
        return $this->getParameter('originalTransactionKey');
    }

    public function setOriginalTransactionKey($value)
    {
        return $this->setParameter('originalTransactionKey', $value);
    }

    /**
     * @return array<int, string>
     */
    public function getLineVatPercentages()
    {
        return (array) $this->getParameter('lineVatPercentages');
    }

    /** @param array<int, string> $value */
    public function setLineVatPercentages($value)
    {
        return $this->setParameter('lineVatPercentages', $value);
    }

    /**
     * The Riverty action for this request, must be one of 'Pay', 'Authorize', 'Capture', 'CancelAuthorize' or
     * 'Refund'
     *
     * @return string
     */
    abstract public function getServiceAction();

    /**
     * @return array<string, string>
     */
    abstract protected function getAmountData();

    /**
     * @return array<int, array<string, string>>
     */
    abstract public function getServiceParameters();

    /**
     * Builds a single Riverty service parameter entry.
     *
     * @param string $name
     * @param mixed  $value
     * @param string $groupType
     * @param string $groupId
     *
     * @return array<string, string>
     */
    protected function buildParameter($name, $value, $groupType = '', $groupId = '')
    {
        return array(
            'Name' => $name,
            'GroupType' => $groupType,
            'GroupID' => $groupId,
            'Value' => (string) $value,
        );
    }

    /**
     * @param float|string $amount
     *
     * @return string
     */
    protected function formatAmount($amount)
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * @return string
     */
    public function getOrder()
    {
        $order = $this->getParameter('order');

        if (!$order) {
            $order = \uniqid();
            $this->setOrder($order);
        }

        return $order;
    }

    public function setOrder($value)
    {
        return $this->setParameter('order', $value);
    }

    public function getData()
    {
        $this->validate('websiteKey', 'secretKey', 'transactionId');

        $data = array(
            'Currency' => $this->getCurrency(),
            // Buckaroo's own docs describe Invoice as "Invoice/order number" (alphanumeric,
            // dashes, underscores; max 19 characters) - using the same value as Order rather than
            // getTransactionId() deliberately: transactionId is our payment/transaction reference
            // (a UUID in this codebase, 36 characters - already over the 19-char cap on its own),
            // whereas getOrder() is already kept <=19 characters (explicit order reference, or the
            // auto-generated fallback).
            'Invoice' => $this->getOrder(),
            'Order' => $this->getOrder(),
        );

        $data = array_merge($data, $this->getAmountData());

        if ($this->getOriginalTransactionKey()) {
            $data['OriginalTransactionKey'] = $this->getOriginalTransactionKey();
        }

        // `getReturnUrl()`/`setReturnUrl()` are inherited for free from Omnipay\Common's
        // AbstractRequest (ParametersTrait) - the payment service already populates this
        // generically for every interactive gateway request via
        // RequestConverterDriver::convertRequest() (see PerformAction.php), exactly like it does
        // websiteKey/secretKey/testMode. The classic Brq_*-based requests in this package read it
        // as `Brq_return` (see AbstractRequest::getData()); per
        // https://docs.buckaroo.io/docs/api-docs-basic-parameters the JSON Payment Engine's
        // equivalent top-level field is `ReturnURL` (optional - only sent when actually set).
        if ($this->getReturnUrl()) {
            $data['ReturnURL'] = $this->getReturnUrl();
        }

        // `getCancelUrl()` is likewise inherited for free, and already populated with the exact
        // same value as `returnUrl` by RequestConverterDriver::convertRequest() (there is only one
        // merchant-supplied "come back to checkout" URL in this codebase - it isn't split into
        // separate cancel/reject destinations upstream). ReturnURLCancel/ReturnURLReject are both
        // documented as optional alternative return URLs for cancelled/rejected transactions
        // respectively (docs.buckaroo.io/docs/api-docs-basic-parameters) - pointed at the same
        // checkout return URL here, same as ReturnURL.
        if ($this->getCancelUrl()) {
            $data['ReturnURLCancel'] = $this->getCancelUrl();
            $data['ReturnURLReject'] = $this->getCancelUrl();
        }

        // `getNotifyUrl()`/`setNotifyUrl()` are likewise inherited for free from Omnipay\Common's
        // AbstractRequest. The consuming payment service only ever populates this parameter when
        // `$gateway->supports(new NotifyRequest(...))` returns true (see PerformAction.php), i.e.
        // once a NotifyHandler is actually registered for this gateway - see
        // Infrastructure\Omnipay\Gateway\Buckaroo\NotifyHandler and
        // config/services/omnipay/buckaroo.yaml. `PushURL` is the JSON Payment Engine's top-level
        // equivalent (docs.buckaroo.io/docs/api-docs-basic-parameters) of the classic gateway's
        // async webhook mechanism - Buckaroo calls it to report a Riverty transaction's final
        // status once the asynchronous credit-check/consumer-acceptance step resolves.
        if ($this->getNotifyUrl()) {
            $data['PushURL'] = $this->getNotifyUrl();
        }

        if ($this->getClientIp()) {
            $data['ClientIP'] = array(
                'Address' => $this->getClientIp(),
                // 0 = IPv4, 1 = IPv6
                'Type' => false !== strpos($this->getClientIp(), ':') ? 1 : 0,
            );
        }

        $data['Services'] = array(
            'ServiceList' => array(
                array(
                    'Name' => static::SERVICE_NAME,
                    'Action' => $this->getServiceAction(),
                    'Version' => static::SERVICE_VERSION,
                    'Parameters' => $this->getServiceParameters(),
                ),
            ),
        );

        return $data;
    }

    /**
     * @param array $data
     *
     * @return RivertyResponse
     */
    public function sendData($data)
    {
        $body = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $endpoint = $this->getEndpoint();

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'Authorization' => $this->getAuthorizationHeader('POST', $endpoint, $body),
        );

        $httpResponse = $this->httpClient->request('POST', $endpoint, $headers, $body);
        $responseData = json_decode((string) $responseBody = $httpResponse->getBody()->getContents(), true);

        file_put_contents('/srv/app/var/log/riverty.log', $responseBody . "\r\n\r\n");

        if (!is_array($responseData)) {
            throw new InvalidResponseException('Unable to parse Riverty/Buckaroo JSON response');
        }

        return $this->response = new RivertyResponse($this, $responseData);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param string $body
     *
     * @return string
     */
    protected function getAuthorizationHeader($method, $uri, $body)
    {
        $timestamp = (string) time();
        $nonce = $this->generateNonce();
        $strippedUri = (string) preg_replace('#^[^:/.]*[:/]+#i', '', $uri);
        $encodedUri = strtolower(urlencode($strippedUri));

        $base64Content = '';
        if ('' !== $body) {
            $base64Content = base64_encode(md5($body, true));
        }

        $hashString = $this->getWebsiteKey().$method.$encodedUri.$timestamp.$nonce.$base64Content;
        $hmac = base64_encode(hash_hmac('sha256', $hashString, (string) $this->getSecretKey(), true));

        return sprintf('hmac %s:%s:%s:%s', $this->getWebsiteKey(), $hmac, $nonce, $timestamp);
    }

    /**
     * @return string
     */
    protected function generateNonce()
    {
        return bin2hex(random_bytes(16));
    }
}
