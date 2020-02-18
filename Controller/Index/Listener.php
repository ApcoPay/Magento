<?php
namespace Apcopay\Magento\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use MyposVirtual\MyposVirtual\Helper\Data;
use Magento\Framework\Registry;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\InvalidRequestException;

class Listener extends Action implements CsrfAwareActionInterface
{

    private $registry;
    private $invoiceService;
    private $invoiceSender;
    private $orderSender;

    public function __construct(
        Context $context,
        Registry $registry,
        InvoiceService $invoiceService,
        Order\Email\Sender\InvoiceSender $invoiceSender,
		Order\Email\Sender\OrderSender $orderSender
    )
    {
        $this->registry = $registry;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;

        parent::__construct($context);
    }

    public function execute()
    {
		http_response_code(200);

        $helper = $this->_objectManager->create('Apcopay\Magento\Helper\Data');
		$helper->log('----- Listener received request -----');

		$received = $this->getRequest()->getPost('params');
		$received = urldecode($received);
		$helper->log('Request: ' . $received);

		$xml = new \DOMDocument();
		$xml->loadXML($received);
		$xml->preserveWhiteSpace = true;

		$transactions = $xml->getElementsByTagName("Transaction");
		if ($transactions->length == 0) {
			$helper->log('Missing Transaction tag');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		$transaction = $transactions->item(0);
		$receivedHash = $transaction->getAttribute("hash");

		$xmlStr = preg_replace('/(hash=")(.*?)(")/', '${1}' . $helper->fastpay_secret . '${3}', $received);
		$generatedHash = hash('sha256', $xmlStr);

		if ($generatedHash !== $receivedHash) {
			$helper->log('Hash mismatch');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}

		// Result tag
		$resultElements = $xml->getElementsByTagName("Result");
		if ($resultElements->length === 0) {
			$helper->log('Missing Result');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		$result = $resultElements->item(0)->nodeValue;
		if (is_null($result) || trim($result) == '') {
			$helper->log('Empty Result');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		$helper->log('Result tag: ' . $result);

		// Oref tag
		$orefElements = $xml->getElementsByTagName("ORef");
		if ($orefElements->length === 0) {
			$helper->log('Missing ORef');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		$oref = $orefElements->item(0)->nodeValue;
		if (is_null($oref) || $oref == 0 || trim($oref) == '') {
			$helper->log('Empty ORef');

			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		$helper->log('ORef tag: ' . $oref);

		// pspid tag
		$pspid = '';
		$pspidElements = $xml->getElementsByTagName("pspid");
		if ($pspidElements->length !== 0) {
			$pspid = $pspidElements->item(0)->nodeValue;
		}
		$helper->log('PspId tag: ' . $pspid);

		// Get order
        /** @var Order $order */
        $order = $this->_objectManager->create('Magento\Sales\Model\Order');
		$order->loadByIncrementId($oref);
        if (!$order->getId()) {
			$helper->log('Invalid order id: ' . $oref);
			
			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}
		
		// Validate order is pending
		if($order->getStatus() != \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT){
			$helper->log('Order already processed. Oref: ' . $oref);
			
			$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
			$result->setHttpResponseCode(400);
			$result->setContents('Error processing request');
			return $result;
		}

		if ($result === "OK") {
			$this->orderSender->send($order);

			// Status
            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
				->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
				->save();

			// Note
			$note = 'Successfully processed transaction.';
			if (!empty($pspid)) {
				$note .= ' ApcoPay PspId: ' . $pspid;
			}
			$order->addStatusHistoryComment($note, false)->save();

			// Invoice
            if($order->canInvoice()) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->register();
                $invoice->setTransactionId($pspid);
                $invoice->save();
                /**
                 * @var \Magento\Framework\DB\Transaction $transactionSave
                 */
                $transactionSave = $this->_objectManager->create('Magento\Framework\DB\Transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder());
                $transactionSave->save();

                $this->invoiceSender->send($invoice);
                //send notification code
                $order->addStatusHistoryComment(__('Notified customer about invoice #%1.', $invoice->getId()))
                    ->setIsCustomerNotified(true)
                    ->save();
            }
            
            $helper->log('Payment completed successfully');
		} else if ($result === "PENDING") {
			$helper->log('Payment status is pending');
		} else {
			// Status
            $order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)
				->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED)
				->save();

			// Generate error message
			$errorMessage = 'Payment failed: ';

			$extendedErrors = $xml->getElementsByTagName("ExtendedErr");
			if ($extendedErrors->length == 0) {
				$errorMessage = $errorMessage . $result;
			} else {
				$extendedError = $extendedErrors->item(0)->nodeValue;
				if (isset($extendedError) && trim($extendedError) !== '') {
					$errorMessage = $errorMessage . $extendedError;
				} else {
					$errorMessage = $errorMessage . $result;
				}
			}

			if (!empty($pspid)) {
				$errorMessage = $errorMessage . ' ApcoPay PspId: ' . $pspid;
			}

            $helper->log('Transaction error: ' . $errorMessage);
            $order
                ->cancel()
                ->addStatusHistoryComment($errorMessage)
                ->setIsCustomerNotified(false)
                ->save();
			$helper->log('Order status updated to failed');
        }

		$result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
		$result->setHttpResponseCode(200);
		$result->setContents('OK');
		return $result;
	}
	
	public function createCsrfValidationException(RequestInterface $request): ? InvalidRequestException
	{
		return null;
	}
		
	public function validateForCsrf(RequestInterface $request): ?bool
	{
		return true;
	}

}