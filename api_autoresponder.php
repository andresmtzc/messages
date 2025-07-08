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
    // Normalize sender (optional: remove spaces etc. if you want)
    $sender = $data->query->sender;
    // Example normalization if needed:
    // $sender = preg_replace('/[^\+\d]/', '', $sender);

    // Debug sender log
    file_put_contents("debug.txt", "Sender: " . $sender . "\n", FILE_APPEND);

    // Use environment variables (set these in Render or your environment)
    $supabaseUrl = getenv('SUPABASE_URL');  // e.g. https://yourproject.supabase.co/rest/v1/messages
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY');

    if (!$supabaseUrl || !$supabaseAnonKey) {
        http_response_code(500);
        echo json_encode(["replies" => [["message" => "Server config error: missing Supabase credentials."]]]);
        exit;
    }

    // Build query to fetch pending replies for this sender
    $query = http_build_query([
        'recipient' => 'eq.' . $sender,  // <-- FIXED: no urlencode here
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $url = $supabaseUrl . '?' . $query;

    // Debug query URL log
    file_put_contents("debug.txt", "Query URL: " . $url . "\n", FILE_APPEND);

    $headers = [
        "apikey: $supabaseAnonKey",
        "Authorization: Bearer $supabaseAnonKey",
        "Content-Type: application/json"
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    // Debug response log
    file_put_contents("debug.txt", "Supabase response: " . $response . "\n", FILE_APPEND);

    $replies = json_decode($response, true);

    $messagesToSend = [];

    if ($replies && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Mark message as 'sent' to avoid duplicate replies
            $updateCh = curl_init($supabaseUrl . '?id=eq.' . $reply['id']);
            curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($updateCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode(['status' => 'sent']));
            curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($updateCh);
            curl_close($updateCh);
        }
    } else {
        // Default reply if no pending messages found
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);
} else {
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: incomplete JSON data"]]]);
}
?>
