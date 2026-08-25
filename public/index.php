<?php
/**
 * OyubiaCYF front controller — bootstraps the app and routes the request.
 */

require __DIR__ . '/../app/db.php';
require __DIR__ . '/../app/helpers.php';
require __DIR__ . '/../app/auth.php';

date_default_timezone_set(config()['timezone'] ?? 'Africa/Lagos');
start_session();

// ---- Resolve the request path relative to base_url ----
$base = rtrim(config()['base_url'] ?? '', '/');
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri    = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---- Route table: [method, pattern, controller file, handler] ----
$routes = [
    ['GET',  '/login',                       'auth',          'login_form'],
    ['POST', '/login',                       'auth',          'login_submit'],
    ['GET',  '/logout',                       'auth',          'logout_action'],

    ['GET',  '/',                            'dashboard',     'index'],

    ['GET',  '/register',                    'register',      'form'],
    ['POST', '/register',                    'register',      'submit'],

    ['GET',  '/congregations',               'congregations', 'index'],
    ['GET',  '/congregations/new',           'congregations', 'create_form'],
    ['POST', '/congregations',               'congregations', 'store'],
    ['GET',  '/congregations/{id}',          'congregations', 'show'],
    ['GET',  '/congregations/{id}/edit',     'congregations', 'edit_form'],
    ['POST', '/congregations/{id}',          'congregations', 'update'],
    ['POST', '/congregations/{id}/accommodation', 'congregations', 'toggle_accommodation'],
    ['GET',  '/congregations/{id}/roster',   'reports',       'congregation_roster'],
    ['GET',  '/congregations/{id}/roster.csv','reports',      'congregation_roster_csv'],

    ['GET',  '/attendees',                   'attendees',     'index'],
    ['GET',  '/attendees/export.csv',        'attendees',     'export_csv'],
    ['GET',  '/attendees/{id}',              'attendees',     'show'],
    ['GET',  '/attendees/{id}/edit',         'attendees',     'edit_form'],
    ['POST', '/attendees/{id}',              'attendees',     'update'],

    ['GET',  '/admin/users',                 'admin',         'users'],
    ['POST', '/admin/users',                 'admin',         'store_user'],
    ['POST', '/admin/users/{id}/toggle',     'admin',         'toggle_user'],
    ['GET',  '/admin/editions',              'admin',         'editions'],
    ['POST', '/admin/editions',              'admin',         'store_edition'],
    ['POST', '/admin/editions/{id}/activate','admin',         'activate_edition'],
    ['POST', '/admin/demo-seed',              'admin',         'seed_demo'],

    ['GET',  '/admin/merge',                 'admin',         'merge_form'],
    ['POST', '/admin/merge',                 'admin',         'merge_submit'],

    // Forms builder (admin)
    ['GET',  '/admin/forms',                  'forms',        'index'],
    ['GET',  '/admin/forms/file/{rid}/{fid}', 'forms',        'download_file'],
    ['GET',  '/admin/forms/new',              'forms',        'new_form'],
    ['POST', '/admin/forms',                  'forms',        'store'],
    ['GET',  '/admin/forms/{id}/edit',        'forms',        'edit_form'],
    ['GET',  '/admin/forms/{id}/responses.csv','forms',       'responses_csv'],
    ['GET',  '/admin/forms/{id}/responses',   'forms',        'responses'],
    ['POST', '/admin/forms/{id}/status',      'forms',        'set_status'],
    ['POST', '/admin/forms/{id}/delete',      'forms',        'delete'],
    ['POST', '/admin/forms/{id}',             'forms',        'update'],

    // Public form filling (no login)
    ['GET',  '/f/{slug}',                     'public_form',  'show'],
    ['POST', '/f/{slug}',                     'public_form',  'submit'],

    // QR self-registration (admin pages + public /join)
    ['GET',  '/admin/self-register',          'self_register','admin_page'],
    ['POST', '/admin/self-register/toggle',   'self_register','toggle'],
    ['GET',  '/admin/self-register/batches',  'self_register','batches'],
    ['GET',  '/join',                         'self_register','pick'],
    ['POST', '/join',                         'self_register','submit'],
    ['GET',  '/join/{category}',              'self_register','form'],

    // JSON API (PWA offline sync + lookups)
    ['POST', '/api/sync',                    'api',           'sync'],
    ['GET',  '/api/bootstrap',               'api',           'bootstrap'],
    ['GET',  '/api/attendees/search',        'api',           'attendees_search'],
];

foreach ($routes as [$rMethod, $pattern, $controller, $handler]) {
    if ($rMethod !== $method) {
        continue;
    }
    $regex = '#^' . preg_replace('#\{[a-z_]+\}#', '([^/]+)', $pattern) . '$#';
    if (preg_match($regex, $uri, $m)) {
        $params = array_slice($m, 1);
        require __DIR__ . '/../app/controllers/' . $controller . '.php';
        call_user_func_array($handler, $params);
        exit;
    }
}

// ---- 404 ----
http_response_code(404);
if (current_user()) {
    view('errors/404', ['title' => 'Not found']);
} else {
    echo 'Page not found.';
}
