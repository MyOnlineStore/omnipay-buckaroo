<?php

namespace Omnipay\Buckaroo\Message;

/**
 * Buckaroo Riverty (Afterpay) Purchase Request ("Pay" action).
 *
 * @docs https://docs.buckaroo.io/docs/riverty-requests
 */
class RivertyPurchaseRequest extends RivertyAbstractRequest
{
    public function getServiceAction()
    {
        return 'Pay';
    }

    protected function getAmountData()
    {
        return array('AmountDebit' => $this->formatAmount($this->getAmount()));
    }

    public function getServiceParameters()
    {
        $this->validate('amount', 'card');

        $parameters = array_merge(
            $this->getArticleParameters(),
            $this->getCustomerParameters('BillingCustomer', 'Billing')
        );

        if ($this->hasShippingAddress()) {
            $parameters = array_merge($parameters, $this->getCustomerParameters('ShippingCustomer', 'Shipping'));
        }

        return $parameters;
    }

    public function getVatPercentage()
    {
        return $this->getParameter('vatPercentage');
    }

    public function setVatPercentage($value)
    {
        return $this->setParameter('vatPercentage', $value);
    }

    public function getIdentificationNumber()
    {
        return $this->getParameter('identificationNumber');
    }

    public function setIdentificationNumber($value)
    {
        return $this->setParameter('identificationNumber', $value);
    }

    public function getSalutation()
    {
        return $this->getParameter('salutation');
    }

    public function setSalutation($value)
    {
        return $this->setParameter('salutation', $value);
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function getArticleParameters()
    {
        $parameters = array();
        $items = $this->getItems();

        if ($items && count($items) > 0) {
            $vatPercentages = $this->getLineVatPercentages();
            $index = 1;

            foreach ($items as $item) {
                $groupId = (string) $index;
                // Per-line VAT when available (see RivertyAbstractRequest::getLineVatPercentages()
                // for why this can't just live on $item), otherwise fall back to the single
                // request-level override.
                $vatPercentage = \array_key_exists($index - 1, $vatPercentages)
                    ? $vatPercentages[$index - 1]
                    : $this->getVatPercentage();
                $parameters[] = $this->buildParameter('Description', $item->getName(), 'Article', $groupId);
                $parameters[] = $this->buildParameter('GrossUnitPrice', $this->formatAmount($item->getPrice()), 'Article', $groupId);
                $parameters[] = $this->buildParameter('VatPercentage', $vatPercentage, 'Article', $groupId);
                $parameters[] = $this->buildParameter('Quantity', $item->getQuantity(), 'Article', $groupId);
                $parameters[] = $this->buildParameter('Identifier', $item->getName(), 'Article', $groupId);
                ++$index;
            }

            return $parameters;
        }

        // Riverty requires at least one article line - fall back to a single line covering the
        $parameters[] = $this->buildParameter('Description', $this->getDescription() ?: 'Order', 'Article', '1');
        $parameters[] = $this->buildParameter('GrossUnitPrice', $this->formatAmount($this->getAmount()), 'Article', '1');
        $parameters[] = $this->buildParameter('VatPercentage', $this->getVatPercentage(), 'Article', '1');
        $parameters[] = $this->buildParameter('Quantity', '1', 'Article', '1');
        $parameters[] = $this->buildParameter('Identifier', $this->getTransactionId(), 'Article', '1');

        return $parameters;
    }

    /**
     * @return bool
     */
    protected function hasShippingAddress()
    {
        $card = $this->getCard();

        return null !== $card && '' !== (string) $card->getShippingAddress1();
    }

    /**
     * @param string $groupType   'BillingCustomer' or 'ShippingCustomer'
     * @param string $addressType 'Billing' or 'Shipping'
     *
     * @return array<int, array<string, string>>
     */
    protected function getCustomerParameters(string $groupType, string $addressType)
    {
        $card = $this->getCard();
        $address = $this->splitStreetAddress($this->getCardValue($card, $addressType, 'Address1'));
        $company = $this->getCardValue($card, $addressType, 'Company');

        $parameters = array(
            $this->buildParameter('Category', '' !== $company ? 'Company' : 'Person', $groupType),
            $this->buildParameter('FirstName', $this->getCardValue($card, $addressType, 'FirstName'), $groupType),
            $this->buildParameter('LastName', $this->getCardValue($card, $addressType, 'LastName'), $groupType),
            $this->buildParameter('Street', $address['street'], $groupType),
            $this->buildParameter('StreetNumber', $address['number'], $groupType),
            $this->buildParameter('PostalCode', $this->getCardValue($card, $addressType, 'Postcode'), $groupType),
            $this->buildParameter('City', $this->getCardValue($card, $addressType, 'City'), $groupType),
            $this->buildParameter('Country', $this->getCardValue($card, $addressType, 'Country'), $groupType),
        );

        if ('BillingCustomer' === $groupType) {
            $parameters[] = $this->buildParameter('Email', $card->getEmail(), $groupType);
            $parameters[] = $this->buildParameter('IdentificationNumber', $this->getIdentificationNumber(), $groupType);
            $parameters[] = $this->buildParameter('Salutation', $this->getSalutation(), $groupType);

            if ($card->getBirthday()) {
                $parameters[] = $this->buildParameter('BirthDate', $card->getBirthday('Y-m-d'), $groupType);
            }

            $phone = $card->getBillingPhone();

            if ($phone) {
                $parameters[] = $this->buildParameter('MobilePhone', $phone, $groupType);
            }
        }

        return $parameters;
    }

    protected function getCardValue($card, $addressType, $field)
    {
        $method = 'get'.$addressType.$field;

        return null !== $card ? (string) $card->{$method}() : '';
    }

    protected function splitStreetAddress($address1)
    {
        if (preg_match('/^(.*\S)\s+(\d+\s*[a-zA-Z-]{0,4})$/', trim($address1), $matches)) {
            return array('street' => $matches[1], 'number' => trim($matches[2]));
        }

        return array('street' => $address1, 'number' => '');
    }
}
