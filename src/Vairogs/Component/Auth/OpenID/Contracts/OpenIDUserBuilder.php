<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenID\Contracts;

interface OpenIDUserBuilder
{
    public function getUser(array $response): OpenIDUser;
}
