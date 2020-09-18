<?php

/**
 * Define the user's "~/.config/laraserve" path.
 */
define('LARASERVE_HOME_PATH', str_replace('\\', '/', $_SERVER['HOME'].'/.config/laraserve'));
define('LARASERVE_STATIC_PREFIX', '41c270e4-5535-4daa-b23e-c269744c2f45');

/**
 * Show the Laraserve 404 "Not Found" page.
 */
function show_laraserve_404()
{
    http_response_code(404);
    require __DIR__.'/cli/templates/404.html';
    exit;
}

/**
 * You may use wildcard DNS providers xip.io or nip.io as a tool for testing your site via an IP address.
 * It's simple to use: First determine the IP address of your local computer (like 192.168.0.10).
 * Then simply use http://project.your-ip.xip.io - ie: http://laravel.192.168.0.10.xip.io.
 */
function laraserve_support_wildcard_dns($domain)
{
    if (in_array(substr($domain, -7), ['.xip.io', '.nip.io'])) {
        // support only ip v4 for now
        $domainPart = explode('.', $domain);
        if (count($domainPart) > 6) {
            $domain = implode('.', array_reverse(array_slice(array_reverse($domainPart), 6)));
        }
    }

    if (strpos($domain, ':') !== false) {
        $domain = explode(':', $domain)[0];
    }

    return $domain;
}

/**
 * Load the Laraserve configuration.
 */
$laraserveConfig = json_decode(
    file_get_contents(LARASERVE_HOME_PATH.'/config.json'), true
);

/**
 * Parse the URI and site / host for the incoming request.
 */
$uri = urldecode(
    explode('?', $_SERVER['REQUEST_URI'])[0]
);

$siteName = basename(
    // Filter host to support wildcard dns feature
    laraserve_support_wildcard_dns($_SERVER['HTTP_HOST']),
    '.'.$laraserveConfig['tld']
);

if (strpos($siteName, 'www.') === 0) {
    $siteName = substr($siteName, 4);
}

/**
 * Determine the fully qualified path to the site.
 */
$laraserveSitePath = null;
$domain = array_slice(explode('.', $siteName), -1)[0];

foreach ($laraserveConfig['paths'] as $path) {
    if (is_dir($path.'/'.$siteName)) {
        $laraserveSitePath = $path.'/'.$siteName;
        break;
    }

    if (is_dir($path.'/'.$domain)) {
        $laraserveSitePath = $path.'/'.$domain;
        break;
    }
}

if (is_null($laraserveSitePath)) {
    show_laraserve_404();
}

$laraserveSitePath = realpath($laraserveSitePath);

/**
 * Find the appropriate Laraserve driver for the request.
 */
$laraserveDriver = null;

require __DIR__.'/cli/drivers/require.php';

$laraserveDriver = LaraserveDriver::assign($laraserveSitePath, $siteName, $uri);

if (! $laraserveDriver) {
    show_laraserve_404();
}

/*
 * Ngrok uses the X-Original-Host to store the forwarded hostname.
 */
if (isset($_SERVER['HTTP_X_ORIGINAL_HOST']) && ! isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_X_FORWARDED_HOST'] = $_SERVER['HTTP_X_ORIGINAL_HOST'];
}

/**
 * Allow driver to mutate incoming URL.
 */
$uri = $laraserveDriver->mutateUri($uri);

/**
 * Determine if the incoming request is for a static file.
 */
$isPhpFile = pathinfo($uri, PATHINFO_EXTENSION) === 'php';

if ($uri !== '/' && ! $isPhpFile && $staticFilePath = $laraserveDriver->isStaticFile($laraserveSitePath, $siteName, $uri)) {
    return $laraserveDriver->serveStaticFile($staticFilePath, $laraserveSitePath, $siteName, $uri);
}

/*
 * Attempt to load server environment variables.
 */
$laraserveDriver->loadServerEnvironmentVariables(
    $laraserveSitePath, $siteName
);

/**
 * Attempt to dispatch to a front controller.
 */
$frontControllerPath = $laraserveDriver->frontControllerPath(
    $laraserveSitePath, $siteName, $uri
);

if (! $frontControllerPath) {
    show_laraserve_404();
}

chdir(dirname($frontControllerPath));

require $frontControllerPath;
