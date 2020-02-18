<?php
namespace Apcopay\Magento\Controller\Index;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

class Index extends Action
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
    $helper->log('----- Index received request -----');

    // Get order
    $order = $this->_checkoutSession->getLastRealOrder();
    if (!$order->getId() || is_null($order)) {
      $helper->log('The order no longer exists.');
      
      $this->messageManager->addErrorMessage('The order no longer exists.');
      $resultRedirect = $this->resultRedirectFactory->create();
      $resultRedirect->setPath('checkout/cart');
      return $resultRedirect;
    }
    
    // Set order state and status
    $order->setState(\Magento\Sales\Model\Order::STATE_NEW)
      ->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT)
      ->save();

    $requestRedirectUrl = $this->_url->getUrl('*/*/redirect');
    $requestListenerUrl = $this->_url->getUrl('*/*/listener');

    $paymentResponse = $helper->process_payment($order, $requestRedirectUrl, $requestListenerUrl);

    if ($paymentResponse['status'] != 'success') {
      $helper->log('Error processing request - Redirecting to error redirect url');
      $this->messageManager->addErrorMessage('Failed to pay order.');
      $resultRedirect = $this->resultRedirectFactory->create();
      $resultRedirect->setPath('checkout/cart');
      return $resultRedirect;
    } else {
      $resultRedirect = $this->resultRedirectFactory->create();
      $resultRedirect->setUrl($paymentResponse['url']);
      return $resultRedirect;
    }
  }
}