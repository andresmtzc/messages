<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Log current timestamp
$now = date('Y-m-d H:i:s');

// Read raw POST JSON data
$rawInput = file_get_contents("php://input");
file_put_contents("debug.txt", "$now - Raw input: $rawInput\n", FILE_APPEND);

$data = json_decode($rawInput);

if (!$data) {
    file_put_contents("debug.txt", "$now - JSON decode failed\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: invalid JSON"]]]);
    exit;
}

// Log sender if exists
$sender = $data->query->sender ?? 'no sender';
file_put_contents("debug.txt", "$now - Sender: $sender\n", FILE_APPEND);

// Use environment variables for security
$supabaseUrl = getenv('SUPABASE_URL');  // e.g. https://yourproject.supabase.co/rest/v1/messages
$supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

if (!$supabaseUrl || !$supabaseAnonKey) {
    file_put_contents("debug.txt", "$now - Missing Supabase URL or Anon Key\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["replies" => [["message" => "Server config error: missing credentials"]]]);
    exit;
}

// For debug: fetch first 5 messages WITHOUT filtering by recipient or status
$url = $supabaseUrl . '?select=*&limit=5';

$headers = [
    "apikey: $supabaseAnonKey",
    "Authorization: Bearer $supabaseAnonKey",
    "Content-Type: application/json"
];

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    $curlError = curl_error($ch);
    file_put_contents("debug.txt", "$now - cURL error: $curlError\n", FILE_APPEND);
}
curl_close($ch);

file_put_contents("debug.txt", "$now - Supabase response: $response\n", FILE_APPEND);

http_response_code(200);
echo json_encode([
    "replies" => [
        ["message" => "Debug: Received sender $sender"],
        ["message" => "Debug: Supabase returned: $response"]
    ]
]);
?>
