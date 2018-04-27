<?php
$postData = file_get_contents("php://input");
$postData = json_decode($postData, TRUE);
$f = fopen("log/callbackUrlStatusUpdates.log","a+");
$d = date("Y-m-d H:i:s");
fwrite($f,"----- $d ----- \n");
fwrite($f,print_r($postData,true));
fclose($f);
?>