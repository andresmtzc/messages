<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->query) &&
    !empty($data->query->sender) &&
    !empty($data->query->message)
) {
    $sender = $data->query->sender;

    $supabaseUrl = 'https://pmcfepoldulhtswwtpkg.supabase.co/rest/v1/messages';
    $supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InBtY2ZlcG9sZHVsaHRzd3d0cGtnIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTE5MzI5MDcsImV4cCI6MjA2NzUwODkwN30.1hzthlKgqNoNrcIIxaImjw19hIRp5WtY4JhNhcOou_o';

    $query = http_build_query([
        'recipient' => 'eq.' . urlencode($sender),
        'status' => 'eq.pending',
        'select' => '*',
        'limit' => 5
    ]);

    $url = $supabaseUrl . '?' . $query;

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

    $replies = json_decode($response, true);

    $messagesToSend = [];

    if ($replies && count($replies) > 0) {
        foreach ($replies as $reply) {
            $messagesToSend[] = ["message" => $reply['message']];

            // Mark this reply as sent to avoid resending
            $updateCh = curl_init($supabaseUrl . '?id=eq.' . $reply['id']);
            curl_setopt($updateCh, CURLOPT_CUSTOMREQUEST, "PATCH");
            curl_setopt($updateCh, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($updateCh, CURLOPT_POSTFIELDS, json_encode(['status' => 'sent']));
            curl_setopt($updateCh, CURLOPT_RETURNTRANSFER, true);
            curl_exec($updateCh);
            curl_close($updateCh);
        }
    } else {
        $messagesToSend[] = ["message" => "Thanks for your message! We'll get back to you soon."];
    }

    http_response_code(200);
    echo json_encode(["replies" => $messagesToSend]);
} else {
    http_response_code(400);
    echo json_encode(["replies" => [["message" => "Error: incomplete JSON data"]]]);
}
?>
