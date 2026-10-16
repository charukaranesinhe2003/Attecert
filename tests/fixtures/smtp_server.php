<?php

declare(strict_types=1);

// Disposable protocol fixture. Bind only loopback and never read application configuration.
$options = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
$server = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
if ($server === false) throw new RuntimeException('Cannot start local SMTP fixture.');
$address = stream_socket_get_name($server, false);
echo 'READY ' . substr($address, strrpos($address, ':') + 1) . "\n";
flush();
$client = stream_socket_accept($server, 15);
if ($client === false) exit(2);
stream_set_timeout($client, 8);
fwrite($client, "220 fixture.test ESMTP\r\n");
$commands = [];
$body = '';
$authenticated = false;
$authStage = '';
$authUser = '';
while (($line = fgets($client)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($authStage !== '') {
        if ($authStage === 'username') {
            $authUser = (string) base64_decode($line, true);
            $authStage = 'password';
            fwrite($client, "334 UGFzc3dvcmQ6\r\n");
        } else {
            $authenticated = $authUser === 'fixture-user' && base64_decode($line, true) === 'fixture-password';
            $authStage = '';
            fwrite($client, $authenticated ? "235 Authentication successful\r\n" : "535 Authentication failed\r\n");
        }
        continue;
    }
    $verb = strtoupper(explode(' ', $line, 2)[0]);
    $commands[] = $verb; // Never expose AUTH credentials in fixture output.
    switch ($verb) {
        case 'EHLO':
        case 'HELO':
            fwrite($client, "250-fixture.test\r\n250 AUTH LOGIN\r\n");
            break;
        case 'AUTH':
            $authStage = 'username';
            fwrite($client, "334 VXNlcm5hbWU6\r\n");
            break;
        case 'MAIL':
            fwrite($client, "250 Sender accepted\r\n");
            break;
        case 'RCPT':
            $reply = match ($options['mode'] ?? 'accept') {
                'temporary' => '451 Temporary recipient failure',
                'permanent' => '550 Recipient rejected',
                default => '250 Recipient accepted',
            };
            fwrite($client, $reply . "\r\n");
            break;
        case 'DATA':
            fwrite($client, "354 End with a dot\r\n");
            while (($data = fgets($client)) !== false && rtrim($data, "\r\n") !== '.') {
                $body .= str_starts_with($data, '..') ? substr($data, 1) : $data;
            }
            if (($options['mode'] ?? 'accept') === 'disconnect') break 2;
            fwrite($client, "250 2.0.0 queued fixture-id\r\n");
            break;
        case 'RSET':
            fwrite($client, "250 Reset\r\n");
            break;
        case 'QUIT':
            fwrite($client, "221 Bye\r\n");
            break 2;
        default:
            fwrite($client, "502 Unsupported command\r\n");
    }
}
fclose($client);
fclose($server);
echo json_encode(['commands' => $commands, 'authenticated' => $authenticated, 'message' => $body], JSON_THROW_ON_ERROR) . "\n";
flush();
