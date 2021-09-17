<?php declare(strict_types = 1);

namespace Vairogs\Component\Cache\Utils\Adapter;

use Psr\Cache\CacheItemPoolInterface;
use Redis;
use Snc\RedisBundle\SncRedisBundle;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Vairogs\Component\Utils\Helper\Php;
use Vairogs\Component\Utils\Vairogs;
use function sprintf;

class PhpRedis implements Cache
{
    public function __construct(private Redis $client, private string $namespace = Vairogs::VAIROGS)
    {
        if (!Php::exists(class: SncRedisBundle::class) || !Php::exists(class: Redis::class)) {
            throw new InvalidConfigurationException(message: sprintf('Packages %s and %s must be installed in order to use %s', 'snc/redis-bundle', 'ext-redis', self::class));
        }
    }

    public function getAdapter(): CacheItemPoolInterface
    {
        return new RedisAdapter(redis: $this->client, namespace: $this->namespace, defaultLifetime: Cache::DEFAULT_LIFETIME);
    }
}
