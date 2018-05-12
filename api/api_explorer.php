<?php

class Random {
	private function __construct() {
	}

	public function randomBytes($length) {
		return random_bytes($length);
	}
}

class UUID {
	private function __construct() {
	}

	public function createUUID() {
		$input = Random::randomBytes(16);
		$input = str_split(bin2hex($input),4);
		$input = vsprintf("%s%s-%s-%s-%s-%s%s%s", $input);
		return $input;
	}
}

class Base64Url {
	private function __construct() {
	}

	public function safeEncode($input) {
		return strtr(base64_encode($input), "/+", "_-");
	}

	public function safeDecode($input) {
		return base64_decode(strtr($input, '-_', '+/'));
	}
	
	public function encode($input) {
		return base64_encode($input);
	}

	public function decode($input) {
		return base64_decode($input);
	}
}

class RandomNonce {
	private function __construct() {
	}

	public function nonce() {
	 	$nonce = Random::randomBytes(48);
	 	return Base64Url::safeEncode($nonce);
	}
}

class Signature {
	private function __construct() {
	}

	public function stringToSign($requestID, $timestamp, $APIKEY, $randomNonce) {
		$stringtosign = $requestID .":" .$timestamp .":" .$APIKEY .":" .$randomNonce;
		//echo "String-To-Sign: " . $stringtosign . "\n\n";
		return $stringtosign;
	}

	public function hashSignature($requestID, $APIKEY, $timestamp, $randomNonce, $APISECRET) {
		$stringtosign = self::stringToSign($requestID, $timestamp, $APIKEY, $randomNonce);
		$apiSecretDecoded = Base64Url::safeDecode($APISECRET);
		$hash = hash_hmac("sha256", $stringtosign, $apiSecretDecoded, true);
		$signature = Base64Url::safeEncode($hash);
    //echo "Encoded Signature: " . $signature . "\n\n";
		return $signature;
	}
}

class TokenObtain {
	const sbxTokenObtainEndpoint = "https://api.sandbox.paydirekt.de/api/merchantintegration/v1/token/obtain";
	
	private function __construct() {
	}

	public function getToken() {
		$APIKEY = $_POST["apiKey"];
		$APISECRET = $_POST["apiSecret"];
		if (!empty($_POST["pspApiKey"])) {
		  $PSPAPIKEY = $_POST["pspApiKey"];
		}
		if (!empty($_POST["pspApiSecret"])) {
		  $PSPAPISECRET = $_POST["pspApiSecret"];
		}
		$requestID = UUID::createUUID();
		$randomNonce = RandomNonce::nonce();
		$now = time();
		$timestamp = gmdate("YmdHis",$now);
		$signature = Signature::hashSignature($requestID, $APIKEY, $timestamp, $randomNonce, $APISECRET);
		if ($PSPAPIKEY && $PSPAPISECRET) {
		  $pspSignature = Signature::hashSignature($requestID, $PSPAPIKEY, $timestamp, $randomNonce, $PSPAPISECRET);
		}
		$timestamp = gmdate(DATE_RFC1123, $now);
		$header = array();
		if ($_POST["action"] == "postTokenObtainTp") {
		  array_push($header, "X-Auth-Key-TP: " .$APIKEY);
		  array_push($header, "X-Auth-Code-TP: " .$signature);
		} else {
		  array_push($header, "X-Auth-Key: " .$APIKEY);
		  array_push($header, "X-Auth-Code: " .$signature);
		}
		if ($PSPAPIKEY && $PSPAPISECRET) {
		  array_push($header, "X-Auth-Key-PSP: " .$PSPAPIKEY);
		  array_push($header, "X-Auth-Code-PSP: " .$pspSignature);
		}
		array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "X-Date: " .$timestamp);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		if ($_POST["customerAuthorizationReference"]) {
		  array_push($header, "X-Auth-Customer-Ref: " .$_POST["customerAuthorizationReference"]);
		}
		if ($_POST["merchantAuthorizationReference"]) {
		  array_push($header, "X-Auth-Merchant-Ref: " .$_POST["merchantAuthorizationReference"]);
		}
		$body = array(
            "grantType" => "api_key",
            "randomNonce" => $randomNonce
        );
        $body = json_encode($body);
		return Curl::runCurl($header, $body, self::sbxTokenObtainEndpoint, "POST");
	}
	
	public function autoToken() {
	  $apiSettings = parse_ini_file("settings.ini");
		$APIKEY = $apiSettings["webApiKey"];
		$APISECRET = $apiSettings["webApiSecret"];
		$requestID = UUID::createUUID();
		$randomNonce = RandomNonce::nonce();
		$now = time();
		$timestamp = gmdate("YmdHis",$now);
		$signature = Signature::hashSignature($requestID, $APIKEY, $timestamp, $randomNonce, $APISECRET);
		$timestamp = gmdate(DATE_RFC1123, $now);
		$header = array();
		array_push($header, "X-Auth-Key: " .$APIKEY);
		array_push($header, "X-Auth-Code: " .$signature);
		array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "X-Date: " .$timestamp);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		$body = array(
            "grantType" => "api_key",
            "randomNonce" => $randomNonce
        );
        $body = json_encode($body);
		return Curl::runCurl($header, $body, self::sbxTokenObtainEndpoint, "POST");
	}
}

class CustomerAuthorization {
    const sbxAuthorizationEndpoint = "https://api.sandbox.paydirekt.de/api/thirdpartycustomerauthorization/v1/authorizations";
    
	private function __construct() {
	}
	
	public function createCustomerAuthorization($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$contextInformationFields = array("deviceIpAddress","deviceFingerprint");
		$contextInformation = array();
		foreach ($contextInformationFields as $value) {
			if (!empty($_POST[$value])) {
				$contextInformation[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		
		$usageAgreementFields = array("startDate");
		$usageAgreementAmountFields = array("amount","currency");
		$usageAgreementRecurrenceIntervalFields = array("intervalUnit","interval");

		$usageAgreementAmount = array();
	  foreach ($usageAgreementAmountFields as $value) {
			if (!empty($_POST[$value])) {
				$usageAgreementAmount[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		$usageAgreementRecurrenceInterval = array();
	  foreach ($usageAgreementRecurrenceIntervalFields as $value) {
			if (!empty($_POST[$value])) {
				$usageAgreementRecurrenceInterval[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		$usageAgreement = array();
	  foreach ($usageAgreementFields as $value) {
			if (!empty($_POST[$value])) {
				$usageAgreement[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		

		$payload = array();
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action")) {
				$payload[$key] = $value;
			}
		}
		
		$payload["contextInformation"] = $contextInformation;
		if (!empty($usageAgreement)) {
		  $usageAgreement["amount"] = $usageAgreementAmount;
		  $usageAgreement["recurrenceInterval"] = $usageAgreementRecurrenceInterval;
		  $payload["usageAgreement"] = $usageAgreement;
		}
		$payload = json_encode($payload);
		
		return Curl::runCurl($header, $payload, self::sbxAuthorizationEndpoint, "POST");
	}
	
	public function retrieveCustomerAuthorization($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxAuthorizationEndpoint . "/" . $_POST["thirdPartyCustomerAuthorizationId"];
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
}

class MerchantAuthorization {
    const sbxAuthorizationEndpoint = "https://api.sandbox.paydirekt.de/api/thirdpartymerchantauthorization/v1/authorizations";
    
	private function __construct() {
	}
	
	public function retrieveMerchantAuthorization($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxAuthorizationEndpoint . "/" . $_POST["thirdPartyMerchantAuthorizationId"];
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
}

class Checkout {
	const sbxCheckoutEndpoint = "https://api.sandbox.paydirekt.de/api/checkout/v1/checkouts";
	
	private function __construct() {
	}

	public function shippingAddress() {
		//todo
	}

	public function addItems() {
		if (!isset($_SESSION["shoppingBasket"])) {
			$_SESSION["shoppingBasket"] = array();
		};
		$item = array();
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action")) {
				$item[$key] = $value;
			}
		}
		if (!empty($item)) {
				array_push($_SESSION["shoppingBasket"], $item);
				return json_encode($_SESSION["shoppingBasket"]);
		} else {
			return "Keine Items";
		}
	}

	public function createCheckout($token, $shippingAddress) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$payload = array();
		$deliveryInformationFields = array("expectedShippingDate","logisticsProvider","trackingNumber");
		$contextInformationFields = array("deviceId","deviceFingerprint","deviceIpAddress","terminalId");
		
		if ($_POST["oneClick"]) {
		  $contextInformation = array();
		  foreach ($contextInformationFields as $value) {
			  if (!empty($_POST[$value])) {
				  $contextInformation[$value] = $_POST[$value];
				  unset($_POST[$value]);
			  }
		  }
		  $contextInformation["terminalAddress"]=$shippingAddress;
		  $payload["contextInformation"] = $contextInformation;
		} else if (!$_POST["express"]) {
		  $payload["shippingAddress"] = $shippingAddress;
		}
		
		$deliveryInformation = array();
		foreach ($deliveryInformationFields as $value) {
			if (!empty($_POST[$value])) {
				$deliveryInformation[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		if (!empty($deliveryInformation)) {
		  $payload["deliveryInformation"] = $deliveryInformation;
		}
		
		if ($_SESSION["shoppingBasket"]) {
		  $payload["items"] = $_SESSION["shoppingBasket"];
		  unset($_SESSION["shoppingBasket"]);
		}

		if (!empty($_POST["sha256hashedEmailAddress"])) {
		  $hashedEmail = Base64Url::encode(hash("sha256", $_POST["sha256hashedEmailAddress"], true));
		  $payload["sha256hashedEmailAddress"] = $hashedEmail;
		  unset($_POST["sha256hashedEmailAddress"]);
		}
		
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action")) {
				$payload[$key] = $value;
			}
		}
		
		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, self::sbxCheckoutEndpoint, "POST");
	}
	
	public function createPaylinkCheckout($token, $payId) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$paymentsData = file_get_contents('../paylink/payments.json');
		$paymentsJson = json_decode($paymentsData, true);
		$payment = array_search($payId, array_column($paymentsJson, 'id'));
		echo $payment;
		//$paymentsJson['payments'][$x]['id']
		$payload = array();
		$payload['type'] = "DIRECT_SALE";
		$payload['express'] = "true";
		$payload['currency'] = "EUR";
    $payload['redirectUrlAfterSuccess'] = "https://lauritzen.me/restricted/paydirekt-php-explorer/executePaylink";
    $payload['redirectUrlAfterCancellation'] = "https://lauritzen.me/restricted/paydirekt-php-explorer/#cancel";
    $payload['redirectUrlAfterRejection'] = "https://lauritzen.me/restricted/paydirekt-php-explorer/#reject";
    $payload['callbackUrlCheckDestinations'] = "https://lauritzen.me/restricted/paydirekt-php-explorer/api/expressCallback.php";
    $payload['webUrlShippingTerms'] = "https://lauritzen.me/restricted/paydirekt-php-explorer/#shippingTerms";
    $payload['merchantOrderReferenceNumber'] = "order123";
    $payload['totalAmount'] = "15";
		
		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, self::sbxCheckoutEndpoint, "POST");
	}

	public function executeCheckout($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/execute";
    $payload = array();
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action")) {
				$payload[$key] = $value;
			}
		}
		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, $endpoint, "POST");
	}
	
	public function executePaylinkCheckout($token, $checkoutId) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxCheckoutEndpoint . "/" . $checkoutId . "/execute";
    
    $now = time();
		$timestamp = date("Y-m-d",$now);
    $payload = array();
    $payload['termsAcceptedTimestamp'] = $timestamp;
    $payload['merchantOrderReferenceNumber'] = "order123";
    
		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, $endpoint, "POST");
	}
	
	public function retrieveCheckout($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		//optional, da kein Body
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"];
		return Curl::runCurl($header, false, $endpoint, "GET");
	}
	
	public function updateDeliveryInformation($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/" . "deliveryInformation";
		
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action") && ($key <> "checkoutId")) {
				$payload[$key] = $value;
			}
		}

		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, $endpoint, "PUT");
	}
	
	public function updateInvoiceReference($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/" . "merchantInvoiceReferenceNumber";
		
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action") && ($key <> "checkoutId")) {
				$payload[$key] = $value;
			}
		}
		
		$payload = json_encode($payload);
		return Curl::runCurl($header, $payload, $endpoint, "PUT");
	}

	public function createCapture($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$payload = array();
		$deliveryInformationFields = array("expectedShippingDate","logisticsProvider","trackingNumber");
		
		$deliveryInformation = array();
		foreach ($deliveryInformationFields as $value) {
			if (!empty($_POST[$value])) {
				$deliveryInformation[$value] = $_POST[$value];
				unset($_POST[$value]);
			}
		}
		if (!empty($deliveryInformation)) {
		  $payload["deliveryInformation"] = $deliveryInformation;
		}
		
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action") && ($key <> "checkoutId")) {
				$payload[$key] = $value;
			}
		}
		$payload = json_encode($payload);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/captures";
		return Curl::runCurl($header, $payload, $endpoint, "POST");
	}

	public function retrieveCapture($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		//optional, da kein Body
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/captures/" . $_POST["captureId"];
		return Curl::runCurl($header, false, $endpoint, "GET");
	}

	public function createRefund($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		$payload = array();
		foreach ($_POST as $key => $value) {
			if (!empty($value) && ($key <> "action") && ($key <> "checkoutId")) {
				$payload[$key] = $value;
			}
		}
		$payload = json_encode($payload);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/refunds";
		return Curl::runCurl($header, $payload, $endpoint, "POST");
	}

	public function retrieveRefund($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		//optional, da kein Body
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/refunds/" . $_POST["refundId"];
		return Curl::runCurl($header, false, $endpoint, "GET");
	}

	public function closeOrder($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		//optional, da kein Body
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		$endpoint = self::sbxCheckoutEndpoint . "/" . $_POST["checkoutId"] . "/close";
		return Curl::runCurl($header, false, $endpoint, "POST");
	}
}

class Report {
	const sbxReportsEndpoint = "https://api.sandbox.paydirekt.de/api/reporting/v1/reports/transactions";

	private function __construct() {
	}

	public function getReport($token) {
		//$requestID = UUID::createUUID();
		$now = time();
		$timestamp = gmdate(DATE_RFC1123, $now);
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " .$_POST["reportType"]);
		array_push($header, "Date: " .$timestamp);
		unset($_POST["reportType"]);
		
		$reportParams = "";
		//$reportParamsArray["from"] = "2018-02-26T00:00:00.000Z";
		//$reportParamsArray["to"] = "2018-03-01T00:00:00.000Z";
		
		if (!empty($_POST["from"])) {
		  $reportFromDate = date('Y-m-d\TH:i:s\Z',strtotime($_POST["from"]));
		  $reportParams .= "&" . "from" . "=" . $reportFromDate;
		  unset($_POST["from"]);
		}
		if (!empty($_POST["to"])) {
		  $reportToDate = date('Y-m-d\TH:i:s\Z',strtotime($_POST["to"]));
		  $reportParams .= "&" . "to" . "=" . $reportToDate;
		  unset($_POST["to"]);
		}
		
		$reportFields = array();
		foreach ($_POST["fields"] as $selected) {
		  $reportParams .= "&" . "fields=" . $selected;
		}
		unset($_POST["fields"]);
		
		foreach ($_POST as $reportParam => $value) {
		  if (!empty($value) && ($reportParam <> "action")) {
        $values = explode(",",$_POST[$reportParam]);
		    foreach ($values as $keyValue) {
		      $reportParams .= "&" . $reportParam . "=" . $keyValue;
		    }
		  }
		}
		
		$endpoint = self::sbxReportsEndpoint . "?" . substr($reportParams,1);
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
}

class Accounts {
  const sbxAccountsEndpoint = "https://api.sandbox.paydirekt.de/api/account/v1/accounts";
  const sbxCreditEndpoint = "https://api.sandbox.paydirekt.de/api/credit/v1/credit";
  const sbxTransactionEndpoint = "https://api.sandbox.paydirekt.de/api/transaction/v1/activities/history/accounts";

	private function __construct() {
	}
	
	public function retrieveAccountStatus($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxAccountsEndpoint . "/" . $_POST["accountId"] . "/status";
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
  
	public function retrieveCreditSum($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxCreditEndpoint . "/" . $_POST["accountId"] . "/sum";
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
	
	public function retrieveTransactions($token) {
		//$requestID = UUID::createUUID();
		$timestamp = gmdate(DATE_RFC1123, time());
		$header = array();
		array_push($header, "Authorization: " ."Bearer " .$token);
		//array_push($header, "X-Request-ID: " .$requestID);
		array_push($header, "Content-Type: " ."application/hal+json;charset=utf-8");
		array_push($header, "Accept: " ."application/hal+json");
		array_push($header, "Date: " .$timestamp);
		
		$endpoint = self::sbxTransactionEndpoint . "/" . $_POST["accountId"] . "/transactions";
    return Curl::runCurl($header, false, $endpoint, "GET");
	}
}

class Curl {
	private function __construct() {
	}
	
	public function runCurl($header, $body, $endpoint, $requestType) {
		$request = curl_init();
		curl_setopt($request, CURLOPT_URL, $endpoint);
		curl_setopt($request, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($request, CURLOPT_TIMEOUT, 10);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($request, CURLOPT_HTTPHEADER, $header);
    if ($requestType == "POST") {
			curl_setopt($request, CURLOPT_POST, true);
      curl_setopt($request, CURLOPT_POSTFIELDS, $body);
		}
    if ($requestType == "PUT") {
			curl_setopt($request, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($request, CURLOPT_POSTFIELDS, $body);
		}
    $response = curl_exec($request);
    $responseCode = curl_getinfo($request, CURLINFO_HTTP_CODE);
    curl_close($request);
    return $response;
	}
}

class Output {
	private function __construct() {
	}
	
	public function jsonPrettyPrint($inputString) {
		return json_encode(json_decode($inputString), JSON_PRETTY_PRINT);
	}
	
	function consoleLog($data) {
    $output = $data;
    if ( is_array( $output ) )
        $output = implode( ',', $output);
    echo "<script>console.log( 'Debug Objects: " . $output . "' );</script>";
  }
}

session_start();
header("Content-Type: application/json; charset=utf-8");

if (isset($_POST["action"]) && !empty($_POST["action"])) {
	$action = $_POST["action"];
	switch ($action) {
		case "clearSession":
			$_SESSION = array();
			session_destroy();
			echo '{"notify": "Session zerstört"}';
			break;
		case "setEndpoint":
			$_SESSION = array();
			echo '{"notify": "Endpoint gesetzt"}';
			break;
		case "postTokenObtain":
		case "postTokenObtainTp":
			$userAction = TokenObtain::getToken();
			$token = json_decode($userAction, true);
			$_SESSION["access_token"] = $token["access_token"];
			$_SESSION["token_expiry"] = time()+$token["expires_in"];
			//echo $userAction;
			print_r(json_decode($userAction));
			break;
		case "checkTokenExpiry":
			if (isset($_SESSION["token_expiry"])) {
				if (($_SESSION["token_expiry"]-time()) < 0) {
					echo '{"notify": "Token abgelaufen"}';
				} else {
					echo $_SESSION["token_expiry"]-time();
				}
			} else {
				echo '{"notify": "Kein Token vorhanden"}';
			}
			break;
		case "postShippingAddress":
			$_SESSION["shipping_address"] = array();
			foreach ($_POST as $key => $value) {
				if (!empty($value) && ($key <> "action")) {
					$_SESSION["shipping_address"][$key] = $value;
				}
			}
			echo '{"notify": "Adresse angelegt"}';
			break;
		case "postCustomerAuthorization":
		  if ($_POST["redirect"]) {
			   $redirect = true;
			   unset($_POST["redirect"]);
			}
			$userAction = CustomerAuthorization::createCustomerAuthorization($_SESSION["access_token"]);
			if ($redirect) {
			  $authorization = json_decode($userAction, true);
			  if ($authorization["_links"]["web"]["href"]) {
		      $approveAuthorization = $authorization["_links"]["web"]["href"];
			    echo json_encode(array("redirect" => $approveAuthorization));
			   }
			} else {
				print_r(json_decode($userAction));
			}
			break;
		case "getCustomerAuthorization":
			$userAction = CustomerAuthorization::retrieveCustomerAuthorization($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getMerchantAuthorization":
			$userAction = MerchantAuthorization::retrieveMerchantAuthorization($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "addItem":
			$userAction = Checkout::addItems();
			echo '{"notify": "Item hinzugefügt"}';
			break;
		case "postCheckout":
			if (isset($_SESSION["shipping_address"]) || $_POST["express"]) {
			  if ($_POST["redirect"]) {
			    $redirect = true;
			    unset($_POST["redirect"]);
			  }
				$userAction = Checkout::createCheckout($_SESSION["access_token"], $_SESSION["shipping_address"]);
				if ($redirect) {
			    $checkout = json_decode($userAction, true);
			    if ($checkout["_links"]["approve"]["href"]) {
			      $approveCheckout = $checkout["_links"]["approve"]["href"];
			      echo json_encode(array("redirect" => $approveCheckout));
			    }
				} else {
				  print_r(json_decode($userAction));
				}
			} else {
				echo '{"notify": "Lieferadresse nicht angegeben"}';
			}
			break;
		case "executeCheckout":
			$userAction = Checkout::executeCheckout($_SESSION["access_token"]);
			echo '{"notify": "Bestellung bestätigt"}';
			break;
		case "getCheckout":
			$userAction = Checkout::retrieveCheckout($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "updateDeliveryInformation":
			$userAction = Checkout::updateDeliveryInformation($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "updateInvoiceReference":
			$userAction = Checkout::updateInvoiceReference($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "closeOrder":
			$userAction = Checkout::closeOrder($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "postCapture":
			$userAction = Checkout::createCapture($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getCapture":
			$userAction = Checkout::retrieveCapture($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "postRefund":
			$userAction = Checkout::createRefund($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getRefund":
			$userAction = Checkout::retrieveRefund($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getReport":
		  if ($_POST["reportType"] == "text/csv") {
		    $userAction = Report::getReport($_SESSION["access_token"]);
        $csvData = str_getcsv($userAction, "\n");
        foreach($csvData as &$Row) $Row = print_r(str_getcsv($Row, ";"));
			  //var_dump($userAction);
		  } else {
		    $userAction = Report::getReport($_SESSION["access_token"]);
			  print_r(json_decode($userAction));
		  }
			break;
		case "getAccountsStatus":
			$userAction = Accounts::retrieveAccountStatus($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getAccountsCreditSum":
			$userAction = Accounts::retrieveCreditSum($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getAccountsTransactions":
			$userAction = Accounts::retrieveTransactions($_SESSION["access_token"]);
			print_r(json_decode($userAction));
			break;
		case "getLog":
			$userAction = file_get_contents("../log/callbackUrlStatusUpdates.log");
			echo $userAction;
			break;
		case "eraseLog":
			$userAction = file_put_contents("../log/callbackUrlStatusUpdates.log", "");
			echo '{"notify": "Log geleert"}';
			break;
		case "paylink":
		  //$payId = $_GET["id"];
		  $payId = 5;
			$userAction = TokenObtain::autoToken();
			$token = json_decode($userAction, true);
			$_SESSION["access_token"] = $token["access_token"];
			$userAction = Checkout::createPaylinkCheckout($_SESSION["access_token"],$payId);
			/*
			$checkout = json_decode($userAction, true);
			$_SESSION["checkoutId"] = $checkout["checkoutId"];
			$approveCheckout = $checkout["_links"]["approve"]["href"];
			echo json_encode(array("redirect" => $approveCheckout));
			//print_r(json_decode($userAction));
			*/
			break;
		default:
			echo '{"notify": "Keine Aktion uebergeben"}';
	}
};
?>