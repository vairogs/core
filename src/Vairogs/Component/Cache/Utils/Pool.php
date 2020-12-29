<?php declare(strict_types = 1);

namespace Vairogs\Component\Cache\Utils;

use InvalidArgumentException;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Vairogs\Component\Cache\Utils\Adapter\Cache;
use function sprintf;

class Pool
{
    /**
     * @param string $class
     * @param array $adapters
     * @return array
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public static function createPoolFor(string $class, array $adapters = []): array
    {
        $pool = [];

        foreach ($adapters as $adapter) {
            if (null === $adapter) {
                continue;
            }

            if (!$adapter instanceof Cache && !$adapter instanceof CacheItemPoolInterface) {
                throw new InvalidArgumentException(sprintf('Adapter %s must implement %s or %s', $adapter::class, Cache::class, CacheItemPoolInterface::class));
            }

            if ($adapter instanceof Cache) {
                /** @var Cache $provider */
                $pool[] = $adapter->getAdapter();
            } else {
                $pool = $adapter;
            }
        }

        if ([] === $pool) {
            throw new RuntimeException(sprintf('At least one provider must be provided in order to use %s', $class));
        }

        return $pool;
    }
}
