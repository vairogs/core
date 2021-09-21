<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenIDConnect\Configuration;

use Vairogs\Component\Auth\OpenIDConnect\OpenIDConnectProvider;

class DefaultProvider extends OpenIDConnectProvider
{
    protected string $validateTokenUrl = '';
    protected string $refreshTokenUrl = '';
    protected string $revokeTokenUrl = '';

    public function getRefreshTokenUrl(): string
    {
        return $this->refreshTokenUrl;
    }

    public function getRevokeTokenUrl(): string
    {
        return $this->revokeTokenUrl;
    }

    public function getValidateTokenUrl(): string
    {
        return $this->validateTokenUrl;
    }
}
