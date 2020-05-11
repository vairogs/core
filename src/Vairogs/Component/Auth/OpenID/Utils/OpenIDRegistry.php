<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenID\Utils;

use InvalidArgumentException;
use Traversable;
use Vairogs\Component\Auth\OpenID\OpenIDProvider;
use function iterator_to_array;
use function sprintf;

class OpenIDRegistry
{
    private array $clients;

    public function __construct(Traversable $clients)
    {
        $this->clients = iterator_to_array($clients, true);
    }

    public function getClient(string $name): OpenIDProvider
    {
        foreach ($this->getClients() as $client) {
            if ($name === $client->getName()) {
                return $client;
            }
        }

        throw new InvalidArgumentException(sprintf('Client "%s" does not exist', $name));
    }

    /**
     * @return iterable
     */
    public function getClients(): iterable
    {
        return $this->clients;
    }
}
