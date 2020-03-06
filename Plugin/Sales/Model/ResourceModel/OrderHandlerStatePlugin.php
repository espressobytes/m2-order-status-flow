<?php

namespace Espressobytes\OrderStatusFlow\Plugin\Sales\Model\ResourceModel;

use Magento\Sales\Model\ResourceModel\Order\Handler\State;
use Magento\Sales\Model\Order;

class OrderHandlerStatePlugin
{

    /**
     * @param State $subject
     * @param callable $proceed
     * @param Order $order
     * @return State
     */
    public function aroundCheck(State $subject, callable $proceed, Order $order)
    {
        $currentState = $order->getState();
        if ($currentState == Order::STATE_NEW && $order->getIsInProcess()) {
            // Order would be set to processing
            if (!$order->isCanceled() && !$order->canUnhold() && !$order->canInvoice()) {
                if ($currentState === Order::STATE_NEW && !$order->canShip()) {
                    // only in this case the order status should be set to processing. In all other cases, let the process be done by main class.
                    $order->setState(Order::STATE_PROCESSING)
                        ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
                    return $subject;
                }
            }
        }

        // in all other cases, proceed with the normal procedure.
        return $proceed($order);
    }

}
