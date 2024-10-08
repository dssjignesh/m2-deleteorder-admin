<?php

declare(strict_types=1);

/**
 * Digit Software Solutions.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 *
 * @category  Dss
 * @package   Dss_DeleteOrder
 * @author    Extension Team
 * @copyright Copyright (c) 2024 Digit Software Solutions. (https://digitsoftsol.com)
 */
namespace Dss\DeleteOrder\Model\Creditmemo;

use Magento\Framework\App\ResourceConnection;
use Dss\DeleteOrder\Helper\Data;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Model\Order;

class Delete
{
    /**
     * Delete constructor.
     *
     * @param ResourceConnection $resource
     * @param Data $data
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param Order $order
     */
    public function __construct(
        protected ResourceConnection $resource,
        protected Data $data,
        protected CreditmemoRepositoryInterface $creditmemoRepository,
        protected Order $order
    ) {
    }

    /**
     * Delete the credit memo by ID and revert the order state.
     *
     * @param string $creditmemoId
     * @return Order
     * @throws \Exception
     */
    public function deleteCreditmemo(string $creditmemoId): Order
    {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $creditmemoGridTable = $connection->getTableName($this->data->getTableName('sales_creditmemo_grid'));
        $creditmemoTable = $connection->getTableName($this->data->getTableName('sales_creditmemo'));
        $creditmemo = $this->creditmemoRepository->get($creditmemoId);
        $orderId = $creditmemo->getOrder()->getId();
        $order = $this->order->load($orderId);
        $orderItems = $order->getAllItems();
        $creditmemoItems = $creditmemo->getAllItems();

        // Revert item in order
        foreach ($orderItems as $item) {
            foreach ($creditmemoItems as $creditmemoItem) {
                if ($creditmemoItem->getOrderItemId() == $item->getItemId()) {
                    $item->setQtyRefunded($item->getQtyRefunded() - $creditmemoItem->getQty());
                    $item->setTaxRefunded($item->getTaxRefunded() - $creditmemoItem->getTaxAmount());
                    $item->setBaseTaxRefunded($item->getBaseTaxRefunded() - $creditmemoItem->getBaseTaxAmount());
                    $discountTaxItem = $item->getDiscountTaxCompensationRefunded();
                    $discountTaxCredit = $creditmemoItem->getDiscountTaxCompensationAmount();
                    $item->setDiscountTaxCompensationRefunded(
                        $discountTaxItem - $discountTaxCredit
                    );
                    $baseDiscountItem = $item->getBaseDiscountTaxCompensationRefunded();
                    $baseDiscountCredit = $creditmemoItem->getBaseDiscountTaxCompensationAmount();
                    $item->setBaseDiscountTaxCompensationRefunded(
                        $baseDiscountItem - $baseDiscountCredit
                    );
                    $item->setAmountRefunded($item->getAmountRefunded() - $creditmemoItem->getRowTotal());
                    $item->setBaseAmountRefunded($item->getBaseAmountRefunded() - $creditmemoItem->getBaseRowTotal());
                    $item->setDiscountRefunded($item->getDiscountRefunded() - $creditmemoItem->getDiscountAmount());
                    $item->setBaseDiscountRefunded(
                        $item->getBaseDiscountRefunded() - $creditmemoItem->getBaseDiscountAmount()
                    );
                }
            }
        }

        // Revert info in order
        $order->setBaseTotalRefunded($order->getBaseTotalRefunded() - $creditmemo->getBaseGrandTotal());
        $order->setTotalRefunded($order->getTotalRefunded() - $creditmemo->getGrandTotal());

        $order->setBaseSubtotalRefunded($order->getBaseSubtotalRefunded() - $creditmemo->getBaseSubtotal());
        $order->setSubtotalRefunded($order->getSubtotalRefunded() - $creditmemo->getSubtotal());

        $order->setBaseTaxRefunded($order->getBaseTaxRefunded() - $creditmemo->getBaseTaxAmount());
        $order->setTaxRefunded($order->getTaxRefunded() - $creditmemo->getTaxAmount());
        $order->setBaseDiscountTaxCompensationRefunded(
            $order->getBaseDiscountTaxCompensationRefunded() - $creditmemo->getBaseDiscountTaxCompensationAmount()
        );
        $order->setDiscountTaxCompensationRefunded(
            $order->getDiscountTaxCompensationRefunded() - $creditmemo->getDiscountTaxCompensationAmount()
        );

        $order->setBaseShippingRefunded($order->getBaseShippingRefunded() - $creditmemo->getBaseShippingAmount());
        $order->setShippingRefunded($order->getShippingRefunded() - $creditmemo->getShippingAmount());

        $order->setBaseShippingTaxRefunded(
            $order->getBaseShippingTaxRefunded() - $creditmemo->getBaseShippingTaxAmount()
        );
        $order->setShippingTaxRefunded($order->getShippingTaxRefunded() - $creditmemo->getShippingTaxAmount());
        $order->setAdjustmentPositive($order->getAdjustmentPositive() - $creditmemo->getAdjustmentPositive());
        $order->setBaseAdjustmentPositive(
            $order->getBaseAdjustmentPositive() - $creditmemo->getBaseAdjustmentPositive()
        );
        $order->setAdjustmentNegative($order->getAdjustmentNegative() - $creditmemo->getAdjustmentNegative());
        $order->setBaseAdjustmentNegative(
            $order->getBaseAdjustmentNegative() - $creditmemo->getBaseAdjustmentNegative()
        );
        $order->setDiscountRefunded($order->getDiscountRefunded() - $creditmemo->getDiscountAmount());
        $order->setBaseDiscountRefunded($order->getBaseDiscountRefunded() - $creditmemo->getBaseDiscountAmount());

        $this->setTotalAndBaseTotal($creditmemo, $order);

        // Delete credit memo info
        $connection->rawQuery('DELETE FROM `'.$creditmemoGridTable.'` WHERE entity_id='.$creditmemoId);
        $connection->rawQuery('DELETE FROM `'.$creditmemoTable.'` WHERE entity_id='.$creditmemoId);

        $this->saveOrder($order);
        return $order;
    }

    /**
     * Set total and base total for the order.
     *
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @param Order $order
     * @return void
     */
    protected function setTotalAndBaseTotal($creditmemo, Order $order): void
    {
        if ($creditmemo->getDoTransaction()) {
            $order->setTotalOnlineRefunded($order->getTotalOnlineRefunded() - $creditmemo->getGrandTotal());
            $order->setBaseTotalOnlineRefunded($order->getBaseTotalOnlineRefunded() - $creditmemo->getBaseGrandTotal());
        } else {
            $order->setTotalOfflineRefunded($order->getTotalOfflineRefunded() - $creditmemo->getGrandTotal());
            $order->setBaseTotalOfflineRefunded(
                $order->getBaseTotalOfflineRefunded() - $creditmemo->getBaseGrandTotal()
            );
        }
    }

    /**
     * Save the order with the updated state.
     *
     * @param Order $order
     * @return void
     */
    protected function saveOrder(Order $order): void
    {
        if ($order->hasShipments() || $order->hasInvoices() || $order->hasCreditmemos()) {
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
                ->save();
        } elseif (!$order->canInvoice() && !$order->canShip() && !$order->hasCreditmemos()) {
            $order->setState(Order::STATE_COMPLETE)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_COMPLETE))
                ->save();
        } else {
            $order->setState(Order::STATE_NEW)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_NEW))
                ->save();
        }
    }
}
