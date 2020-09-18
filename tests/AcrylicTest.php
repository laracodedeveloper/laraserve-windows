<?php

use Illuminate\Container\Container;
use Laraserve\Acrylic;
use Laraserve\CommandLine;
use Laraserve\Filesystem;
use function Laraserve\resolve;
use function Laraserve\swap;
use function Laraserve\user;

class AcrylicTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['SUDO_USER'] = user();

        Container::setInstance(new Container());
    }

    public function tearDown()
    {
        Mockery::close();
    }

    public function test_install_service()
    {
        $files = Mockery::mock(Filesystem::class.'[put,exists,putAsUser]');

        $files->shouldReceive('put')->andReturnUsing(function ($path, $contents) {
            $this->assertSame($this->path().'/AcrylicHosts.txt', $path);
            $this->assertTrue(strpos($contents, 'test') !== false);
            $this->assertTrue(strpos($contents, LARASERVE_HOME_PATH) !== false);
        })->once();

        $files->shouldReceive('exists')->with(LARASERVE_HOME_PATH.'/AcrylicHosts.txt')->andReturn(false)->once();

        $files->shouldReceive('putAsUser')->andReturnUsing(function ($path, $contents) {
            $this->assertSame(LARASERVE_HOME_PATH.'/AcrylicHosts.txt', $path);
            $this->assertSame(PHP_EOL, $contents);
        });

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runOrDie')->andReturnUsing(function ($command) {
            $this->assertSame('cmd /C "'.$this->path().'/AcrylicUI.exe" InstallAcrylicService', $command);
        })->once();

        $acrylic = Mockery::mock(Acrylic::class.'[restart,configureNetworkDNS]', [$cli, $files]);
        $acrylic->shouldReceive('restart')->once();
        $acrylic->shouldReceive('configureNetworkDNS')->once();

        swap(Acrylic::class, $acrylic);
        swap(CommandLine::class, $cli);

        resolve(Acrylic::class)->install();
    }

    public function test_update_tld()
    {
        $acrylic = Mockery::mock(Acrylic::class.'[stop,createHostsFile,restart]', [resolve(CommandLine::class), resolve(Filesystem::class)]);
        $acrylic->shouldReceive('stop')->once();
        $acrylic->shouldReceive('createHostsFile')->with('app')->once();
        $acrylic->shouldReceive('restart')->once();

        swap(Acrylic::class, $acrylic);

        resolve(Acrylic::class)->updateTld('app');
    }

    public function test_uninstall_service()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('run')->andReturnUsing(function ($command) {
            $this->assertSame('cmd /C "'.$this->path().'/AcrylicUI.exe" UninstallAcrylicService', $command);
        })->once();

        $acrylic = Mockery::mock(Acrylic::class.'[stop]', [$cli, resolve(Filesystem::class)]);
        $acrylic->shouldReceive('stop')->once();

        swap(Acrylic::class, $acrylic);

        resolve(Acrylic::class)->uninstall();
    }

    protected function path()
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', realpath(__DIR__.'/../bin/Acrylic/'));
    }
}
