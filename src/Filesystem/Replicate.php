<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Filesystem;

use Gaufrette\Adapter as AdapterInterface;
use Gaufrette\Adapter\MetadataSupporter;
use Gaufrette\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * @final since sonata-project/media-bundle 3.21.0
 */
class Replicate implements AdapterInterface, MetadataSupporter
{
    /**
     * @var AdapterInterface
     */
    protected $master;

    /**
     * @var AdapterInterface
     */
    protected $slave;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(AdapterInterface $master, AdapterInterface $slave, ?LoggerInterface $logger = null)
    {
        $this->master = $master;
        $this->slave = $slave;
        $this->logger = $logger;
    }

    public function delete($key)
    {
        $ok = true;

        try {
            $this->slave->delete($key);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to delete %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        try {
            $this->master->delete($key);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to delete %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        return $ok;
    }

    public function mtime($key)
    {
        return $this->master->mtime($key);
    }

    public function keys()
    {
        return $this->master->keys();
    }

    public function exists($key)
    {
        return $this->master->exists($key);
    }

    public function write($key, $content, ?array $metadata = null)
    {
        $ok = true;
        $return = false;

        try {
            $return = $this->master->write($key, $content, $metadata);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to write %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        try {
            $return = $this->slave->write($key, $content, $metadata);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to write %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        return $ok && $return;
    }

    public function read($key)
    {
        return $this->master->read($key);
    }

    public function rename($key, $new)
    {
        $ok = true;

        try {
            $this->master->rename($key, $new);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to rename %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        try {
            $this->slave->rename($key, $new);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->critical(sprintf('Unable to rename %s, error: %s', $key, $e->getMessage()));
            }

            $ok = false;
        }

        return $ok;
    }

    /**
     * If one of the adapters can allow inserting metadata.
     *
     * @return bool true if supports metadata, false if not
     */
    public function supportsMetadata()
    {
        return $this->master instanceof MetadataSupporter || $this->slave instanceof MetadataSupporter;
    }

    public function setMetadata($key, $metadata)
    {
        if ($this->master instanceof MetadataSupporter) {
            $this->master->setMetadata($key, $metadata);
        }
        if ($this->slave instanceof MetadataSupporter) {
            $this->slave->setMetadata($key, $metadata);
        }
    }

    public function getMetadata($key)
    {
        if ($this->master instanceof MetadataSupporter) {
            return $this->master->getMetadata($key);
        } elseif ($this->slave instanceof MetadataSupporter) {
            return $this->slave->getMetadata($key);
        }

        return [];
    }

    /**
     * Gets the class names as an array for both adapters.
     *
     * @return string[]
     */
    public function getAdapterClassNames()
    {
        return [
            \get_class($this->master),
            \get_class($this->slave),
        ];
    }

    public function createFile($key, Filesystem $filesystem)
    {
        return $this->master->createFile($key, $filesystem);
    }

    public function createFileStream($key, Filesystem $filesystem)
    {
        return $this->master->createFileStream($key, $filesystem);
    }

    public function listDirectory($directory = '')
    {
        return $this->master->listDirectory($directory);
    }

    public function isDirectory($key)
    {
        return $this->master->isDirectory($key);
    }
}
