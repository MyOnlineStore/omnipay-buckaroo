<?php

namespace Omnipay\Buckaroo;

use Omnipay\Common\AbstractGateway;

class RivertyGateway extends AbstractGateway
{
    public function getName()
    {
        return 'Buckaroo Riverty';
    }

    public function getDefaultParameters()
    {
        return array(
            'websiteKey' => '',
            'secretKey' => '',
            'testMode' => false,
        );
    }

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

    public function isInteractive(): bool
    {
        return true;
    }
    
    /** Performs a credit check and creates the Riverty order ("Pay" action) */
    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Buckaroo\Message\RivertyPurchaseRequest', $parameters);
    }
}
