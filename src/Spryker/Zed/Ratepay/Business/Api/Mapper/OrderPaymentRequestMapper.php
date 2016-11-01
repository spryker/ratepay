<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Ratepay\Business\Api\Mapper;

use Generated\Shared\Transfer\AddressTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\RatepayPaymentInitTransfer;
use Generated\Shared\Transfer\RatepayPaymentRequestTransfer;
use Orm\Zed\Sales\Persistence\SpySalesOrder;
use Orm\Zed\Sales\Persistence\SpySalesOrderAddress;
use Spryker\Zed\Ratepay\Persistence\RatepayQueryContainerInterface;

class OrderPaymentRequestMapper extends BaseMapper
{

    /**
     * @var \Generated\Shared\Transfer\RatepayPaymentRequestTransfer
     */
    protected $ratepayPaymentRequestTransfer;

    /**
     * @var \Generated\Shared\Transfer\OrderTransfer
     */
    protected $orderTransfer;

    /**
     * @var \Generated\Shared\Transfer\QuoteTransfer
     */
    protected $orderEntity;

    /**
     * @var \Generated\Shared\Transfer\RatepayPaymentInitTransfer
     */
    protected $ratepayPaymentInitTransfer;

    /**
     * @var \Spryker\Zed\Ratepay\Persistence\RatepayQueryContainerInterface $queryContainer
     */
    protected $queryContainer;

    /**
     * @param \Generated\Shared\Transfer\RatepayPaymentRequestTransfer $ratepayPaymentRequestTransfer
     * @param \Generated\Shared\Transfer\RatepayPaymentInitTransfer $ratepayPaymentInitTransfer
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \Orm\Zed\Sales\Persistence\SpySalesOrder $orderEntity
     * @param \Spryker\Zed\Ratepay\Persistence\RatepayQueryContainerInterface $queryContainer
     */
    public function __construct(
        RatepayPaymentRequestTransfer $ratepayPaymentRequestTransfer,
        RatepayPaymentInitTransfer $ratepayPaymentInitTransfer,
        OrderTransfer $orderTransfer,
        SpySalesOrder $orderEntity,
        RatepayQueryContainerInterface $queryContainer
    ) {
        $this->ratepayPaymentRequestTransfer = $ratepayPaymentRequestTransfer;
        $this->ratepayPaymentInitTransfer = $ratepayPaymentInitTransfer;
        $this->orderTransfer = $orderTransfer;
        $this->orderEntity = $orderEntity;
        $this->queryContainer = $queryContainer;
    }

    /**
     * @return void
     */
    public function map()
    {
        $totalsTransfer = $this->orderTransfer->requireTotals()->getTotals();
        $billingAddress = $this->getAddressTransfer($this->orderEntity->getBillingAddress());
        $shippingAddress = $this->getAddressTransfer($this->orderEntity->getShippingAddress());
        $expenses = $this->orderTransfer->getExpenses();

        if ($this->orderEntity->getSpyPaymentRatepays() &&
            count($this->orderEntity->getSpyPaymentRatepays()->getData())
        ) {
            /** @var \Orm\Zed\Ratepay\Persistence\SpyPaymentRatepay $paymentRatepayEntity */
            $paymentRatepayEntity = $this->orderEntity->getSpyPaymentRatepays()->getData()[0];
            $this->ratepayPaymentRequestTransfer
                ->setPaymentType($paymentRatepayEntity->getPaymentType())
                ->setCurrencyIso3($paymentRatepayEntity->getCurrencyIso3())
                ->setCustomerPhone($paymentRatepayEntity->getPhone())
                ->setGender($paymentRatepayEntity->getGender())
                ->setDateOfBirth($paymentRatepayEntity->getDateOfBirth("Y-m-d"))
                ->setIpAddress($paymentRatepayEntity->getIpAddress())
                ->setCustomerAllowCreditInquiry($paymentRatepayEntity->getCustomerAllowCreditInquiry())

//                ->setBankAccountHolder($paymentRatepayEntity->getBankAccountHolder())
                ->setBankAccountHolder($billingAddress->getFirstName() . " " . $billingAddress->getLastName())
                ->setBankAccountBic($paymentRatepayEntity->getBankAccountBic())
                ->setBankAccountIban($paymentRatepayEntity->getBankAccountIban())

                ->setDebitPayType($paymentRatepayEntity->getDebitPayType())
                ->setInstallmentGrandTotalAmount($paymentRatepayEntity->getInstallmentTotalAmount())
                ->setInstallmentNumberRates($paymentRatepayEntity->getInstallmentNumberRates())
                ->setInstallmentRate($paymentRatepayEntity->getInstallmentRate())
                ->setInstallmentLastRate($paymentRatepayEntity->getInstallmentLastRate())
                ->setInstallmentInterestRate($paymentRatepayEntity->getInstallmentInterestRate())
                ->setInstallmentPaymentFirstDay($paymentRatepayEntity->getInstallmentPaymentFirstDay());
        }

        $this->ratepayPaymentRequestTransfer
            ->setOrderId($this->orderEntity->getIdSalesOrder())
            ->setRatepayPaymentInit($this->ratepayPaymentInitTransfer)
//            ->setGrandTotal($totalsTransfer->requireGrandTotal()->getGrandTotal())
            ->setExpenseTotal($totalsTransfer->requireExpenseTotal()->getExpenseTotal())

            ->setCustomerEmail($this->orderTransfer->getEmail())

            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress);
        if (count($expenses)) {
            $this->ratepayPaymentRequestTransfer
                ->setShippingTaxRate($expenses[0]->getTaxRate());
        }
        $basketItems = $this->orderTransfer->requireItems()->getItems();
        $grouppedItems = [];
        foreach ($basketItems as $basketItem) {
            if (isset($grouppedItems[$basketItem->getGroupKey()])) {
                $grouppedItems[$basketItem->getGroupKey()]->setQuantity($grouppedItems[$basketItem->getGroupKey()]->getQuantity() + 1);
            } else {
                $grouppedItems[$basketItem->getGroupKey()] = clone $basketItem;
                $ratepayOrderItem = $this->queryContainer
                    ->queryPaymentItemByOrderIdAndSku($this->orderEntity->getIdSalesOrder(), $basketItem->getGroupKey())
                    ->findOne();
                if ($ratepayOrderItem) {
                    /**
                     * In Spryker system each Item has own discount,
                     * but we need to send discount amount like in previous item.
                     * That's why we save first
                     */
                    $grouppedItems[$basketItem->getGroupKey()]->setUnitGrossPriceWithProductOptions($ratepayOrderItem->getUnitGrossPriceWithProductOptions());
                    $grouppedItems[$basketItem->getGroupKey()]->setUnitTotalDiscountAmountWithProductOption($ratepayOrderItem->getUnitTotalDiscountAmountWithProductOption());
                    $grouppedItems[$basketItem->getGroupKey()]->setSumGrossPriceWithProductOptionAndDiscountAmounts($ratepayOrderItem->getSumGrossPriceWithProductOptionAndDiscountAmounts());
                } else {
                    $this->queryContainer
                        ->addPaymentItem($grouppedItems[$basketItem->getGroupKey()]);
                }
            }
        }

        $grandTotal = $totalsTransfer->requireExpenseTotal()->getExpenseTotal();
        foreach ($grouppedItems as $basketItem) {
            $this->ratepayPaymentRequestTransfer->addItem($basketItem);
            $grandTotal += $basketItem->getSumGrossPriceWithProductOptionAndDiscountAmounts() * $basketItem->getQuantity();
        }

        $this->ratepayPaymentRequestTransfer
            ->setGrandTotal($grandTotal);
    }

    /**
     * @param \Orm\Zed\Sales\Persistence\SpySalesOrderAddress $addressEntity
     * @return \Generated\Shared\Transfer\AddressTransfer
     */
    protected function getAddressTransfer(SpySalesOrderAddress $addressEntity)
    {
        $addressTransfer = new AddressTransfer();
        $addressTransfer
            ->setSalutation($addressEntity->getSalutation())
            ->setFirstName($addressEntity->getFirstName())
            ->setMiddleName($addressEntity->getMiddleName())
            ->setLastName($addressEntity->getLastName())
            ->setCompany($addressEntity->getCompany())
            ->setCity($addressEntity->getCity())
            ->setIso2Code($addressEntity->getCountry()->getIso2Code())
            ->setZipCode($addressEntity->getZipCode())
            ->setAddress1($addressEntity->getAddress1())
            ->setAddress2($addressEntity->getAddress2())
            ->setAddress3($addressEntity->getAddress3())
            ->setCellPhone($addressEntity->getCellPhone())
            ->setEmail($addressEntity->getEmail());

        return $addressTransfer;
    }

}
