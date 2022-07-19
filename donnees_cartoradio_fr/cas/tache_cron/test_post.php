<?php
// Set The API URL
$url = 'http://www.cartoradio.fr/api/v1/utilisateurs/signin';

// Create a new cURL resource
$ch = curl_init($url);

// Setup request to send json via POST`
$payload = json_encode(array(
        'login' => 'import_cartoradio@ballesta.fr',
        'pwd'   => '//11031049'
    )
);

curl_setopt($ch,CURLOPT_VERBOSE , true);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
//curl_setopt($ch, CURLOPT_FAILONERROR, true);
curl_setopt($ch, CURLOPT_POST, true);

// Attach encoded JSON string to the POST fields
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

// Set the content type to application/json
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);

// Return response instead of outputting
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute the POST request
$result = curl_exec($ch);

// Get the POST request header status
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// If header status is not Created or not OK, return error message
if ( $status == 400  ) {
    die("Error: call to URL $url failed <br>" .
        "with status $status <br>" .
        "response $result, <br>" .
        "curl_error " . curl_error($ch) . "<br>" .
        "curl_errno " . curl_errno($ch) . "<br>");
}

// Close cURL resource
curl_close($ch);

// if you need to process the response from the API further
$response = json_decode($result, true);