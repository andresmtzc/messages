<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Read raw POST JSON data
$data = json_decode(file_get_contents("php://input"));

// Check required fields
if (
    !empty($data->query) &&
    !empty($data->query->sender) &&
    !empty($data->query->message)
) {
    $rawSender = $data->query->sender;

    // Normalize sender: remove anything not 0-9
    $normalizedSender = preg_replace('/\D/', '', $rawSender);

    // Debug logs
    file_put_contents("debug.txt", "Raw sender: " . $rawSender . "\n", FILE_APPEND);
    file_put_contents("debug.txt", "Normalized sender: " . $normalizedSender . "\n", FILE_APPEND);

    // Use environment variables for security
    $supabaseUrl = getenv('SUPABASE_URL');  // example: https://yourproject.supabase.co/rest/v1/messages
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseAnonKey) {
        http_response_code(500);
        echo json_encode(["replies" => [["message" => "Server config error: missing Supabase credentials."]]]);
        exit;
    }

    // Build query to fetch pending messages for normalized sender
    $query = http_build_query([
        'recipient' => 'eq.' . $normalizedSender,
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $url = $supabaseUrl . '?' . $query;

    // Debug query url
    file_put_contents("debug.txt", "Query URL: " . $url . "\n", FILE_APPEND);

    $headers = [
        "apikey: $supabaseAnonKey",
        "Authorization: Bearer $supabaseAnonKey",
        "Content-Type: application/json"
    ];

    // Fetch pending replies from Supabase
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    file_put_contents("debug.txt", "Supabase response: " . $response . "\n", FILE_APPEND);

    $replies = json_decode($response, true);

    $messagesToSend = [];

    if ($replies && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Mark message as 'sent'
            $updateCh = curl_init($supabaseUrl . '?id=eq.' . $reply['id']);
            curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($updateCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode(['status' => 'sent']));
            curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($updateCh);
            curl_close($updateCh);
        }
    } else {
        // Default fallback message
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);
} else {
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: incomplete JSON data"]]]);
}
?>
