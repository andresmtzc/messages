<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Read raw POST input
$rawInput = file_get_contents("php://input");
log_debug("Raw input: " . $rawInput);

$data = json_decode($rawInput);

// Validate input
if (
    isset($data->query) &&
    isset($data->query->sender) &&
    isset($data->query->message)
) {
    $rawSender = $data->query->sender;
    log_debug("Raw sender: " . $rawSender);

    // Normalize sender (remove non-numeric characters)
    $normalizedSender = preg_replace('/\D/', '', $rawSender);
    log_debug("Normalized sender: " . $normalizedSender);

    // Supabase config
    $supabaseUrl = getenv('SUPABASE_URL');
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseAnonKey) {
        http_response_code(500);
        echo json_encode(["replies" => [["message" => "Server config error."]]]);
        exit;
    }

    // Query for pending messages matching the normalized sender in the existing `recipient` column
    $query = http_build_query([
        'recipient' => 'eq.' . $normalizedSender,
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $url = $supabaseUrl . '?' . $query;
    log_debug("Query URL: " . $url);

    $headers = [
        "apikey: $supabaseAnonKey",
        "Authorization: Bearer $supabaseAnonKey",
        "Content-Type: application/json"
    ];

    // Fetch pending messages
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    log_debug("Supabase response: " . $response);
    $replies = json_decode($response, true);

    $messagesToSend = [];

    if (is_array($replies) && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Update status to 'sent'
            $updateCh = curl_init($supabaseUrl . '?id=eq.' . $reply['id']);
            curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($updateCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode(['status' => 'sent']));
            curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($updateCh);
            curl_close($updateCh);
        }
    } else {
        // Fallback if no pending messages found
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);
} else {
    log_debug("Invalid or incomplete JSON received.");
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: Incomplete JSON."]]]);
}
?>
