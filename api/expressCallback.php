<?php
$postData = file_get_contents("php://input");
$postData = json_decode($postData, TRUE);
$checkoutDestinations = $postData["destinations"];

$shippingOptionsFields = array("code","name","description","amount");
$validExpressAddress = "60486";
$validBillingAddress = "60433";
$validShippingAddress = "60325";
// DHL_PAECKCHEN / DHL Päckchen / Unversichert / 4.5
// DHL_PAKET / DHL Paket / Versichert / 6.99
$acceptedShippingOptions = array(
  array(
    "code" => "DHL_PAECKCHEN",
    "name" => "DHL Päckchen",
    "description" => "Unversichert",
    "amount" => "4.5"
  ),
  array(
    "code" => "DHL_PAKET",
    "name" => "DHL Paket",
    "description" => "Versichert",
    "amount" => "6.99"
  )
);
  
$checkedDestinations = array();
foreach ($checkoutDestinations as $destinations => $destination) {
  foreach ($destination as $key => $value) {
    $checkedDestinations[$destinations][$key] = $value;
  }
  if ($checkedDestinations[$destinations]["zip"] == $validExpressAddress) {
    $checkedDestinations[$destinations]["validBillingDestination"] = "true";
    $checkedDestinations[$destinations]["validShippingDestination"] = "true";
  } else if ($checkedDestinations[$destinations]["zip"] == $validBillingAddress) {
    $checkedDestinations[$destinations]["validBillingDestination"] = "true";
    $checkedDestinations[$destinations]["validShippingDestination"] = "false";
  } else if ($checkedDestinations[$destinations]["zip"] == $validShippingAddress) {
    $checkedDestinations[$destinations]["validBillingDestination"] = "false";
    $checkedDestinations[$destinations]["validShippingDestination"] = "true";
  } else {
    $checkedDestinations[$destinations]["validBillingDestination"] = "false";
    $checkedDestinations[$destinations]["validShippingDestination"] = "false";
  }
  $checkedDestinations[$destinations]["shippingOptions"] = $acceptedShippingOptions;
}
$expressCallback["checkedDestinations"] = $checkedDestinations;
$expressCallback = json_encode($expressCallback);

$header = header("Content-Type: application/hal+json;charset=UTF-8");
echo $header . "\r\n" . $expressCallback;
?>