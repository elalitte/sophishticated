<?php
/**
 * Router – Simple HTTP router with dynamic parameters, middleware support,
 * and SPA fallback.
 */

// ── Middleware includes ────────────────────────────────────────────
require_once __DIR__ . '/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/Middleware/CsrfMiddleware.php';
require_once __DIR__ . '/Middleware/RoleMiddleware.php';
require_once __DIR__ . '/Middleware/RateLimitMiddleware.php';

// ── Controller includes ────────────────────────────────────────────
require_once __DIR__ . '/Controllers/AuthController.php';
require_once __DIR__ . '/Controllers/DashboardController.php';
require_once __DIR__ . '/Controllers/RecipientController.php';
require_once __DIR__ . '/Controllers/GroupController.php';
require_once __DIR__ . '/Controllers/EmailTemplateController.php';
require_once __DIR__ . '/Controllers/LandingPageController.php';
require_once __DIR__ . '/Controllers/CampaignController.php';
require_once __DIR__ . '/Controllers/TrackingController.php';
require_once __DIR__ . '/Controllers/StatsController.php';
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Controllers/PackController.php';

// ── Model includes ─────────────────────────────────────────────────
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Recipient.php';
require_once __DIR__ . '/Models/Group.php';
require_once __DIR__ . '/Models/EmailTemplate.php';
require_once __DIR__ . '/Models/LandingPage.php';
require_once __DIR__ . '/Models/Campaign.php';
require_once __DIR__ . '/Models/CampaignRecipient.php';
require_once __DIR__ . '/Models/TrackingEvent.php';
require_once __DIR__ . '/Models/AuditLog.php';

// ── Service includes ──────────────────────────────────────────────
require_once __DIR__ . '/Services/JsonTemplateStorage.php';
require_once __DIR__ . '/Services/MicrosoftGraphService.php';
require_once __DIR__ . '/Services/CampaignService.php';
require_once __DIR__ . '/Services/TrackingService.php';
require_once __DIR__ . '/Services/StatsService.php';
require_once __DIR__ . '/Services/QueueService.php';
require_once __DIR__ . '/Services/WebSocketBroadcaster.php';

// ── Parse request ──────────────────────────────────────────────────
$requestUri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// ── Middleware presets ──────────────────────────────────────────────
$apiAuth = [
    fn () => AuthMiddleware::handle(),
    fn () => CsrfMiddleware::handle(),
];

$adminAuth = [
    fn () => AuthMiddleware::handle(),
    fn () => CsrfMiddleware::handle(),
    fn () => RoleMiddleware::handle('admin'),
];

$managerAuth = [
    fn () => AuthMiddleware::handle(),
    fn () => CsrfMiddleware::handle(),
    fn () => RoleMiddleware::handle('admin', 'manager'),
];

$noAuth = [];

// ── Route definitions ──────────────────────────────────────────────
// [HTTP_METHOD, pattern, controllerClass, method, middlewares[]]
$routes = [
    // Auth (no auth required for login and me)
    ['POST', '/api/auth/login',              'App\\Controllers\\AuthController',          'login',          [fn () => RateLimitMiddleware::handle('login')]],
    ['POST', '/api/auth/logout',             'App\\Controllers\\AuthController',          'logout',         $apiAuth],
    ['GET',  '/api/auth/me',                 'App\\Controllers\\AuthController',          'me',             $noAuth],
    ['POST', '/api/auth/change-password',    'App\\Controllers\\AuthController',          'changePassword', $apiAuth],

    // Dashboard
    ['GET',  '/api/dashboard',               'App\\Controllers\\DashboardController',     'index',          $apiAuth],

    // Recipients (routes fixes AVANT les routes avec {id})
    ['GET',    '/api/recipients',             'App\\Controllers\\RecipientController',     'index',          $apiAuth],
    ['POST',   '/api/recipients/sync',        'App\\Controllers\\RecipientController',     'sync',           $managerAuth],
    ['GET',    '/api/recipients/departments', 'App\\Controllers\\RecipientController',     'departments',    $apiAuth],
    ['GET',    '/api/recipients/groups',      'App\\Controllers\\GroupController',         'index',          $apiAuth],
    ['POST',   '/api/recipients/groups',      'App\\Controllers\\GroupController',         'store',          $managerAuth],
    ['GET',    '/api/recipients/{id}',        'App\\Controllers\\RecipientController',     'show',           $apiAuth],
    ['PUT',    '/api/recipients/{id}',        'App\\Controllers\\RecipientController',     'update',         $apiAuth],
    ['GET',    '/api/recipients/{id}/history', 'App\\Controllers\\RecipientController',    'history',        $apiAuth],
    ['POST',   '/api/recipients/{id}/groups/{groupId}', 'App\\Controllers\\RecipientController', 'addToGroup', $managerAuth],
    ['DELETE', '/api/recipients/{id}/groups/{groupId}', 'App\\Controllers\\RecipientController', 'removeFromGroup', $managerAuth],

    // Groups (canonical)
    ['GET',    '/api/groups',                 'App\\Controllers\\GroupController',         'index',          $apiAuth],
    ['POST',   '/api/groups',                 'App\\Controllers\\GroupController',         'store',          $managerAuth],
    ['GET',    '/api/groups/{id}',            'App\\Controllers\\GroupController',         'show',           $apiAuth],
    ['PUT',    '/api/groups/{id}',            'App\\Controllers\\GroupController',         'update',         $managerAuth],
    ['DELETE', '/api/groups/{id}',            'App\\Controllers\\GroupController',         'destroy',        $managerAuth],
    ['GET',    '/api/groups/{id}/members',    'App\\Controllers\\GroupController',         'members',        $apiAuth],
    ['POST',   '/api/groups/{id}/members',    'App\\Controllers\\GroupController',         'addMembers',     $managerAuth],
    ['DELETE', '/api/groups/{id}/members',    'App\\Controllers\\GroupController',         'removeMembers',  $managerAuth],
    ['DELETE', '/api/groups/{id}/members/{memberId}', 'App\\Controllers\\GroupController', 'removeSingleMember', $managerAuth],

    // Groups (aliases under /api/recipients/groups for frontend compat)
    ['PUT',    '/api/recipients/groups/{id}', 'App\\Controllers\\GroupController',         'update',         $managerAuth],
    ['DELETE', '/api/recipients/groups/{id}', 'App\\Controllers\\GroupController',         'destroy',        $managerAuth],
    ['GET',    '/api/recipients/groups/{id}/members', 'App\\Controllers\\GroupController', 'members',        $apiAuth],
    ['POST',   '/api/recipients/groups/{id}/members', 'App\\Controllers\\GroupController', 'addMembers',     $managerAuth],
    ['DELETE', '/api/recipients/groups/{id}/members/{memberId}', 'App\\Controllers\\GroupController', 'removeSingleMember', $managerAuth],

    // Email Templates
    ['GET',    '/api/templates',              'App\\Controllers\\EmailTemplateController', 'index',          $apiAuth],
    ['POST',   '/api/templates',              'App\\Controllers\\EmailTemplateController', 'store',          $managerAuth],
    ['GET',    '/api/templates/{id}',         'App\\Controllers\\EmailTemplateController', 'show',           $apiAuth],
    ['PUT',    '/api/templates/{id}',         'App\\Controllers\\EmailTemplateController', 'update',         $managerAuth],
    ['DELETE', '/api/templates/{id}',         'App\\Controllers\\EmailTemplateController', 'destroy',        $managerAuth],
    ['POST',   '/api/templates/{id}/duplicate', 'App\\Controllers\\EmailTemplateController', 'duplicate',   $managerAuth],
    ['POST',   '/api/templates/{id}/toggle-active', 'App\\Controllers\\EmailTemplateController', 'toggleActive', $managerAuth],

    // Landing Pages
    ['GET',    '/api/landing-pages',          'App\\Controllers\\LandingPageController',   'index',          $apiAuth],
    ['POST',   '/api/landing-pages',          'App\\Controllers\\LandingPageController',   'store',          $managerAuth],
    ['GET',    '/api/landing-pages/{id}',     'App\\Controllers\\LandingPageController',   'show',           $apiAuth],
    ['PUT',    '/api/landing-pages/{id}',     'App\\Controllers\\LandingPageController',   'update',         $managerAuth],
    ['DELETE', '/api/landing-pages/{id}',     'App\\Controllers\\LandingPageController',   'destroy',        $managerAuth],
    ['POST',   '/api/landing-pages/{id}/duplicate', 'App\\Controllers\\LandingPageController', 'duplicate',   $managerAuth],
    ['POST',   '/api/landing-pages/{id}/toggle-active', 'App\\Controllers\\LandingPageController', 'toggleActive', $managerAuth],

    // Campaigns
    ['GET',    '/api/campaigns',              'App\\Controllers\\CampaignController',      'index',          $apiAuth],
    ['POST',   '/api/campaigns',              'App\\Controllers\\CampaignController',      'store',          $managerAuth],
    ['GET',    '/api/campaigns/{id}',         'App\\Controllers\\CampaignController',      'show',           $apiAuth],
    ['PUT',    '/api/campaigns/{id}',         'App\\Controllers\\CampaignController',      'update',         $managerAuth],
    ['DELETE', '/api/campaigns/{id}',         'App\\Controllers\\CampaignController',      'destroy',        $managerAuth],
    ['POST',   '/api/campaigns/{id}/launch',  'App\\Controllers\\CampaignController',      'launch',         $managerAuth],
    ['POST',   '/api/campaigns/{id}/pause',   'App\\Controllers\\CampaignController',      'pause',          $managerAuth],
    ['POST',   '/api/campaigns/{id}/resume',  'App\\Controllers\\CampaignController',      'resume',         $managerAuth],
    ['POST',   '/api/campaigns/{id}/cancel',  'App\\Controllers\\CampaignController',      'cancel',         $managerAuth],
    ['POST',   '/api/campaigns/{id}/duplicate', 'App\\Controllers\\CampaignController',    'duplicate',      $managerAuth],
    ['GET',    '/api/campaigns/{id}/recipients', 'App\\Controllers\\CampaignController',   'recipients',     $apiAuth],

    // Statistics
    ['GET',  '/api/stats/dashboard',         'App\\Controllers\\StatsController',          'global',         $apiAuth],
    ['GET',  '/api/stats/dashboard/export',  'App\\Controllers\\StatsController',          'exportGlobal',   $apiAuth],
    ['GET',  '/api/stats/global',            'App\\Controllers\\StatsController',          'global',         $apiAuth],
    ['GET',  '/api/stats/campaign/{id}',     'App\\Controllers\\StatsController',          'campaign',       $apiAuth],
    ['GET',  '/api/stats/group/{id}',        'App\\Controllers\\StatsController',          'group',          $apiAuth],
    ['GET',  '/api/stats/recipient/{id}',    'App\\Controllers\\StatsController',          'recipient',      $apiAuth],
    ['GET',  '/api/stats/export/{type}',     'App\\Controllers\\StatsController',          'export',         $apiAuth],
    ['GET',  '/api/stats/export/{type}/{id}', 'App\\Controllers\\StatsController',         'export',         $apiAuth],

    // Template Packs
    ['GET',    '/api/packs',                'App\\Controllers\\PackController',           'index',          $apiAuth],
    ['GET',    '/api/packs/{filename}',     'App\\Controllers\\PackController',           'show',           $apiAuth],
    ['POST',   '/api/packs/import',         'App\\Controllers\\PackController',           'import',         $managerAuth],
    ['POST',   '/api/packs/export',         'App\\Controllers\\PackController',           'export',         $managerAuth],

    // Administration
    ['GET',    '/api/admin/users',            'App\\Controllers\\AdminController',          'index',          $adminAuth],
    ['POST',   '/api/admin/users',            'App\\Controllers\\AdminController',          'store',          $adminAuth],
    ['PUT',    '/api/admin/users/{id}',       'App\\Controllers\\AdminController',          'update',         $adminAuth],
    ['DELETE', '/api/admin/users/{id}',       'App\\Controllers\\AdminController',          'destroy',        $adminAuth],
    ['GET',    '/api/admin/audit-log',        'App\\Controllers\\AdminController',          'auditLog',       $adminAuth],
    ['GET',    '/api/admin/audit-logs',       'App\\Controllers\\AdminController',          'auditLog',       $adminAuth],
    ['POST',   '/api/admin/users/{id}/reset-password', 'App\\Controllers\\AdminController', 'resetPassword', $adminAuth],

    // Tracking (public - no auth, no CSRF)
    ['GET',  '/track/open/{token}',          'App\\Controllers\\TrackingController',        'open',           $noAuth],
    ['GET',  '/track/css/{token}',           'App\\Controllers\\TrackingController',        'openCss',        $noAuth],
    ['GET',  '/track/font/{token}',          'App\\Controllers\\TrackingController',        'openFont',       $noAuth],
    ['GET',  '/track/click/{token}',         'App\\Controllers\\TrackingController',        'click',          $noAuth],
    ['GET',  '/phish/{token}',               'App\\Controllers\\TrackingController',        'landingPage',    $noAuth],
    ['POST', '/phish/{token}/submit',        'App\\Controllers\\TrackingController',        'submit',         $noAuth],
    ['GET',  '/awareness/{token}',           'App\\Controllers\\TrackingController',        'awareness',      $noAuth],
];

// ── Route matching ─────────────────────────────────────────────────

function matchRoute(string $pattern, string $uri): array|false
{
    $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $uri, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

$matched = false;

foreach ($routes as [$method, $pattern, $controllerClass, $controllerMethod, $middlewares]) {
    if ($requestMethod !== $method) {
        continue;
    }

    $params = matchRoute($pattern, $requestUri);
    if ($params === false) {
        continue;
    }

    $matched = true;

    // Execute middlewares
    foreach ($middlewares as $mw) {
        $mw();
    }

    // Cast numeric params (id, groupId, memberId) to int
    $castParams = array_map(function ($v) {
        return ctype_digit((string) $v) ? (int) $v : $v;
    }, array_values($params));

    // Instantiate controller and call method
    $controller = new $controllerClass(getDB());
    $controller->{$controllerMethod}(...$castParams);
    exit;
}

// ── SPA fallback ───────────────────────────────────────────────────
if (!$matched) {
    $spaPath = __DIR__ . '/../public/dist/index.html';
    if (file_exists($spaPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($spaPath);
    } else {
        http_response_code(404);
        echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 – Not Found</h1><p>Build the frontend first: npm run build</p></body></html>';
    }
    exit;
}
