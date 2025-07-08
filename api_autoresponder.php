<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Read raw POST JSON data
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput);

file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Raw input: " . $rawInput . "\n", FILE_APPEND);

// Validate required fields
if (
    !empty($data->query) &&
    !empty($data->query->sender) &&
    !empty($data->query->message)
) {
    $rawSender = $data->query->sender;

    // Normalize sender by removing spaces, +, hyphens etc
    $normalizedSender = preg_replace('/[^0-9]/', '', $rawSender);

    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Raw sender: $rawSender\n", FILE_APPEND);
    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Normalized sender: $normalizedSender\n", FILE_APPEND);

    // Supabase REST API URL and anon key - set these as environment variables for security
    $supabaseUrl = getenv('SUPABASE_URL');  // e.g. https://yourproject.supabase.co/rest/v1/messages
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseAnonKey) {
        http_response_code(500);
        echo json_encode(["replies" => [["message" => "Server error: missing Supabase credentials."]]]);
        exit;
    }

    // Build query to get pending messages for normalized recipient
    $queryParams = http_build_query([
        'recipient_norm' => 'eq.' . $normalizedSender,
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $requestUrl = $supabaseUrl . '?' . $queryParams;

    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Query URL: $requestUrl\n", FILE_APPEND);

    $headers = [
        "apikey: $supabaseAnonKey",
        "Authorization: Bearer $supabaseAnonKey",
        "Content-Type: application/json"
    ];

    // Fetch pending messages
    $ch = curl_init($requestUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents("debug.txt", date('Y-m-d H:i:s') . " - Supabase response: $response\n", FILE_APPEND);

    $replies = json_decode($response, true);

    $messagesToSend = [];

    if ($replies && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Update status to 'sent' to avoid resending
            $updateUrl = $supabaseUrl . '?id=eq.' . $reply['id'];
            $chUpdate = curl_init($updateUrl);
            curl_setopt($chUpdate, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($chUpdate, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($chUpdate, CURLOPT_POSTFIELDS, json_encode(['status' => 'sent']));
            curl_setopt($chUpdate, CURLOPT_RETURNTRANSFER, true);
            curl_exec($chUpdate);
            curl_close($chUpdate);
        }
    } else {
        // Fallback message if no pending replies found
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);
} else {
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: incomplete JSON data"]]]);
}
?>
