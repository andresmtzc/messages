<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Read raw POST JSON data (optional, we’ll log it anyway)
$rawInput = file_get_contents("php://input");
file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Raw input: " . $rawInput . "\n", FILE_APPEND);

// Decode JSON if any (for completeness)
$data = json_decode($rawInput);

$supabaseUrl = getenv('SUPABASE_URL');  // e.g. https://pmcfepoldulhtswwtpkg.supabase.co/rest/v1/messages
$supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

if (!$supabaseUrl || !$supabaseAnonKey) {
    http_response_code(500);
    $errorMsg = "Server configuration error: missing Supabase credentials.";
    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - ERROR: " . $errorMsg . "\n", FILE_APPEND);
    echo json_encode(["replies" => [["message" => $errorMsg]]]);
    exit;
}

// Build URL to fetch all messages (limit 10 for debug)
$testUrl = $supabaseUrl . '?select=*&limit=10';

$headers = [
    "apikey: $supabaseAnonKey",
    "Authorization: Bearer $supabaseAnonKey",
    "Content-Type: application/json"
];

$ch = curl_init($testUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if(curl_errno($ch)) {
    $curlErr = curl_error($ch);
    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - CURL ERROR: " . $curlErr . "\n", FILE_APPEND);
}

curl_close($ch);

file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Supabase response: " . $response . "\n", FILE_APPEND);

// Return a dummy reply to autoresponder so it doesn't hang
http_response_code(200);
echo json_encode(["replies" => [["message" => "Debug test complete. Check debug.txt for details."]]]);
