<?php

/**
 * Check the system's compatibility with Laraserve.
 */
$inTestingEnvironment = strpos($_SERVER['SCRIPT_NAME'], 'phpunit') !== false;

if (PHP_OS !== 'WINNT' && ! $inTestingEnvironment) {
    echo 'Laraserve for Windows only supports the Windows operating system.'.PHP_EOL;

    exit(1);
}

if (version_compare(PHP_VERSION, '5.6.0', '<')) {
    echo 'Laraserve requires PHP 5.6.0 or later.';

    exit(1);
}
