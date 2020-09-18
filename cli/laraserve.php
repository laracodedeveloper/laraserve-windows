<?php

/**
 * Load correct autoloader depending on install location.
 */
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require_once __DIR__.'/../vendor/autoload.php';
} else {
    require_once __DIR__.'/../../../autoload.php';
}

require_once __DIR__.'/includes/compatibility.php';
require_once __DIR__.'/includes/facades.php';
require_once __DIR__.'/includes/helpers.php';

use Illuminate\Container\Container;
use Silly\Application;
use function Laraserve\info;
use function Laraserve\output;
use function Laraserve\table;
use function Laraserve\warning;

/*
 * Relocate config dir to ~/.config/laraserve/ if found in old location.
 */
if (is_dir(LARASERVE_LEGACY_HOME_PATH) && ! is_dir(LARASERVE_HOME_PATH)) {
    Configuration::createConfigurationDirectory();
}

/*
 * Create the application.
 */
Container::setInstance(new Container());

$version = '1.0.0';

$app = new Application('Laraserve for Windows', $version);

/*
 * Prune missing directories and symbolic links on every command.
 */
if (is_dir(LARASERVE_HOME_PATH)) {
    /*
     * Upgrade helper: ensure the tld config exists
     */
    if (empty(Configuration::read()['tld'])) {
        Configuration::writeBaseConfiguration();
    }

    Configuration::prune();

    Site::pruneLinks();
}

/*
 * Allow Laraserve to be run more conveniently by allowing the Node proxy to run password-less sudo.
 */
$app->command('install', function () {
    Nginx::stop();
    PhpFpm::stop();
    Acrylic::stop();

    Configuration::install();

    Nginx::install();
    PhpFpm::install();

    $tld = Configuration::read()['tld'];
    Acrylic::install($tld);

    Nginx::restart();

    output(PHP_EOL.'<info>Laraserve installed successfully!</info>');
})->descriptions('Install the Laraserve services');

/*
 * Get or set the tld currently being used by Laraserve.
 */
$app->command('tld [tld]', function ($tld = null) {
    if ($tld === null) {
        return info(Configuration::read()['tld']);
    }

    $oldTld = Configuration::read()['tld'];
    $tld = trim($tld, '.');

    Acrylic::updateTld($tld);

    Configuration::updateKey('tld', $tld);

    Site::resecureForNewTld($oldTld, $tld);
    PhpFpm::restart();
    Nginx::restart();

    info('Your Laraserve tld has been updated to ['.$tld.'].');
}, ['tld'])->descriptions('Get or set the TLD used for Laraserve sites.');

/*
 * Add the current working directory to the paths configuration.
 */
$app->command('park [path]', function ($path = null) {
    Configuration::addPath($path ?: getcwd());

    info(($path === null ? 'This' : "The [{$path}]")." directory has been added to Laraserve's paths.");
})->descriptions('Register the current working (or specified) directory with Laraserve');

/*
 * Remove the current working directory from the paths configuration.
 */
$app->command('forget [path]', function ($path = null) {
    Configuration::removePath($path ?: getcwd());

    info(($path === null ? 'This' : "The [{$path}]")." directory has been removed from Laraserve's paths.");
})->descriptions('Remove the current working (or specified) directory from Laraserve\'s list of paths');

/*
 * Register a symbolic link with Laraserve.
 */
$app->command('link [name] [--secure]', function ($name, $secure) {
    $linkPath = Site::link(getcwd(), $name = $name ?: basename(getcwd()));

    info('A ['.$name.'] symbolic link has been created in ['.$linkPath.'].');

    if ($secure) {
        $this->runCommand('secure '.$name);
    }
})->descriptions('Link the current working directory to Laraserve');

/*
 * Display all of the registered symbolic links.
 */
$app->command('links', function () {
    $links = Site::links();

    table(['Site', 'SSL', 'URL', 'Path'], $links->all());
})->descriptions('Display all of the registered Laraserve links');

/*
 * Unlink a link from the Laraserve links directory.
 */
$app->command('unlink [name]', function ($name) {
    info('The ['.Site::unlink($name).'] symbolic link has been removed.');
})->descriptions('Remove the specified Laraserve link');

/*
 * Secure the given domain with a trusted TLS certificate.
 */
$app->command('secure [domain]', function ($domain = null) {
    $url = ($domain ?: Site::host(getcwd())).'.'.Configuration::read()['tld'];

    Site::secure($url);

    Nginx::restart();

    info('The ['.$url.'] site has been secured with a fresh TLS certificate.');
})->descriptions('Secure the given domain with a trusted TLS certificate');

/*
 * Stop serving the given domain over HTTPS and remove the trusted TLS certificate.
 */
$app->command('unsecure [domain]', function ($domain = null) {
    $url = ($domain ?: Site::host(getcwd())).'.'.Configuration::read()['tld'];

    Site::unsecure($url);

    Nginx::restart();

    info('The ['.$url.'] site will now serve traffic over HTTP.');
})->descriptions('Stop serving the given domain over HTTPS and remove the trusted TLS certificate');

/*
 * Determine which Laraserve driver the current directory is using.
 */
$app->command('which', function () {
    require __DIR__.'/drivers/require.php';

    $driver = LaraserveDriver::assign(getcwd(), basename(getcwd()), '/');

    if ($driver) {
        info('This site is served by ['.get_class($driver).'].');
    } else {
        warning('Laraserve could not determine which driver to use for this site.');
    }
})->descriptions('Determine which Laraserve driver serves the current working directory');

/*
 * Display all of the registered paths.
 */
$app->command('paths', function () {
    $paths = Configuration::read()['paths'];

    if (count($paths) > 0) {
        output(json_encode($paths, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    } else {
        info('No paths have been registered.');
    }
})->descriptions('Get all of the paths registered with Laraserve');

/*
 * Open the current or given directory in the browser.
 */
 $app->command('open [domain]', function ($domain = null) {
     $url = 'http://'.($domain ?: Site::host(getcwd())).'.'.Configuration::read()['tld'];

     passthru("start $url");
 })->descriptions('Open the site for the current (or specified) directory in your browser');

/*
 * Generate a publicly accessible URL for your project.
 */
$app->command('share', function () {
    $host = Site::host(getcwd());
    $tld = Configuration::read()['tld'];
    $port = Site::port("$host.$tld");
    $port = $port === 443 ? 60 : $port;
    $ngrok = realpath(__DIR__.'/../bin/ngrok.exe');

    passthru("start \"$host.$tld\" \"$ngrok\" http $host.$tld:$port -host-header=rewrite");
})->descriptions('Generate a publicly accessible URL for your project');

/*
 * Echo the currently tunneled URL.
 */
$app->command('fetch-share-url', function () {
    output(Ngrok::currentTunnelUrl());
})->descriptions('Get the URL to the current Ngrok tunnel');

/*
 * Start the daemon services.
 */
$app->command('start', function () {
    PhpFpm::restart();

    Nginx::restart();

    Acrylic::restart();

    info('Laraserve services have been started.');
})->descriptions('Start the Laraserve services');

/*
 * Restart the daemon services.
 */
$app->command('restart', function () {
    PhpFpm::restart();

    Nginx::restart();

    Acrylic::restart();

    info('Laraserve services have been restarted.');
})->descriptions('Restart the Laraserve services');

/*
 * Stop the daemon services.
 */
$app->command('stop', function () {
    PhpFpm::stop();

    Nginx::stop();

    Acrylic::stop();

    info('Laraserve services have been stopped.');
})->descriptions('Stop the Laraserve services');

/*
 * Uninstall Laraserve entirely.
 */
$app->command('uninstall', function () {
    Nginx::uninstall();

    PhpFpm::uninstall();

    Acrylic::uninstall();

    info('Laraserve has been uninstalled.');
})->descriptions('Uninstall the Laraserve services');

/*
 * Determine if this is the latest release of Laraserve.
 */
$app->command('on-latest-version', function () use ($version) {
    if (Laraserve::onLatestVersion($version)) {
        output('YES');
    } else {
        output('NO');
    }
})->descriptions('Determine if this is the latest version of Laraserve');

/*
 * Allow the user to change the version of php laraserve uses
 */
$app->command('use phpVersion', function ($phpVersion) {
    info('Not implemented yet!');
})->descriptions('Change the version of php used by laraserve', [
    'phpVersion' => 'The PHP version you want to use, e.g php@7.2',
]);

/*
 * Load all of the Laraserve extensions.
 */
foreach (Laraserve::extensions() as $extension) {
    include $extension;
}

/*
 * Run the application.
 */
$app->run();
