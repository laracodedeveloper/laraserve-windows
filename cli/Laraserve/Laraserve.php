<?php

namespace Laraserve;

use Httpful\Request;

class Laraserve
{
    public $cli;
    public $files;

    /**
     * Create a new Laraserve instance.
     *
     * @param CommandLine $cli
     * @param Filesystem  $files
     */
    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->files = $files;
    }

    /**
     * Get the paths to all of the Laraserve extensions.
     *
     * @return array
     */
    public function extensions()
    {
        if (! $this->files->isDir(LARASERVE_HOME_PATH.'/Extensions')) {
            return [];
        }

        return collect($this->files->scandir(LARASERVE_HOME_PATH.'/Extensions'))
                    ->reject(function ($file) {
                        return is_dir($file);
                    })
                    ->map(function ($file) {
                        return LARASERVE_HOME_PATH.'/Extensions/'.$file;
                    })
                    ->values()->all();
    }

    /**
     * Determine if this is the latest version of Laraserve.
     *
     * @param string $currentVersion
     *
     * @return bool
     */
    public function onLatestVersion($currentVersion)
    {
        $response = Request::get('https://api.github.com/repos/laracodedeveloper/laraserve-windows/releases/latest')->send();

        return version_compare($currentVersion, trim($response->body->tag_name, 'v'), '>=');
    }
}
