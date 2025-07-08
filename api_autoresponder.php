<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Debug log file
$logFile = 'debug.txt';
function logDebug($msg) {
    global $logFile;
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

// Read raw POST JSON data
$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->query) &&
    !empty($data->query->sender) &&
    !empty($data->query->message)
) {
    $sender = $data->query->sender;
    logDebug("Raw sender: $sender");

    // Normalize sender (remove all non-digits)
    $normalizedSender = preg_replace('/\D+/', '', $sender);
    logDebug("Normalized sender: $normalizedSender");

    // Get Supabase REST URL and anon key from environment variables
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseAnonKey) {
        http_response_code(500);
        $errorMsg = "Server config error: missing Supabase credentials.";
        logDebug($errorMsg);
        echo json_encode(["replies" => [["message" => $errorMsg]]]);
        exit;
    }

    // Build query string for Supabase
    $queryParams = http_build_query([
        'recipient' => 'eq.' . $normalizedSender,
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $url = $supabaseUrl . '?' . $queryParams;
    logDebug("Query URL: $url");

    // Setup headers for Supabase request
    $headers = [
        "apikey: $supabaseAnonKey",
        "Authorization: Bearer $supabaseAnonKey",
        "Content-Type: application/json"
    ];

    // Init cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        logDebug("cURL error: $error");
        // Respond with error to AutoResponder
        http_response_code(500);
        echo json_encode(["replies" => [["message" => "Error querying database."]]]);
        curl_close($ch);
        exit;
    } else {
        logDebug("Supabase response: $response");
    }

    curl_close($ch);

    $replies = json_decode($response, true);

    $messagesToSend = [];

    if ($replies && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Optionally: mark message as 'sent' to avoid resending
            // (You can implement PATCH request here if needed)
        }
    } else {
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);

} else {
    logDebug("Error: incomplete JSON data received.");
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: incomplete JSON data"]]]);
}
?>
