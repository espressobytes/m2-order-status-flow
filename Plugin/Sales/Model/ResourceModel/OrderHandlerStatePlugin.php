<?php

namespace Espressobytes\OrderStatusFlow\Plugin\Sales\Model\ResourceModel;

use Espressobytes\OrderStatusFlow\Helper\Config;
use Magento\Sales\Model\ResourceModel\Order\Handler\State;
use Magento\Sales\Model\Order;

class OrderHandlerStatePlugin
{

    /** @var Config */
    protected $configHelper;

    /**
     * OrderHandlerStatePlugin constructor.
     * @param Config $configHelper
     */
    public function __construct(
        Config $configHelper
    )
    {
        $this->configHelper = $configHelper;
    }

    /**
     * Plugin method to intersect the status change, whenever a order with virtual products (that cannot be changed) is set to complete instead of processing.
     *
     * @param State $subject
     * @param callable $proceed
     * @param Order $order
     * @return State
     */
    public function aroundCheck(State $subject, callable $proceed, Order $order)
    {
        $currentState = $order->getState();
        $currentStatus = $order->getStatus();

        $statusSet = false;
        if ($this->configHelper->isStatusReplacementEnabled()) {
            $statusSet = $this->replaceStatusChangeForVirtualOrders($order, $currentState);
        }
        if (!$statusSet) {
            $proceed($order);
        }

        if ($this->configHelper->isStatusChangeCommentEnabled()) {
            $this->commentStatusChange($currentStatus, $order);
        }
        return $subject;
    }

    /**
     * In case when order cannot be shipped (because items are all virtual products), set status and state to configured values in ConfigHelper
     *
     * @param Order $order
     * @param $currentState
     * @return bool
     */
    private function replaceStatusChangeForVirtualOrders(Order $order, $currentState)
    {
        if ($currentState == Order::STATE_NEW && $order->getIsInProcess()) {
            // Order would be set to processing
            if (!$order->isCanceled() && !$order->canUnhold() && !$order->canInvoice()) {
                if ($currentState === Order::STATE_NEW && !$order->canShip()) {

                    // only in this case the order status should be set to processing. In all other cases, let the process be done by main class.
                    $newState = $this->getIntermediateStateForVirtualOrders();
                    $newStatus = $this->getIntermediateStatusForVirtualOrders($order);

                    $order->setState($newState)
                        ->setStatus($newStatus);

                    return true;
                }
            }
        } elseif ($currentState === Order::STATE_PROCESSING && !$order->canShip()) {
            if (!$order->isCanceled() && !$order->canUnhold() && !$order->canInvoice()) {
                return true;
            }
        }

        // in all other cases, proceed with the normal procedure.
        return false;
    }

    /**
     * Optimize: Get Config-Value which can be set up in Adminhtml
     *
     * @return string
     */
    public function getIntermediateStateForVirtualOrders()
    {
        return Order::STATE_PROCESSING;
    }

    /**
     * Optimize: Get Config-Value which can be set up in Adminhtml
     *
     * @param Order $order
     * @return string|null
     */
    public function getIntermediateStatusForVirtualOrders(Order $order)
    {
        return $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING);
    }

    /**
     * @param $oldState
     * @param $oldStatus
     * @param Order $order
     */
    private function commentStatusChange($oldStatus, Order $order)
    {
        $newStatus = $order->getStatus();

        if ($oldStatus != $newStatus) {
            $order->addCommentToStatusHistory(
                __("Update of Order-Status: %1 > %2", $this->getStatusLabel($order, $oldStatus), $this->getStatusLabel($order, $newStatus)));
        }
    }

    /**
     * @param Order $order
     * @param $statusCode
     * @return string|null
     */
    private function getStatusLabel(Order $order, $statusCode)
    {
        try {
            return $order->getConfig()->getStatusLabel($statusCode);
        } catch (\Exception $e) {
            // Optimize: Log or give out Exception. But it is non-critical for order-flow.
        }
        return $statusCode;
    }

}
