<?php

namespace App\Services;

class CampaignService
{
    public function __construct(private \PDO $db)
    {
    }

    // ─── Recipient preparation ──────────────────────────────────────

    /**
     * Attach a list of recipients to a campaign.
     * Generates a unique tracking token for each recipient.
     */
    public function prepareCampaign(int $campaignId, array $recipientIds): void
    {
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO campaign_recipients (campaign_id, recipient_id, unique_token)
             VALUES (:campaign_id, :recipient_id, :token)'
        );

        foreach ($recipientIds as $recipientId) {
            $token = generate_uuid();
            $stmt->execute([
                ':campaign_id'  => $campaignId,
                ':recipient_id' => (int) $recipientId,
                ':token'        => $token,
            ]);
        }
    }

    // ─── Template personalisation ───────────────────────────────────

    /**
     * Replace template placeholders with actual recipient data.
     *
     * Supported placeholders:
     *   {{prenom}}, {{nom}}, {{nom_complet}}, {{email}},
     *   {{entreprise}}, {{departement}}, {{tracking_pixel}}, {{phishing_link}}
     */
    public function personalizeTemplate(string $html, array $recipient, string $token, ?string $phishingDomain = null): string
    {
        if ($phishingDomain) {
            // Use the custom phishing domain (strip trailing slash, ensure https)
            $phishingDomain = rtrim($phishingDomain, '/');
            if (!preg_match('#^https?://#', $phishingDomain)) {
                $phishingDomain = 'https://' . $phishingDomain;
            }
            $appUrl = $phishingDomain;
        } else {
            $appUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost:8080', '/');
        }

        $trackingPixelUrl = "{$appUrl}/track/open/{$token}";
        $trackingCssUrl   = "{$appUrl}/track/css/{$token}";
        $trackingFontUrl  = "{$appUrl}/track/font/{$token}";
        $phishingLinkUrl  = "{$appUrl}/track/click/{$token}";

        // Method 1: Classic tracking pixel <img>
        $trackingPixelTag = '<img src="' . htmlspecialchars($trackingPixelUrl, ENT_QUOTES, 'UTF-8')
                          . '" width="1" height="1" alt="" style="display:none;" />';

        // Method 2: @import url() inside <style> block
        // Many clients that strip <link> tags still process inline <style> blocks
        $trackingCssImport = '<style>@import url(\'' . htmlspecialchars($trackingCssUrl, ENT_QUOTES, 'UTF-8') . '\');</style>';

        // Method 3: @font-face with external font URL
        // Clients that block images and CSS imports may still load custom fonts
        $fontFamily = 'F' . substr(md5($token), 0, 8);
        $trackingFontTag = '<style>@font-face{font-family:\'' . $fontFamily . '\';src:url(\''
                         . htmlspecialchars($trackingFontUrl, ENT_QUOTES, 'UTF-8')
                         . '\') format(\'woff2\');font-display:block;}</style>'
                         . '<span style="font-family:\'' . $fontFamily . '\',Arial;font-size:0;line-height:0;color:transparent;mso-hide:all;" aria-hidden="true">&nbsp;</span>';

        // Combined tracking block: pixel + CSS @import + @font-face
        $allTrackingTags = $trackingPixelTag . "\n" . $trackingCssImport . "\n" . $trackingFontTag;

        // Extra tracking tags (without the pixel itself, for injection alongside an existing <img>)
        $extraTrackingTags = $trackingCssImport . "\n" . $trackingFontTag;

        $replacements = [
            '{{prenom}}'         => $recipient['first_name']   ?? $recipient['givenName']   ?? '',
            '{{nom}}'            => $recipient['last_name']    ?? $recipient['surname']      ?? '',
            '{{nom_complet}}'    => $recipient['display_name'] ?? $recipient['displayName']
                                    ?? trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
            '{{email}}'          => $recipient['email']        ?? $recipient['mail']         ?? '',
            '{{entreprise}}'     => $recipient['company']      ?? $recipient['organisation'] ?? 'My Company',
            '{{departement}}'    => $recipient['department']   ?? '',
            '{{phishing_link}}'  => $phishingLinkUrl,
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $html);

        // Resolve dynamic date variables relative to today
        $html = self::resolveDateVariables($html);

        // Handle tracking pixel: if used inside src="{{tracking_pixel}}", replace the <img> src
        // and inject the two extra tracking methods before </body>
        if (strpos($html, 'src="{{tracking_pixel}}"') !== false || strpos($html, "src='{{tracking_pixel}}'") !== false) {
            $html = str_replace('{{tracking_pixel}}', $trackingPixelUrl, $html);
            $html = str_replace('</body>', $extraTrackingTags . "\n</body>", $html);
        } else {
            // Standalone {{tracking_pixel}} placeholder: replace with all three methods
            $html = str_replace('{{tracking_pixel}}', $allTrackingTags, $html);
        }

        return $html;
    }

    // ─── Subject personalisation ─────────────────────────────────────

    /**
     * Replace placeholders in the email subject line.
     *
     * Handles both recipient variables ({{prenom}}, {{nom}}, etc.)
     * and dynamic date variables ({{date_j-1_courte}}, etc.).
     */
    public function personalizeSubject(string $subject, array $recipient): string
    {
        $replacements = [
            '{{prenom}}'      => $recipient['first_name']   ?? $recipient['givenName']   ?? '',
            '{{nom}}'         => $recipient['last_name']    ?? $recipient['surname']      ?? '',
            '{{nom_complet}}' => $recipient['display_name'] ?? $recipient['displayName']
                                  ?? trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? '')),
            '{{email}}'       => $recipient['email']        ?? $recipient['mail']         ?? '',
            '{{entreprise}}'  => $recipient['company']      ?? $recipient['organisation'] ?? 'My Company',
            '{{departement}}' => $recipient['department']   ?? '',
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $subject = self::resolveDateVariables($subject);

        return $subject;
    }

    // ─── Dynamic date resolution ─────────────────────────────────────

    /**
     * Replace date placeholder variables with real dates relative to today.
     *
     * Supported formats:
     *   {{date_j+N}}          → "25 mars 2026"       (long French date)
     *   {{date_j-N}}          → "15 février 2026"     (long French date)
     *   {{date_j+N_courte}}   → "25/03/2026"          (dd/mm/yyyy)
     *   {{date_j-N_courte}}   → "19/03/2026"          (dd/mm/yyyy)
     *   {{date_j-N_heure}}    → "24 Mar 2026, 14:32 (UTC+1)" (with time)
     *
     * Can be called statically so TrackingController can use it for landing pages.
     */
    public static function resolveDateVariables(string $html): string
    {
        $moisFr = [
            1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
            5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
            9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
        ];

        $moisShort = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
        ];

        // Match all {{date_j+N}}, {{date_j-N}}, {{date_j+N_courte}}, {{date_j-N_courte}}, {{date_j-N_heure}}
        return preg_replace_callback(
            '/\{\{date_j([+-]\d+)(?:_(courte|heure))?\}\}/',
            function ($m) use ($moisFr, $moisShort) {
                $offset = (int) $m[1];
                $format = $m[2] ?? 'longue';
                $date = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
                $date->modify("{$offset} days");

                $day   = (int) $date->format('j');
                $month = (int) $date->format('n');
                $year  = $date->format('Y');

                if ($format === 'courte') {
                    return $date->format('d/m/Y');
                }

                if ($format === 'heure') {
                    $hour = rand(7, 18);
                    $min  = rand(10, 59);
                    return sprintf('%d %s %s, %02d:%02d (UTC+1)',
                        $day, $moisShort[$month], $year, $hour, $min);
                }

                // Format long: "1er mars 2026" or "15 février 2026"
                $dayStr = ($day === 1) ? '1er' : (string) $day;
                return "{$dayStr} {$moisFr[$month]} {$year}";
            },
            $html
        );
    }
}
