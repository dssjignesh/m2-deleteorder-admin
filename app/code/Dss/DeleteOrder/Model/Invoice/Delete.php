<?php

declare(strict_types= 1);

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
* @copyright Copyright (c) 2024 Digit Software Solutions. ( https://digitsoftsol.com )
*/
namespace Dss\DeleteOrder\Model\Invoice;

use Magento\Framework\App\ResourceConnection;
use Dss\DeleteOrder\Helper\Data;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;

class Delete
{
    /**
     * Delete constructor.
     *
     * @param ResourceConnection $resource
     * @param Data $data
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param Order $order
     */
    public function __construct(
        protected ResourceConnection $resource,
        protected Data $data,
        protected InvoiceRepositoryInterface $invoiceRepository,
        protected Order $order
    ) {
    }

    /**
     * Delete the invoice by Id and revert the odrer state.
     *
     * @param string $invoiceId
     * @return Order
     * @throws \Exception
     */
    public function deleteInvoice(string $invoiceId)
    {
        $connection = $this->resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $invoiceGridTable = $connection->getTableName($this->data->getTableName('sales_invoice_grid'));
        $invoiceTable = $connection->getTableName($this->data->getTableName('sales_invoice'));
        $invoice = $this->invoiceRepository->get($invoiceId);
        $orderId = $invoice->getOrder()->getId();
        $order = $this->order->load($orderId);
        $orderItems = $order->getAllItems();
        $invoiceItems = $invoice->getAllItems();

        // Revert item in order
        foreach ($orderItems as $item) {
            foreach ($invoiceItems as $invoiceItem) {
                if ($invoiceItem->getOrderItemId() == $item->getItemId()) {
                    $item->setQtyInvoiced($item->getQtyInvoiced() - $invoiceItem->getQty());
                    $item->setTaxInvoiced($item->getTaxInvoiced() - $invoiceItem->getTaxAmount());
                    $item->setBaseTaxInvoiced($item->getBaseTaxInvoiced() - $invoiceItem->getBaseTaxAmount());
                    $item->setDiscountTaxCompensationInvoiced(
                        $item->getDiscountTaxCompensationInvoiced() - $invoiceItem->getDiscountTaxCompensationAmount()
                    );
                    $baseDiscountTaxItem = $item->getBaseDiscountTaxCompensationInvoiced();
                    $baseDiscountTaxInvoice = $invoiceItem->getBaseDiscountTaxCompensationAmount();
                    $item->setBaseDiscountTaxCompensationInvoiced(
                        $baseDiscountTaxItem - $baseDiscountTaxInvoice
                    );

                    $item->setDiscountInvoiced($item->getDiscountInvoiced() - $invoiceItem->getDiscountAmount());
                    $item->setBaseDiscountInvoiced(
                        $item->getBaseDiscountInvoiced() - $invoiceItem->getBaseDiscountAmount()
                    );

                    $item->setRowInvoiced($item->getRowInvoiced() - $invoiceItem->getRowTotal());
                    $item->setBaseRowInvoiced($item->getBaseRowInvoiced() - $invoiceItem->getBaseRowTotal());
                }
            }
        }
        // Revert info in order
        $order->setTotalInvoiced($order->getTotalInvoiced() - $invoice->getGrandTotal());
        $order->setBaseTotalInvoiced($order->getBaseTotalInvoiced() - $invoice->getBaseGrandTotal());

        $order->setSubtotalInvoiced($order->getSubtotalInvoiced() - $invoice->getSubtotal());
        $order->setBaseSubtotalInvoiced($order->getBaseSubtotalInvoiced() - $invoice->getBaseSubtotal());

        $order->setTaxInvoiced($order->getTaxInvoiced() - $invoice->getTaxAmount());
        $order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() - $invoice->getBaseTaxAmount());

        $order->setDiscountTaxCompensationInvoiced(
            $order->getDiscountTaxCompensationInvoiced() - $invoice->getDiscountTaxCompensationAmount()
        );
        $order->setBaseDiscountTaxCompensationInvoiced(
            $order->getBaseDiscountTaxCompensationInvoiced() - $invoice->getBaseDiscountTaxCompensationAmount()
        );
        $order->setShippingTaxInvoiced($order->getShippingTaxInvoiced() - $invoice->getShippingTaxAmount());
        $order->setBaseShippingTaxInvoiced($order->getBaseShippingTaxInvoiced() - $invoice->getBaseShippingTaxAmount());

        $order->setShippingInvoiced($order->getShippingInvoiced() - $invoice->getShippingAmount());
        $order->setBaseShippingInvoiced($order->getBaseShippingInvoiced() - $invoice->getBaseShippingAmount());

        $order->setDiscountInvoiced($order->getDiscountInvoiced() - $invoice->getDiscountAmount());
        $order->setBaseDiscountInvoiced($order->getBaseDiscountInvoiced() - $invoice->getBaseDiscountAmount());
        $order->setBaseTotalInvoicedCost($order->getBaseTotalInvoicedCost() - $invoice->getBaseCost());

        if ($invoice->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID) {
            $order->setTotalPaid($order->getTotalPaid() - $invoice->getGrandTotal());
            $order->setBaseTotalPaid($order->getBaseTotalPaid() - $invoice->getBaseGrandTotal());
        }

        // Delete invoice info
        $connection->rawQuery('DELETE FROM `'.$invoiceGridTable.'` WHERE entity_id='.$invoiceId);
        $connection->rawQuery('DELETE FROM `'.$invoiceTable.'` WHERE entity_id='.$invoiceId);
        if ($order->hasShipments() || $order->hasInvoices() || $order->hasCreditmemos()) {
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING))
                ->save();
        } else {
            $order->setState(\Magento\Sales\Model\Order::STATE_NEW)
                ->setStatus($order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_NEW))
                ->save();
        }
        return $order;
    }
}
