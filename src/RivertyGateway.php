<?php

namespace Omnipay\Buckaroo;

use Omnipay\Common\AbstractGateway;

/**
 * Buckaroo Riverty (Afterpay) Gateway
 *
 * Deliberately does NOT extend BuckarooGateway, even though every other per-method gateway class
 * in this package does (IdealGateway, CreditCardGateway, PayPalGateway, SepaDirectDebitGateway,
 * BancontactMrCashGateway) - that would also inherit BuckarooGateway::completePurchase(), which
 * builds the classic Brq_signature-based CompletePurchaseRequest. Riverty's completePurchase/
 * notify handling goes through a different, HMAC-SHA256-signed JSON Payment Engine API and isn't
 * implemented yet (see config/services/omnipay/buckaroo.yaml in the consuming app - deliberately
 * deferred); inheriting completePurchase() here would make it silently "work" with the wrong
 * signature scheme instead of failing loudly as unsupported.
 *
 * getWebsiteKey()/setWebsiteKey()/getSecretKey()/setSecretKey()/getDefaultParameters() are
 * therefore defined directly below (copied from BuckarooGateway) rather than inherited. Without
 * them, Omnipay\Common\Helper::initialize() silently drops the websiteKey/secretKey parameters
 * passed in by GatewayType::getGatewayParams() (it only applies a parameter if a matching setter
 * method exists on the gateway instance), leaving every Riverty request with no credentials at
 * all and failing with "The websiteKey parameter is required" the moment it validates.
 */
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
