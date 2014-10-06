<?php

namespace React\Filesystem\Node;

use React\Filesystem\FilesystemInterface;
use React\Promise\Deferred;
use React\Promise\FulfilledPromise;

class Directory implements DirectoryInterface, GenericOperationInterface
{

    use GenericOperationTrait;

    protected $typeClassMapping = [
        EIO_DT_DIR => '\React\Filesystem\Node\Directory',
        EIO_DT_REG => '\React\Filesystem\Node\File',
    ];

    protected $recursiveInvoker;

    protected function getRecursiveInvoker()
    {
        if ($this->recursiveInvoker instanceof RecursiveInvoker) {
            return $this->recursiveInvoker;
        }

        $this->recursiveInvoker = new RecursiveInvoker($this);
        return $this->recursiveInvoker;
    }

    public function __construct($path, FilesystemInterface $filesystem)
    {
        $this->path = $path;
        $this->filesystem = $filesystem;
    }

    protected function getPath()
    {
        return $this->path;
    }

    public function ls()
    {
        $deferred = new Deferred();

        $this->filesystem->ls($this->path)->then(function ($result) use ($deferred) {
            $this->filesystem->getLoop()->futureTick(function () use ($result, $deferred) {
                $deferred->resolve($this->processLsContents($result));
            });
        }, function ($error) use ($deferred) {
            $deferred->reject($error);
        });

        return $deferred->promise();
    }

    protected function processLsContents($result)
    {
        $list = [];
        foreach ($result['dents'] as $entry) {
            if (isset($this->typeClassMapping[$entry['type']])) {
                $path = $this->path . DIRECTORY_SEPARATOR . $entry['name'];
                $list[$entry['name']] = new $this->typeClassMapping[$entry['type']]($path, $this->filesystem);
            }
        }
        return $list;
    }

    public function size()
    {
        $deferred = new Deferred();

        $this->ls()->then(function($result) use ($deferred) {
            $this->filesystem->getLoop()->futureTick(function () use ($result, $deferred) {
                $this->processSizeContents($result)->then(function($numbers) use ($deferred) {
                    $deferred->resolve($numbers);
                });
            });
        }, function ($error) use ($deferred) {
            $deferred->reject($error);
        });

        return $deferred->promise();
    }

    protected function processSizeContents($nodes)
    {
        $deferred = new Deferred();
        $numbers = [
            'directories' => 0,
            'files' => 0,
            'size' => 0,
        ];

        $promises = [];
        foreach ($nodes as $node) {
            switch (true) {
                case $node instanceof Directory:
                    $numbers['directories']++;
                    break;
                case $node instanceof File:
                    $numbers['files']++;
                    $promises[] = $node->size()->then(function($size) use (&$numbers) {
                        $numbers['size'] += $size;
                        return new FulfilledPromise();
                    });
                    break;
            }
        }

        \React\Promise\all($promises)->then(function() use ($deferred, &$numbers) {
            $deferred->resolve($numbers);
        });

        return $deferred->promise();
    }

    public function create()
    {
        return $this->filesystem->mkdir($this->path);
    }

    public function remove()
    {
        return $this->filesystem->rmdir($this->path);
    }

    public function createRecursive()
    {
        $deferred = new Deferred();

        $parentPath = explode(DIRECTORY_SEPARATOR, $this->path);
        array_pop($parentPath);
        $parentPath = implode(DIRECTORY_SEPARATOR, $parentPath);

        $parentDirectory = new Directory($parentPath, $this->filesystem);
        $parentDirectory->stat()->then(null, function () use ($parentDirectory, $deferred) {
            return $parentDirectory->createRecursive();
        })->then(function () use ($deferred) {
            return $this->create();
        })->then(function () use ($deferred) {
            $deferred->resolve();
        });

        return $deferred->promise();
    }

    public function chmodRecursive($mode)
    {
        return $this->getRecursiveInvoker()->execute('chmod', [$mode]);
    }

    public function chownRecursive($uid = -1, $gid = -1)
    {
        return $this->getRecursiveInvoker()->execute('chown', [$uid, $gid]);
    }

    public function removeRecursive()
    {
        return $this->getRecursiveInvoker()->execute('remove', []);
    }
}
