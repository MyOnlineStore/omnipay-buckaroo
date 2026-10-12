<?php

namespace Omnipay\Buckaroo\Message;

use Omnipay\Common\Message\AbstractResponse;
use Omnipay\Common\Message\RedirectResponseInterface;

class RivertyResponse extends AbstractResponse implements RedirectResponseInterface
{
    const STATUS_SUCCESS = 190;
    const STATUS_PENDING_INPUT = 790;
    const STATUS_PENDING_PROCESSING = 791;
    const STATUS_REJECTED = 690;
    const STATUS_FAILED = 490;
    const STATUS_CANCELLED_BY_USER = 890;
    const STATUS_CANCELLED_BY_MERCHANT = 891;

    public function isSuccessful()
    {
        return self::STATUS_SUCCESS === $this->getStatusCode();
    }

    public function isPending()
    {
        return in_array($this->getStatusCode(), array(self::STATUS_PENDING_INPUT, self::STATUS_PENDING_PROCESSING), true);
    }

    public function isRedirect()
    {
        return $this->isPending() && null !== $this->getRedirectUrl();
    }

    public function getRedirectUrl()
    {
        return isset($this->data['RequiredAction']['RedirectURL']) && '' !== $this->data['RequiredAction']['RedirectURL']
            ? $this->data['RequiredAction']['RedirectURL']
            : null;
    }

    public function getRedirectMethod()
    {
        return 'GET';
    }

    public function getRedirectData()
    {
        return array();
    }

    public function getStatusCode()
    {
        return isset($this->data['Status']['Code']['Code']) ? (int) $this->data['Status']['Code']['Code'] : null;
    }

    public function getTransactionReference()
    {
        return isset($this->data['Key']) ? $this->data['Key'] : null;
    }

    public function getMessage()
    {
        if (!empty($this->data['Status']['SubCode']['Description'])) {
            return $this->data['Status']['SubCode']['Description'];
        }

        return isset($this->data['Status']['Code']['Description']) ? $this->data['Status']['Code']['Description'] : null;
    }
}
