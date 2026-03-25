<?php

namespace App\Controllers;

use App\Services\TrackingService;

class TrackingController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * GET /track/open/{token}
     * Record an email open and return a 1x1 transparent GIF.
     */
    public function open(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordOpen($token, $ip, $ua);

        // 1x1 transparent GIF
        $gif = base64_decode(
            'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
        );

        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen($gif));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo $gif;
        exit;
    }

    /**
     * GET /track/css/{token}
     * Record an email open via external CSS loading and return an empty stylesheet.
     */
    public function openCss(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordOpen($token, $ip, $ua);

        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        echo '/* */';
        exit;
    }

    /**
     * GET /track/font/{token}
     * Record an email open via @font-face loading and return a minimal valid WOFF2 font.
     */
    public function openFont(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordOpen($token, $ip, $ua);

        // Minimal valid WOFF2 font (48-byte header + minimal table directory)
        // This is a structurally valid WOFF2 with signature 'wOF2', containing
        // an empty glyph set – enough for the client to complete the HTTP request.
        $woff2 = base64_decode(
            'd09GMgABAAAAAABIAAsAAAAAAnAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
          . 'AAAAAAAAAAAB4AAAAAAAQDQAAAAAAAAAAAAAAAAAAAAAAAQAAAAAAAAAAAAAAAA'
          . 'AAAAAAAAAAAAAAAA=='
        );

        header('Content-Type: font/woff2');
        header('Content-Length: ' . strlen($woff2));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Access-Control-Allow-Origin: *');
        echo $woff2;
        exit;
    }

    /**
     * GET /track/click/{token}
     * Record a link click and redirect to the phishing landing page.
     */
    public function click(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordClick($token, $ip, $ua);

        // Use phishing domain if configured on the campaign
        $stmt = $this->db->prepare(
            "SELECT c.phishing_domain
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             WHERE cr.unique_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        $baseUrl = '';
        if (!empty($data['phishing_domain'])) {
            $domain = rtrim($data['phishing_domain'], '/');
            if (!preg_match('#^https?://#', $domain)) {
                $domain = 'https://' . $domain;
            }
            $baseUrl = $domain;
        }

        redirect("{$baseUrl}/phish/{$token}");
    }

    /**
     * GET /phish/{token}
     * Record a page visit and render the landing page HTML.
     */
    public function landingPage(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordPageVisit($token, $ip, $ua);

        // Fetch the landing page HTML — use per-recipient template if set, else campaign-level
        $stmt = $this->db->prepare(
            "SELECT lp.html_content, lp.awareness_html, c.phishing_domain
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             JOIN email_templates et ON et.id = COALESCE(cr.email_template_id, c.email_template_id)
             JOIN landing_pages lp ON lp.id = et.landing_page_id
             WHERE cr.unique_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $pageData = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Even if the token is invalid, show a generic page
        if (!$pageData || empty($pageData['html_content'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Page</title></head><body><p>Page not available.</p></body></html>';
            exit;
        }

        $htmlContent = $pageData['html_content'];

        // Build form_action using the phishing domain if set, otherwise relative
        $baseUrl = '';
        if (!empty($pageData['phishing_domain'])) {
            $domain = rtrim($pageData['phishing_domain'], '/');
            if (!preg_match('#^https?://#', $domain)) {
                $domain = 'https://' . $domain;
            }
            $baseUrl = $domain;
        }
        $formAction = "{$baseUrl}/phish/{$token}/submit";
        $htmlContent = str_replace('{{form_action}}', htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8'), $htmlContent);

        // Resolve dynamic date variables
        $htmlContent = \App\Services\CampaignService::resolveDateVariables($htmlContent);
        $htmlContent = str_replace('{{token}}', htmlspecialchars($token, ENT_QUOTES, 'UTF-8'), $htmlContent);

        header('Content-Type: text/html; charset=utf-8');
        echo $htmlContent;
        exit;
    }

    /**
     * POST /phish/{token}/submit
     * Record form submission and redirect to awareness page.
     */
    public function submit(string $token): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $trackingService = new TrackingService($this->db);
        $trackingService->recordSubmission($token, $_POST, $ip, $ua);

        // Use the landing page's redirect_url if set, with phishing domain support
        $stmt = $this->db->prepare(
            "SELECT lp.redirect_url, c.phishing_domain
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             JOIN email_templates et ON et.id = COALESCE(cr.email_template_id, c.email_template_id)
             JOIN landing_pages lp ON lp.id = et.landing_page_id
             WHERE cr.unique_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        $redirectUrl = $data['redirect_url'] ?? null;

        if ($redirectUrl && preg_match('#^https?://#', $redirectUrl)) {
            // Full external URL — redirect directly
            redirect($redirectUrl);
        } else {
            // Relative path — build with phishing domain if set
            $baseUrl = '';
            if (!empty($data['phishing_domain'])) {
                $domain = rtrim($data['phishing_domain'], '/');
                if (!preg_match('#^https?://#', $domain)) {
                    $domain = 'https://' . $domain;
                }
                $baseUrl = $domain;
            }
            redirect("{$baseUrl}/awareness/{$token}");
        }
    }

    /**
     * GET /phish/{token}/awareness
     * Render the awareness/education page.
     */
    public function awareness(string $token): void
    {
        $stmt = $this->db->prepare(
            "SELECT lp.awareness_html
             FROM campaign_recipients cr
             JOIN campaigns c ON c.id = cr.campaign_id
             JOIN email_templates et ON et.id = COALESCE(cr.email_template_id, c.email_template_id)
             JOIN landing_pages lp ON lp.id = et.landing_page_id
             WHERE cr.unique_token = :token"
        );
        $stmt->execute([':token' => $token]);
        $awarenessHtml = $stmt->fetchColumn();

        if (!$awarenessHtml) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html><head><title>Sensibilisation</title></head><body>'
               . '<div style="max-width:600px;margin:50px auto;padding:30px;font-family:Arial,sans-serif;text-align:center">'
               . '<h1 style="color:#4F46E5">&#x1F6E1; Phishing Simulation</h1>'
               . '<p>This was a phishing awareness exercise conducted by your organization.</p>'
               . '<p>Restez vigilant face aux emails suspects !</p>'
               . '</div></body></html>';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $awarenessHtml;
        exit;
    }
}
