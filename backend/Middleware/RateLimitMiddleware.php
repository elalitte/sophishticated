<?php
/**
 * RateLimitMiddleware – Session-based rate limiting.
 *
 * Tracks attempt timestamps per key in $_SESSION and returns 429
 * when the limit is exceeded within the decay window.
 */
class RateLimitMiddleware
{
    /**
     * @param string $key          Unique key for the rate-limit bucket (e.g. "login")
     * @param int    $maxAttempts  Maximum allowed attempts within the window
     * @param int    $decayMinutes Length of the sliding window in minutes
     */
    public static function handle(string $key, int $maxAttempts = 5, int $decayMinutes = 1): void
    {
        $storeKey  = "rate_limit_{$key}";
        $now       = time();
        $windowStart = $now - ($decayMinutes * 60);

        // Retrieve stored timestamps (or start fresh)
        $attempts = $_SESSION[$storeKey] ?? [];

        // Prune expired entries
        $attempts = array_values(array_filter($attempts, function (int $ts) use ($windowStart) {
            return $ts >= $windowStart;
        }));

        if (count($attempts) >= $maxAttempts) {
            // Calculate seconds until the oldest attempt expires
            $retryAfter = ($attempts[0] + $decayMinutes * 60) - $now;
            if ($retryAfter < 1) {
                $retryAfter = 1;
            }

            $_SESSION[$storeKey] = $attempts;

            json_response([
                'error'       => 'Too many requests',
                'retry_after' => $retryAfter,
            ], 429);
        }

        // Record this attempt
        $attempts[] = $now;
        $_SESSION[$storeKey] = $attempts;
    }
}
