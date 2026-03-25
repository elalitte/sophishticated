<?php

namespace App\Services;

/**
 * Broadcasts events to the Ratchet WebSocket server.
 *
 * Connects as a regular WebSocket client and sends a JSON message with
 * action=broadcast so the server redistributes it to channel subscribers.
 */
class WebSocketBroadcaster
{
    /**
     * Send a broadcast message to all subscribers of a channel.
     *
     * @param string $channel  Channel name (e.g. 'campaign.42', 'sync')
     * @param string $event    Event name (e.g. 'email.opened')
     * @param array  $data     Payload data
     */
    public static function broadcast(string $channel, string $event, array $data = []): void
    {
        $host = $_ENV['WEBSOCKET_HOST'] ?? '127.0.0.1';
        $port = $_ENV['WEBSOCKET_PORT'] ?? 8081;

        $message = json_encode([
            'action'  => 'broadcast',
            'channel' => $channel,
            'event'   => $event,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);

        try {
            // Connect via raw TCP and perform a minimal WebSocket handshake
            $socket = @fsockopen($host, (int) $port, $errno, $errstr, 2);
            if ($socket === false) {
                error_log("WebSocketBroadcaster: cannot connect to {$host}:{$port} – {$errstr}");
                return;
            }

            // WebSocket upgrade handshake
            $key = base64_encode(random_bytes(16));
            $handshake  = "GET / HTTP/1.1\r\n";
            $handshake .= "Host: {$host}:{$port}\r\n";
            $handshake .= "Upgrade: websocket\r\n";
            $handshake .= "Connection: Upgrade\r\n";
            $handshake .= "Sec-WebSocket-Key: {$key}\r\n";
            $handshake .= "Sec-WebSocket-Version: 13\r\n";
            $handshake .= "\r\n";

            fwrite($socket, $handshake);

            // Read handshake response
            stream_set_timeout($socket, 2);
            $response = '';
            while (($line = fgets($socket)) !== false) {
                $response .= $line;
                if (rtrim($line) === '') {
                    break;
                }
            }

            if (strpos($response, '101') === false) {
                error_log("WebSocketBroadcaster: handshake failed – " . trim($response));
                fclose($socket);
                return;
            }

            // Send the message as a masked WebSocket text frame
            fwrite($socket, self::encodeFrame($message));

            // Close gracefully
            fclose($socket);
        } catch (\Throwable $e) {
            error_log("WebSocketBroadcaster: " . $e->getMessage());
        }
    }

    /**
     * Encode a text message into a masked WebSocket frame.
     */
    private static function encodeFrame(string $payload): string
    {
        $length = strlen($payload);
        $frame  = chr(0x81); // FIN + text opcode

        $mask = random_bytes(4);

        if ($length <= 125) {
            $frame .= chr($length | 0x80); // mask bit set
        } elseif ($length <= 65535) {
            $frame .= chr(126 | 0x80);
            $frame .= pack('n', $length);
        } else {
            $frame .= chr(127 | 0x80);
            $frame .= pack('J', $length);
        }

        $frame .= $mask;

        // Apply mask to payload
        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }
}
