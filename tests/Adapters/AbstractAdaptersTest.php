<?php

namespace React\Tests\Filesystem\Adapters;

use React\EventLoop;
use React\Filesystem\ChildProcess;
use React\Filesystem\Eio;
use React\Filesystem\Filesystem;
use React\Filesystem\Pthreads;
use React\Tests\Filesystem\TestCase;

abstract class AbstractAdaptersTest extends TestCase
{
    /**
     * @var EventLoop\LoopInterface
     */
    protected $loop;

    public function adapterProvider()
    {
        $adapters = [];

        /*if (function_exists('event_base_new'))
        {
            $this->adapterFactory($adapters, 'libevent', function () {
                return new EventLoop\LibEventLoop();
            });
        }

        if (class_exists('libev\EventLoop', false))
        {
            $this->adapterFactory($adapters, 'libev', function () {
                return new EventLoop\LibEvLoop;
            });
        }

        if (class_exists('EventBase', false))
        {
            $this->adapterFactory($adapters, 'extevent', function () {
                return new EventLoop\ExtEventLoop;
            });
        }*/

        $this->adapterFactory($adapters, 'streamselect', function () {
            return new EventLoop\StreamSelectLoop();
        });

        /*$this->adapterFactory($adapters, 'factory', function () {
            return EventLoop\Factory::create();
        });*/

        return $adapters;
    }

    protected function adapterFactory(&$adapters, $loopSlug, callable $loopFactory)
    {

        $adapters[$loopSlug . '-child-process'] = $this->getChildProcessProvider($loopFactory);

        if (extension_loaded('eio')) {
            $adapters[$loopSlug . '-eio'] = $this->getEioProvider($loopFactory);
        }

        if (extension_loaded('pthreads')) {
            $adapters[$loopSlug . '-pthreads'] = $this->getPthreadsProvider($loopFactory);
        }
    }

    protected function getChildProcessProvider(callable $loopFactory)
    {
        $loop = $loopFactory();
        return [
            $loop,
            new ChildProcess\Adapter($loop),
        ];
    }

    protected function getEioProvider(callable $loopFactory)
    {
        $loop = $loopFactory();
        return [
            $loop,
            new Eio\Adapter($loop),
        ];
    }

    protected function getPthreadsProvider(callable $loopFactory)
    {
        $loop = $loopFactory();
        return [
            $loop,
            new Pthreads\Adapter($loop),
        ];
    }

    public function filesystemProvider()
    {
        $filesystems = [];

        foreach ($this->adapterProvider() as $name => $adapter) {
            $filesystems[$name] = [
                $adapter[0],
                Filesystem::createFromAdapter($adapter[1]),
            ];
        }

        return $filesystems;
    }
}