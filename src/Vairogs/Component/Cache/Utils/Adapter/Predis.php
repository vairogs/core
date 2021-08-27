<?php declare(strict_types = 1);

namespace Vairogs\Component\Cache\Utils\Adapter;

use Predis\ClientInterface;
use Psr\Cache\CacheItemPoolInterface;
use Snc\RedisBundle\SncRedisBundle;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Vairogs\Component\Utils\Vairogs;
use function class_exists;
use function interface_exists;
use function sprintf;

class Predis implements Cache
{
    public function __construct(private ClientInterface $client, private string $namespace = Vairogs::VAIROGS)
    {
        if (!class_exists(SncRedisBundle::class) || !interface_exists(ClientInterface::class)) {
            throw new InvalidConfigurationException(sprintf('Packages %s and %s must be installed in order to use %s', 'snc/redis-bundle', 'predis/predis', self::class));
        }
    }

    public function getAdapter(): CacheItemPoolInterface
    {
        return new RedisAdapter($this->client, $this->namespace, 0);
    }
}
