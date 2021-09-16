<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenIDConnect\Utils\Traits;

use Lcobucci\JWT\Signer;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\Uri;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\ValidatorChain;

trait OpenIDConnectProviderVariables
{
    /**
     * @var Uri[]
     */
    protected array $uris = [];
    protected string $publicKey;
    protected Signer $signer;
    protected ValidatorChain $validatorChain;
    protected string $idTokenIssuer;
    protected bool $useSession = false;
    protected SessionInterface $session;
    protected int $statusCode;
    protected string $baseUri;
    protected ?string $baseUriPost;
    protected bool $verify = true;

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getUris(): array
    {
        return $this->uris;
    }

    public function setUris(array $uris): static
    {
        $this->uris = $uris;

        return $this;
    }

    public function getSigner(): Signer\Rsa\Sha256|Signer
    {
        return $this->signer;
    }

    public function setSigner(Signer\Rsa\Sha256|Signer $signer): static
    {
        $this->signer = $signer;

        return $this;
    }

    public function getUseSession(): bool
    {
        return $this->useSession;
    }

    public function setUseSession(bool $useSession): static
    {
        $this->useSession = $useSession;

        return $this;
    }

    public function getSession(): callable|SessionInterface
    {
        return $this->session;
    }

    public function setSession(callable|SessionInterface $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function setBaseUri(string $baseUri): static
    {
        $this->baseUri = $baseUri;

        return $this;
    }

    public function getBaseUriPost(): ?string
    {
        return $this->baseUriPost;
    }

    public function setBaseUriPost(?string $baseUriPost): static
    {
        $this->baseUriPost = $baseUriPost;

        return $this;
    }

    public function getVerify(): bool
    {
        return $this->verify;
    }

    public function setVerify(bool $verify): static
    {
        $this->verify = $verify;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValidatorChain(): ValidatorChain
    {
        return $this->validatorChain;
    }

    public function setValidatorChain(ValidatorChain $validatorChain): static
    {
        $this->validatorChain = $validatorChain;

        return $this;
    }

    public function getUri($name): ?Uri
    {
        return $this->uris[$name] ?? null;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function setIdTokenIssuer(string $idTokenIssuer): static
    {
        $this->idTokenIssuer = $idTokenIssuer;

        return $this;
    }

    protected function getIdTokenIssuer(): string
    {
        return $this->idTokenIssuer;
    }
}
