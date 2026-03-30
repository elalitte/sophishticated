<?php

/**
 * Ratchet WebSocket Server for Sophishticated
 *
 * Single server on port 8081 (WEBSOCKET_PORT).
 * Vue.js clients connect and subscribe to channels.
 * The PHP backend (WebSocketBroadcaster) connects as a regular client
 * and sends messages with action=broadcast, which the server redistributes
 * to all subscribers of the specified channel.
 *
 * Usage:  php websocket/server.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// ── Load environment ────────────────────────────────────────────────────
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── WebSocket handler ───────────────────────────────────────────────────

class PhishingTracker implements MessageComponentInterface
{
    /** @var \SplObjectStorage<ConnectionInterface, mixed> */
    protected \SplObjectStorage $clients;

    /** @var array<string, ConnectionInterface[]>  channel => [conn, ...] */
    protected array $subscriptions = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
        echo "PhishingTracker WebSocket server initialised.\n";
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
        echo "New connection: #{$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $data = json_decode($msg, true);

        if (!is_array($data) || empty($data['action'])) {
            return;
        }

        $action = $data['action'];

        switch ($action) {
            case 'subscribe':
                $this->handleSubscribe($from, $data);
                break;

            case 'unsubscribe':
                $this->handleUnsubscribe($from, $data);
                break;

            case 'broadcast':
                // Internal broadcast from PHP backend (WebSocketBroadcaster)
                $this->handleBroadcast($from, $data);
                break;

            default:
                // Unknown action – ignore
                break;
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);

        // Remove from all channel subscriptions
        foreach ($this->subscriptions as $channel => &$subscribers) {
            $subscribers = array_filter($subscribers, function ($c) use ($conn) {
                return $c !== $conn;
            });
            if (empty($subscribers)) {
                unset($this->subscriptions[$channel]);
            }
        }
        unset($subscribers);

        echo "Connection closed: #{$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        echo "Error on #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    // ── Private handlers ────────────────────────────────────────────

    /**
     * Subscribe a connection to a channel.
     * Expected payload: { "action": "subscribe", "channel": "campaign.42" }
     */
    private function handleSubscribe(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        if (empty($channel)) {
            return;
        }

        if (!isset($this->subscriptions[$channel])) {
            $this->subscriptions[$channel] = [];
        }

        // Avoid duplicate subscriptions
        foreach ($this->subscriptions[$channel] as $subscriber) {
            if ($subscriber === $conn) {
                return;
            }
        }

        $this->subscriptions[$channel][] = $conn;
        echo "Connection #{$conn->resourceId} subscribed to '{$channel}'\n";

        // Acknowledge subscription
        $conn->send(json_encode([
            'event'   => 'subscribed',
            'channel' => $channel,
        ]));
    }

    /**
     * Unsubscribe a connection from a channel.
     * Expected payload: { "action": "unsubscribe", "channel": "campaign.42" }
     */
    private function handleUnsubscribe(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        if (empty($channel) || !isset($this->subscriptions[$channel])) {
            return;
        }

        $this->subscriptions[$channel] = array_filter(
            $this->subscriptions[$channel],
            function ($c) use ($conn) {
                return $c !== $conn;
            }
        );

        if (empty($this->subscriptions[$channel])) {
            unset($this->subscriptions[$channel]);
        }

        echo "Connection #{$conn->resourceId} unsubscribed from '{$channel}'\n";
    }

    /**
     * Redistribute a broadcast message to all subscribers of the specified channel.
     * Expected payload: { "action": "broadcast", "channel": "...", "event": "...", "data": {...} }
     *
     * The broadcasting connection does NOT receive the message back.
     */
    private function handleBroadcast(ConnectionInterface $from, array $data): void
    {
        $channel = $data['channel'] ?? null;
        $event   = $data['event']   ?? 'unknown';
        $payload = $data['data']    ?? [];

        if (empty($channel)) {
            return;
        }

        $message = json_encode([
            'channel' => $channel,
            'event'   => $event,
            'data'    => $payload,
        ], JSON_UNESCAPED_UNICODE);

        $subscribers = $this->subscriptions[$channel] ?? [];
        $count = 0;

        foreach ($subscribers as $subscriber) {
            // Don't send back to the broadcaster itself
            if ($subscriber !== $from) {
                $subscriber->send($message);
                $count++;
            }
        }

        echo "Broadcast '{$event}' on '{$channel}' to {$count} subscriber(s)\n";
    }
}

// ── Start server ────────────────────────────────────────────────────────

$host = $_ENV['WEBSOCKET_BIND'] ?? '0.0.0.0';
$port = (int) ($_ENV['WEBSOCKET_PORT'] ?? 8081);

echo "Starting WebSocket server on {$host}:{$port}...\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new PhishingTracker()
        )
    ),
    $port,
    $host
);

$server->run();
