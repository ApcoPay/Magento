<?php
namespace Apcopay\Magento\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Redirect extends Action
{
  private $_checkoutSession;

  public function __construct(Context $context, CheckoutSession $checkoutSession)
  {
    $this->_checkoutSession = $checkoutSession;
    return parent::__construct($context);
  }

  public function execute()
  {
	$helper = $this->_objectManager->create('Apcopay\Magento\Helper\Data');
	$helper->log('----- Redirect received request -----');

	$params = $this->getRequest()->getParam('params');
	if(empty($params)){
		$helper->log('Post params missing');

		$this->messageManager->addErrorMessage('Error processing payment request');
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('checkout/cart');
		return $resultRedirect;
	}
	$params = str_replace("\\\"", "\"", $params);
	$helper->log('Params: ' . $params);

	if (!isset($params) || trim($params) === '') {
		$helper->log('Empty params');

		$this->messageManager->addErrorMessage('Error processing payment request');
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('checkout/cart');
		return $resultRedirect;
	}

	$xml = new \DOMDocument();
	$xml->loadXML($params);
	$xml->preserveWhiteSpace = true;

	$transactions = $xml->getElementsByTagName("Transaction");
	if ($transactions->length == 0) {
		$helper->log('Missing Transaction tag');

		$this->messageManager->addErrorMessage('Error processing payment request');
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('checkout/cart');
		return $resultRedirect;
	}
	$transaction = $transactions->item(0);

	// Result tag
	$result = '';
	$resultElements = $xml->getElementsByTagName("Result");
	if ($resultElements->length != 0) {
		$result = $resultElements->item(0)->nodeValue;
	}
	$helper->log('Result tag: ' . $result);
	
	// Oref tag
	$orefElements = $xml->getElementsByTagName("ORef");
	if ($orefElements->length === 0) {
		$helper->log('Missing ORef');

		$this->messageManager->addErrorMessage('Error processing payment request');
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('checkout/cart');
		return $resultRedirect;
	}
	$oref = $orefElements->item(0)->nodeValue;
	if (is_null($oref) || $oref == 0 || trim($oref) == '') {
		$helper->log('Empty ORef');

		$this->messageManager->addErrorMessage('Error processing payment request');
		$resultRedirect = $this->resultRedirectFactory->create();
		$resultRedirect->setPath('checkout/cart');
		return $resultRedirect;
	}
	$helper->log('ORef tag: ' . $oref);
	
	/** @var Order $order */
	$order = $this->_objectManager->create('Magento\Sales\Model\Order');
	$order->loadByIncrementId($oref);

	// Show success/error message
	if (!$order->getId() || $result != 'OK') {
		$this->messageManager->addErrorMessage('Failed to pay order.');
	}else{
		$this->messageManager->addSuccess('Order paid successfully');
	}
	
	$resultRedirect = $this->resultRedirectFactory->create();
	$resultRedirect->setPath('checkout/cart');
	return $resultRedirect;
  }
}