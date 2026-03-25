<?php

namespace App\Services;

class MicrosoftGraphService
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private ?string $accessToken = null;
    private int $tokenExpiresAt = 0;

    public function __construct()
    {
        $this->clientId     = $_ENV['CLIENT_ID']     ?? '';
        $this->clientSecret = $_ENV['CLIENT_SECRET'] ?? '';
        $this->tenantId     = $_ENV['TENANT_ID']     ?? '';
    }

    // ─── Authentication ─────────────────────────────────────────────

    /**
     * Obtain an access token via client_credentials grant.
     * Caches the token in memory until it expires.
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->tokenExpiresAt) {
            return $this->accessToken;
        }

        $url = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        $postFields = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
        ]);

        $response = $this->curlRequest('POST', $url, null, [
            'Content-Type: application/x-www-form-urlencoded',
        ], $postFields);

        if (!isset($response['body']['access_token'])) {
            throw new \RuntimeException(
                'Failed to obtain Microsoft Graph access token: '
                . json_encode($response['body'])
            );
        }

        $this->accessToken    = $response['body']['access_token'];
        // Expire 60 seconds early to avoid edge-case failures
        $this->tokenExpiresAt = time() + ($response['body']['expires_in'] ?? 3600) - 60;

        return $this->accessToken;
    }

    // ─── Users ──────────────────────────────────────────────────────

    /**
     * Fetch all users from Microsoft Graph, handling pagination.
     * Filters out entries that have no mail address.
     */
    public function getAllUsers(): array
    {
        $token = $this->getAccessToken();
        $headers = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];

        $url = 'https://graph.microsoft.com/v1.0/users?$select=id,mail,displayName,givenName,surname,jobTitle,department&$top=999';
        $allUsers = [];

        while ($url !== null) {
            $response = $this->curlRequest('GET', $url, null, $headers);

            if ($response['status'] !== 200) {
                throw new \RuntimeException(
                    'Microsoft Graph /users request failed with status ' . $response['status']
                );
            }

            $users = $response['body']['value'] ?? [];
            foreach ($users as $user) {
                if (!empty($user['mail'])) {
                    $allUsers[] = $user;
                }
            }

            $url = $response['body']['@odata.nextLink'] ?? null;
        }

        return $allUsers;
    }

    // ─── Mail creation ──────────────────────────────────────────────

    /**
     * Create a message directly in a user's Inbox via Graph API.
     * The message appears as an unread, received email.
     *
     * Handles HTTP 429 rate-limiting by respecting the Retry-After header.
     */
    /**
     * Check whether a message has been read via Graph API.
     *
     * @return bool|null  true = read, false = unread, null = message not found / error
     */
    public function isMessageRead(string $userEmail, string $messageId): ?bool
    {
        $token = $this->getAccessToken();
        $url = 'https://graph.microsoft.com/v1.0/users/' . urlencode($userEmail)
             . '/messages/' . urlencode($messageId) . '?$select=isRead';

        $response = $this->curlRequest('GET', $url, null, [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ]);

        if ($response['status'] === 200 && isset($response['body']['isRead'])) {
            return (bool) $response['body']['isRead'];
        }

        return null;
    }

    /**
     * Create a message directly in a user's Inbox via Graph API.
     * Returns the Graph message ID on success, or false on failure.
     */
    public function createMailInInbox(
        string $userEmail,
        string $subject,
        string $htmlBody,
        string $senderName,
        string $senderEmail
    ): string|false {
        $token = $this->getAccessToken();

        $url = "https://graph.microsoft.com/v1.0/users/" . urlencode($userEmail) . "/mailFolders/inbox/messages";

        $now = gmdate('Y-m-d\TH:i:s\Z');

        $payload = [
            'subject' => $subject,
            'body'    => [
                'contentType' => 'HTML',
                'content'     => $htmlBody,
            ],
            'from' => [
                'emailAddress' => [
                    'name'    => $senderName,
                    'address' => $senderEmail,
                ],
            ],
            'sender' => [
                'emailAddress' => [
                    'name'    => $senderName,
                    'address' => $senderEmail,
                ],
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'name'    => $userEmail,
                        'address' => $userEmail,
                    ],
                ],
            ],
            'isRead' => false,
            'isDraft' => false,
            'receivedDateTime' => $now,
            'sentDateTime'     => $now,
            'internetMessageHeaders' => [
                ['name' => 'X-Mailer',           'value' => 'Microsoft Outlook 16.0'],
                ['name' => 'X-Phish-Campaign',   'value' => 'sophishticated'],
            ],
            // PR_MESSAGE_FLAGS = 1 (MSGFLAG_READ = 0x01, sans MSGFLAG_UNSENT = 0x04)
            // Valeur 1 = message reçu, non lu. Sans le flag 0x04, ce n'est PAS un brouillon.
            'singleValueExtendedProperties' => [
                [
                    'id'    => 'Integer 0x0E07',
                    'value' => '1',
                ],
            ],
        ];

        $headers = [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ];

        $maxRetries = 3;
        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->curlRequest('POST', $url, $payload, $headers);

            if ($response['status'] === 201 || $response['status'] === 200) {
                return $response['body']['id'] ?? true;
            }

            if ($response['status'] === 429) {
                $retryAfter = $response['retry_after'] ?? 10;
                sleep((int) $retryAfter);
                continue;
            }

            // Any other error – log and return false
            error_log(
                "MicrosoftGraphService::createMailInInbox failed [{$response['status']}]: "
                . json_encode($response['body'])
            );
            return false;
        }

        return false;
    }

    // ─── cURL helper ────────────────────────────────────────────────

    /**
     * Generic cURL request helper.
     *
     * @param  string      $method  HTTP method (GET, POST, PATCH, DELETE …)
     * @param  string      $url     Full URL
     * @param  array|null  $data    JSON body (will be json_encoded)
     * @param  array|null  $headers HTTP headers
     * @param  string|null $rawBody Raw body string (takes precedence over $data)
     * @return array{status: int, body: array, retry_after: int|null}
     */
    private function curlRequest(
        string  $method,
        string  $url,
        ?array  $data    = null,
        ?array  $headers = null,
        ?string $rawBody = null
    ): array {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);

        if ($rawBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        } elseif ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        if ($headers !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: {$error}");
        }

        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = substr($rawResponse, 0, $headerSize);
        $responseBody    = substr($rawResponse, $headerSize);

        // Parse Retry-After header if present
        $retryAfter = null;
        if (preg_match('/Retry-After:\s*(\d+)/i', $responseHeaders, $matches)) {
            $retryAfter = (int) $matches[1];
        }

        $decoded = json_decode($responseBody, true);

        return [
            'status'      => (int) $httpCode,
            'body'        => is_array($decoded) ? $decoded : [],
            'retry_after' => $retryAfter,
        ];
    }
}
