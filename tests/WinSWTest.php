<?php

use Illuminate\Container\Container;
use Laraserve\CommandLine;
use Laraserve\Filesystem;
use function Laraserve\resolve;
use function Laraserve\swap;
use function Laraserve\user;
use Laraserve\WinSW;

class WinSWTest extends PHPUnit_Framework_TestCase
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
        $files = Mockery::mock(Filesystem::class);

        $files->shouldReceive('get')->andReturnUsing(function ($path) {
            $this->assertSame(realpath(__DIR__.'/../cli/Laraserve').'/../stubs/testservice.xml', $path);
        })->once();

        $files->shouldReceive('putAsUser')->andReturnUsing(function ($path) {
            $this->assertSame(LARASERVE_HOME_PATH.'/Services/testservice.xml', $path);
        })->once();

        $files->shouldReceive('copy')->andReturnUsing(function ($from, $to) {
            $this->assertSame(realpath(__DIR__.'/../bin').'/winsw.exe', $from);
            $this->assertSame(LARASERVE_HOME_PATH.'/Services/testservice.exe', $to);
        })->once();

        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('runOrDie')->andReturnUsing(function ($command) {
            $this->assertSame('cmd "/C cd '.LARASERVE_HOME_PATH.'\Services && testservice install"', $command);
        })->once();

        swap(CommandLine::class, $cli);
        swap(Filesystem::class, $files);

        resolve(WinSW::class)->install('testservice');
    }

    public function test_stop_service()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('run')->andReturnUsing(function ($command) {
            $this->assertSame('cmd "/C cd '.LARASERVE_HOME_PATH.'\Services && testservice stop"', $command);
        })->once();

        swap(CommandLine::class, $cli);

        resolve(WinSW::class)->stop('testservice');
    }

    public function test_restart_service()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('run')->andReturnUsing(function ($command) {
            $this->assertSame('cmd "/C cd '.LARASERVE_HOME_PATH.'\Services && testservice start"', $command);
        })->once();

        $winsw = Mockery::mock(WinSW::class.'[stop]', [$cli, resolve(Filesystem::class)]);
        $winsw->shouldReceive('stop')->with('testservice')->once();

        swap(WinSW::class, $winsw);

        resolve(WinSW::class)->restart('testservice');
    }

    public function test_uninstall_service()
    {
        $cli = Mockery::mock(CommandLine::class);

        $cli->shouldReceive('run')->andReturnUsing(function ($command) {
            $this->assertSame('cmd "/C cd '.LARASERVE_HOME_PATH.'\Services && testservice uninstall"', $command);
        })->once();

        $winsw = Mockery::mock(WinSW::class.'[stop]', [$cli, resolve(Filesystem::class)]);
        $winsw->shouldReceive('stop')->with('testservice')->once();

        swap(WinSW::class, $winsw);

        resolve(WinSW::class)->uninstall('testservice');
    }
}
