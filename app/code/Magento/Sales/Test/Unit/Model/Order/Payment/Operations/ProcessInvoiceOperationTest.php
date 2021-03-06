<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Sales\Test\Unit\Model\Order\Payment\Operations;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\Method\Adapter;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Operations\ProcessInvoiceOperation;
use Magento\Sales\Model\Order\Payment\State\CommandInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Order\Payment\Transaction\ManagerInterface as TransactionManagerInterface;

class ProcessInvoiceOperationTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var TransactionManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $transactionManager;

    /**
     * @var EventManagerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $eventManager;

    /**
     * @var BuilderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $transactionBuilder;

    /**
     * @var CommandInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateCommand;

    /**
     * @var ProcessInvoiceOperation|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $model;

    protected function setUp()
    {
        $this->transactionManager = $this->getMockForAbstractClass(TransactionManagerInterface::class);
        $this->eventManager = $this->getMockForAbstractClass(EventManagerInterface::class);
        $this->transactionBuilder = $this->getMockForAbstractClass(BuilderInterface::class);
        $this->stateCommand = $this->getMockForAbstractClass(CommandInterface::class);

        $this->model = new ProcessInvoiceOperation(
            $this->stateCommand,
            $this->transactionBuilder,
            $this->transactionManager,
            $this->eventManager
        );
    }

    public function testExecute()
    {
        $amountToCapture = $baseGrandTotal = 10;
        $operationMethod = 'sale';
        $storeId = 1;
        $transactionId = '1ASD3456';

        /** @var Order|\PHPUnit_Framework_MockObject_MockObject $order */
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $order->method('getStoreId')
            ->willReturn($storeId);

        /** @var Adapter|\PHPUnit_Framework_MockObject_MockObject $paymentMethod */
        $paymentMethod = $this->getMockBuilder(Adapter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderPayment = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderPayment->method('formatAmount')
            ->with($baseGrandTotal)
            ->willReturnArgument(0);
        $orderPayment->method('getOrder')
            ->willReturn($order);
        $orderPayment->method('getMethodInstance')
            ->willReturn($paymentMethod);
        $orderPayment->expects($this->once())
            ->method('setTransactionId')
            ->with($transactionId);
        $authTransaction = $this->createMock(Transaction::class);
        $orderPayment->expects($this->once())
            ->method('getAuthorizationTransaction')
            ->willReturn($authTransaction);
        $orderPayment->expects($this->once())
            ->method('getIsTransactionPending')
            ->willReturn(true);
        $orderPayment->expects($this->once())
            ->method('getTransactionAdditionalInfo')
            ->willReturn([]);

        $this->transactionManager->expects($this->once())
            ->method('generateTransactionId')
            ->with($orderPayment, Transaction::TYPE_CAPTURE, $authTransaction)
            ->willReturn($transactionId);

        $paymentMethod->method('setStore')
            ->with($storeId);
        $paymentMethod->expects($this->once())
            ->method($operationMethod)
            ->with($orderPayment, $amountToCapture);

        $this->transactionBuilder->expects($this->once())
            ->method('setPayment')
            ->with($orderPayment)
            ->willReturnSelf();

        $invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->getMock();
        $invoice->method('getBaseGrandTotal')
            ->willReturn($baseGrandTotal);

        $this->model->execute($orderPayment, $invoice, $operationMethod);
    }
}
