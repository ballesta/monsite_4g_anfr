<?php

$url = "https://www.cartoradio.fr/api/v1/utilisateurs/signin";
echo "Connection $url";
$data = [
    'Host'  => 'www.cartoradio.fr',
    'login' => 'import_cartoradio@ballesta.fr',
    'pwd'   => '//11031049'
];
$data_json = $data->toJson();

$curl = curl_init($url);

curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_POSTFIELDS, $data_json);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($curl);
echo 'curl_exec: ', $response;

$resp = curl_exec($curl);
curl_close($curl);
var_dump($resp);

?>
