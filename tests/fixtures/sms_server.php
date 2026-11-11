<?php
declare(strict_types=1);
$mode = trim((string) fgets(STDIN));
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) { exit(1); }
echo 'READY ' . substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n"; flush();
$client = stream_socket_accept($server, 10);
if ($client === false) { exit(2); }
stream_set_timeout($client, 5);
$length = 0;
while (($line = fgets($client)) !== false && trim($line) !== '') {
    if (stripos($line, 'Content-Length:') === 0) { $length = (int) trim(substr($line, 15)); }
}
$body = '';
while (strlen($body) < $length && !feof($client)) { $body .= fread($client, $length - strlen($body)); }
$payload = json_decode($body, true);
$response = match ($mode) {
    'success' => json_encode(['success' => true, 'data' => ['status' => 'success', 'campaign_id' => 42]]),
    'invalid' => 'not-json',
    default => json_encode(['success' => false, 'message' => 'rejected']),
};
$status = $mode === 'error' ? '500 Internal Server Error' : '200 OK';
fwrite($client, "HTTP/1.1 {$status}\r\nContent-Type: application/json\r\nContent-Length: " . strlen($response) . "\r\nConnection: close\r\n\r\n" . $response);
fclose($client); fclose($server);
echo json_encode($payload) . "\n"; flush();
