<?php

namespace Apcopay\Magento\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;

class Data extends AbstractHelper
{

	public $isTest;
	public $merch_id;
	public $merch_pass;
	public $profile_id;
	public $fastpay_secret;

	public $fastpay_transaction_type;
	public $fastpay_cards_list;
	public $fastpay_card_restrict;
	public $fastpay_retry;
	public $fastpay_new_card_1_try;
	public $fastpay_new_card_on_fail;

    public function __construct(
		Context $context,
		\Apcopay\Magento\Logger\Logger $logger
    )
    {
		parent::__construct($context);

        $this->_logger = $logger;

		$this->isTest = $this->getConfigValue('test') == 1 ? true : false;
        $this->merch_id = $this->getConfigValue('merch_id');
        $this->merch_pass = $this->getConfigValue('merch_pass');
        $this->profile_id = $this->getConfigValue('profile_id');
		$this->fastpay_secret = $this->getConfigValue('fastpay_secret');
	
		$this->fastpay_transaction_type = $this->getConfigValue('fastpay_transaction_type');
		$this->fastpay_cards_list = $this->getConfigValue('fastpay_cards_list');
		$this->fastpay_card_restrict = $this->getConfigValue('fastpay_card_restrict');
		$this->fastpay_retry = $this->getConfigValue('fastpay_retry');
		$this->fastpay_new_card_1_try = $this->getConfigValue('fastpay_new_card_1_try');
		$this->fastpay_new_card_on_fail = $this->getConfigValue('fastpay_new_card_on_fail');
	}

	public function getConfigValue($field)
	{
		return $this->scopeConfig->getValue('payment/apcopay/' . $field);
	}
	
    public function log($message, $id = null)
    {
		$logText = 'Apcopay: ';
		if ($id) 
		{
			$logText .= 'Order reference: ' . $id . ' - ';
        }
		$logText .= $message;
		$this->_logger->info($logText);
    }

	/**
	 * Gets numeric currency code from alpha 3 currency
	 * @param string $currencyAlpha3
	 * @return string
	 */
	function get_numeric_currency_code($currencyAlpha3)
	{
		$currencies = array(
			'AUD' => '36',
			'CAD' => '124',
			'CHF' => '756',
			'CYP' => '196',
			'DEM' => '280',
			'EUR' => '978',
			'FRF' => '250',
			'GBP' => '826',
			'EGP' => '818',
			'ITL' => '380',
			'JPY' => '392',
			'MTL' => '470',
			'USD' => '840',
			'NOK' => '578',
			'SEK' => '752',
			'RON' => '946',
			'SKK' => '703',
			'CZK' => '203',
			'HUF' => '348',
			'PLN' => '985',
			'DKK' => '208',
			'HKD' => '344',
			'ILS' => '376',
			'EEK' => '233',
			'BRL' => '986',
			'ZAR' => '710',
			'SGD' => '702',
			'LTL' => '440',
			'LVL' => '428',
			'NZD' => '554',
			'TRY' => '949',
			'KRW' => '410',
			'HRK' => '191',
			'BGN' => '975',
			'MXN' => '484',
			'PHP' => '608',
			'RUB' => '643',
			'THB' => '764',
			'CNY' => '156',
			'MYR' => '458',
			'INR' => '356',
			'IDR' => '360',
			'ISK' => '352',
			'CLP' => '152',
			'ARS' => '32',
			'MDL' => '498',
			'NGN' => '566',
			'MAD' => '504',
			'TND' => '788',
			'BTC' => '999',
			'PEN' => '604',
			'BOB' => '68',
			'COP' => '170',
			'PTS' => '899'
		);

		if (!isset($currencyAlpha3) || !is_string($currencyAlpha3) || trim($currencyAlpha3) === '' || !array_key_exists($currencyAlpha3, $currencies)) {
			return null;
		}
		$currencyNumeric = $currencies[$currencyAlpha3];
		if (!isset($currencyNumeric) || trim($currencyNumeric) === '') {
			return null;
		}
		return $currencyNumeric;
    }
    
	function get_payment_data($order, $redirectUrl, $listenerUrl)
	{
		$payRequestData = array();

        $payRequestData['language'] = 'en';
		$payRequestData['redirection_url'] = $redirectUrl;
		$payRequestData['status_url'] = $listenerUrl;

        // Get numeric currency code
        $orderCurrency = $order->getOrderCurrencyCode();
		$payRequestData['currencyCode'] = $this->get_numeric_currency_code($orderCurrency);
		if (is_null($payRequestData['currencyCode'])) {
			$this->log('Unsupported order currency: ' . $orderCurrency);
			return null;
        }

		$payRequestData['merchant_id'] = $this->merch_id;
		$payRequestData['merchant_password'] = $this->merch_pass;
		$payRequestData['profile_id'] = $this->profile_id;
		$payRequestData['fastpay_secret'] = $this->fastpay_secret;
		$payRequestData['value'] = $order->getTotalDue();
		$payRequestData['client_account'] = $order->getCustomerId();
		$payRequestData['order_id'] = $order->getIncrementId();
		$payRequestData['action_type'] = $this->fastpay_transaction_type;
		$payRequestData['fastpay_cards_list'] = $this->fastpay_cards_list;
		$payRequestData['fastpay_card_restrict'] = $this->fastpay_card_restrict;
		$payRequestData['fastpay_retry'] = $this->fastpay_retry;
		$payRequestData['fastpay_new_card_1_try'] = $this->fastpay_new_card_1_try;
		$payRequestData['fastpay_new_card_on_fail'] = $this->fastpay_new_card_on_fail;

		$payRequestData['is_test_mode'] = $this->isTest;
		return $payRequestData;
	}

	function get_payment_url($payRequestData)
	{
        //Building payment request xml
		$xmlDom = new \DOMDocument();
		$xmlRoot = $xmlDom->createElement('Transaction');
		$xmlDom->appendChild($xmlRoot);
		$hashAttribute = $xmlDom->createAttribute('hash');
		$hashAttribute->value = $payRequestData['fastpay_secret'];
		$xmlRoot->appendChild($hashAttribute);

		// Mandatory tags
		$xmlRoot->appendChild($xmlDom->createElement('ProfileID', $payRequestData['profile_id']));
		$xmlRoot->appendChild($xmlDom->createElement('Value', $payRequestData['value']));
		$xmlRoot->appendChild($xmlDom->createElement('Curr', $payRequestData['currencyCode']));
		$xmlRoot->appendChild($xmlDom->createElement('Lang', $payRequestData['language']));
		$xmlRoot->appendChild($xmlDom->createElement('ORef', $payRequestData['order_id']));
		$xmlRoot->appendChild($xmlDom->createElement('UID', $payRequestData['order_id']));
		$xmlRoot->appendChild($xmlDom->createElement('ActionType', $payRequestData['action_type']));
		$xmlRoot->appendChild($xmlDom->createElement('UDF1', ''));
		$xmlRoot->appendChild($xmlDom->createElement('UDF2', ''));
		$xmlRoot->appendChild($xmlDom->createElement('UDF3', ''));
		$xmlRoot->appendChild($xmlDom->createElement('RedirectionURL', $payRequestData['redirection_url']));
		$xmlRoot->appendChild($xmlDom->createElement('ApiPlatform', 'Magento'));
		$xmlRoot->appendChild($xmlDom->createElement('CSSTemplate', 'Plugin'));
		// $xmlRoot->appendChild($xmlDom->createElement('', '')); // If needed, add additional tags here

		$statusUrlNode = $xmlDom->createElement('status_url', $payRequestData['status_url']);
		$xmlRoot->appendChild($statusUrlNode);
		$statusUrlAttribute = $xmlDom->createAttribute('urlEncode');
		$statusUrlAttribute->value = 'true';
		$statusUrlNode->appendChild($statusUrlAttribute);

		// Optional tags
		if (isset($payRequestData['client_account']) && !empty($payRequestData['client_account'])) {
			$xmlRoot->appendChild($xmlDom->createElement('ClientAcc', $payRequestData['client_account']));
		}
		if ($payRequestData['fastpay_cards_list']) {
			$xmlRoot->appendChild($xmlDom->createElement('ListAllCards', 'ALL'));
		} else {
			$xmlRoot->appendChild($xmlDom->createElement('NoCardList', null));
		}
		if ($payRequestData['fastpay_card_restrict']) {
			$xmlRoot->appendChild($xmlDom->createElement('CardRestrict', null));
		}
		if (!$payRequestData['fastpay_retry']) {
			$xmlRoot->appendChild($xmlDom->createElement('noRetry', null));
		}
		if ($payRequestData['fastpay_new_card_1_try']) {
			$xmlRoot->appendChild($xmlDom->createElement('NewCard1Try', null));
		}
		if ($payRequestData['fastpay_new_card_on_fail']) {
			$xmlRoot->appendChild($xmlDom->createElement('NewCardOnFail', null));
		}

		// Test tags
		if ($payRequestData['is_test_mode']) {
			$xmlRoot->appendChild($xmlDom->createElement('TEST', ''));
			$xmlRoot->appendChild($xmlDom->createElement('ForceBank', 'PTEST'));
			$xmlRoot->appendChild($xmlDom->createElement('TESTCARD', ''));
		}

		// Payment xml to string
		$requestXmlString = $xmlDom->saveXML($xmlDom->documentElement);

		// Log merchant tools request
		$merchantToolsRequestLogMessage = 'Sending ApcoPay merchant tools request.';
		$merchantToolsRequestLogMessage .= ' MerchID: ' . $payRequestData['merchant_id'] ;
		$merchantToolsRequestLogMessage .= ' MerchPass: ' . str_repeat("*", strlen($payRequestData['merchant_password'])); // Do not log raw merchant password
		$merchantToolsRequestLogMessage .= ' XMLParam: ' . $requestXmlString;
		$this->log($merchantToolsRequestLogMessage);

		// Send payment request to merchant tools
		$requestXmlEncodedString = urlencode($requestXmlString);
		$requestString = '{"MerchID":"' . $payRequestData['merchant_id'] . '","MerchPass":"' . $payRequestData['merchant_password'] . '","XMLParam":"' . $requestXmlEncodedString . '"}';

		$length = strlen($requestString);
		$headers = array(
			"Content-Type: application/json",
			"Accept: */*",
			"Content-length: " . $length
		);
		$merchantToolBuildTokenUrl = "https://apsp.biz/pay/MagentoLayer/api/Proxy/Pay";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_URL, $merchantToolBuildTokenUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $requestString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

		$responseStr = curl_exec($ch);
		if (curl_errno($ch)) {
			$error_response = curl_error($ch);
		}
		curl_close($ch);

		if (isset($error_response)) {
			$this->log('Error sending request to ApcoPay merchant tools');
			return array(
				'status' => 'error'
			);
		}

		$this->log('Merchant tools response: ' . $responseStr);

		$response = json_decode($responseStr, true);
		$error = urldecode($response["ErrorMsg"]);
		if ($error != "") {
			$this->log('Error sending request to ApcoPay merchant tools');
			return array(
				'status' => 'error'
			);
		}

		$redirectUrl =  $response["BaseURL"] . $response["Token"];
		return array(
			'status' => 'success',
			'url' => $redirectUrl
		);
	}

	public function process_payment($order, $redirectUrl, $listenerUrl)
	{
		$payRequestData = $this->get_payment_data($order, $redirectUrl, $listenerUrl);
		if (is_null($payRequestData)) {
			$this->log('Error sending request to MerchantTools');
			return array(
				'status' => 'error'
			);
		}
		return $this->get_payment_url($payRequestData);
	}
}