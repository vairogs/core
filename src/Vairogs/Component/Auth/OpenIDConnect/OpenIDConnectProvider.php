<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenIDConnect;

use DateTime;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonException;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Client\Grant\AbstractGrant;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken as BaseAccessToken;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use UnexpectedValueException;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\ParsedToken;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\Uri;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\ValidatorChain;
use Vairogs\Component\Auth\OpenIDConnect\Exception\OpenIDConnectException;
use Vairogs\Component\Auth\OpenIDConnect\Utils\Traits\OpenIDConnectProviderVariables;
use Vairogs\Component\Utils\Helper\Identification;
use Vairogs\Component\Utils\Helper\Iteration;
use Vairogs\Component\Utils\Helper\Json;
use Vairogs\Component\Utils\Helper\Text;
use Vairogs\Extra\Constants\ContentType;
use Vairogs\Extra\Specification;
use function array_merge;
use function base64_encode;
use function hash;
use function json_last_error;
use function property_exists;
use function round;
use function rtrim;
use function sprintf;
use function substr;

abstract class OpenIDConnectProvider extends AbstractProvider
{
    use OpenIDConnectProviderVariables;

    public function __construct(protected string $name, protected Router $router, RequestStack $requestStack, array $options = [], array $collaborators = [])
    {
        $this->signer = new Signer\Rsa\Sha256();
        $this->validatorChain = new ValidatorChain();
        $this->setValidators();
        $this->session = $requestStack->getCurrentRequest()
            ->getSession();
        parent::__construct(options: $options, collaborators: $collaborators);
        $this->buildParams(options: $options);
    }

    protected function setValidators(): void
    {
        $this->validatorChain->setValidators(validators: [
            new Specification\EqualsTo(name: 'at_hash', required: true),
            new Specification\EqualsTo(name: 'aud', required: true),
            new Specification\EqualsTo(name: 'azp'),
            new Specification\EqualsTo(name: 'iss', required: true),
            new Specification\EqualsTo(name: 'jti'),
            new Specification\EqualsTo(name: 'nonce'),
            new Specification\GreaterOrEqualsTo(name: 'exp', required: true),
            new Specification\LesserOrEqualsTo(name: 'nbf'),
            new Specification\NotEmpty(name: 'iat', required: true),
            new Specification\NotEmpty(name: 'sub', required: true),
        ]);
    }

    protected function buildParams(array $options = []): void
    {
        if ([] !== $options) {
            $this->state = $this->getRandomState();

            $url = match ($options['redirect']['type']) {
                'uri' => $options['redirect']['uri'],
                'route' => $this->router->generate(name: $options['redirect']['route'], parameters: $options['redirect']['params'] ?? [], referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
                default => null,
            };

            $this->redirectUri = $url;
            $uris = $options['uris'] ?? [];
            unset($options['redirect'], $options['uris']);

            foreach (Iteration::makeOneDimension(array: $options, onlyLast: false, maxDepth: 0) as $key => $value) {
                if (property_exists(object_or_class: $this, property: $var = Text::toCamelCase(string: $key))) {
                    $this->{$var} = $value;
                }
            }

            $this->publicKey = 'file://' . $this->publicKey;

            $this->buildUris(options: $uris);
        }
    }

    protected function getRandomState($length = 32): string
    {
        return Identification::getUniqueId(length: $length);
    }

    protected function createResourceOwner(array $response, BaseAccessToken $token): array
    {
        return [];
    }

    protected function buildUris(array $options = []): void
    {
        foreach ($options as $name => $uri) {
            $params = [
                'client_id' => $this->clientId,
                'redirect_uri' => $this->redirectUri,
                'state' => $this->state,
                'base_uri' => $this->baseUri,
                'base_uri_post' => $this->baseUriPost ?? $this->baseUri,
            ];
            $this->uris[$name] = new Uri(options: $uri, extra: $params, useSession: $this->useSession, method: $uri['method'] ?? Request::METHOD_POST, session: $this->session);
        }
    }

    public function getBaseAccessTokenUrl(array $params): string
    {
        return '';
    }

    public function getResourceOwnerDetailsUrl(BaseAccessToken $token): void
    {
    }

    public function getBaseAuthorizationUrl(): string
    {
        return '';
    }

    protected function getScopeSeparator(): string
    {
        return ' ';
    }

    public function getDefaultScopes(): array
    {
        return [];
    }

    /**
     * @throws JsonException
     */
    protected function parseJson($content): array
    {
        if (empty($content)) {
            return [];
        }

        $content = Json::decode(json: $content, flags: 1);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new UnexpectedValueException(message: sprintf('Failed to parse JSON response: %s', json_last_error_msg()));
        }

        return $content;
    }

    /**
     * @throws IdentityProviderException
     */
    public function getRefreshToken($token, array $options = []): array|string|ResponseInterface
    {
        $params = [
            'token' => $token,
            'grant_type' => 'refresh_token',
        ];
        $params = array_merge($params, $options);
        $request = $this->getRefreshTokenRequest(params: $params);

        return $this->getTokenResponse(request: $request);
    }

    protected function getRefreshTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getRefreshTokenUrl();
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    abstract public function getRefreshTokenUrl(): string;

    #[ArrayShape([
        'headers' => 'string[]',
        'body' => 'mixed',
    ])]
    protected function getAccessTokenOptions(array $params): array
    {
        $options = $this->getBaseTokenOptions(params: $params);
        $options['headers']['authorization'] = 'Basic ' . base64_encode(string: $this->clientId . ':' . $this->clientSecret);

        return $options;
    }

    #[ArrayShape([
        'headers' => 'string[]',
        'body' => 'mixed',
    ])]
    protected function getBaseTokenOptions(array $params): array
    {
        $options = [
            'headers' => [
                'content-type' => ContentType::X_WWW_FORM_URLENCODED,
            ],
        ];
        if (self::METHOD_POST === $this->getAccessTokenMethod()) {
            $options['body'] = $this->getAccessTokenBody(params: $params);
        }

        return $options;
    }

    #[Pure]
    protected function getAccessTokenBody(array $params): string
    {
        return $this->buildQueryString(params: $params);
    }

    /**
     * @throws IdentityProviderException
     */
    public function getTokenResponse(RequestInterface $request): array
    {
        $response = $this->getResponse(request: $request);
        $this->statusCode = $response->getStatusCode();
        /** @var array $parsed */
        $parsed = $this->parseResponse(response: $response);
        $this->checkResponse(response: $response, data: $parsed);

        return $parsed;
    }

    protected function getATHash(string $accessToken, int $bit = 256): string
    {
        $hash = substr(string: hash(algo: 'sha' . $bit, data: $accessToken, binary: true), offset: 0, length: (int) round(num: $bit / 16));

        return strtr(string: rtrim(string: base64_encode(string: $hash), characters: '='), from: '+/', to: '-_');
    }

    protected function checkResponse(ResponseInterface $response, $data): void
    {
        // Override parent method
    }

    /**
     * @throws IdentityProviderException
     */
    public function getValidateToken($token, array $options = []): array|string|ResponseInterface
    {
        $params = [
            'token' => $token,
        ];
        $params = array_merge($params, $options);
        $request = $this->getValidateTokenRequest(params: $params);

        return $this->getTokenResponse(request: $request);
    }

    protected function getValidateTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getValidateTokenUrl();
        $options = $this->getBaseTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    /**
     * @throws IdentityProviderException
     * @throws OpenIDConnectException
     */
    public function getAccessToken($grant, array $options = []): AccessTokenInterface|BaseAccessToken
    {
        /** @var ParsedToken $accessToken */
        $accessToken = $this->getAccessTokenFunction(grant: $grant, options: $options);

        if (null === $accessToken) {
            throw new OpenIDConnectException(message: 'Invalid access token.');
        }

        // id_token is empty.
        if (null === $token = $accessToken->getIdToken()) {
            throw new OpenIDConnectException(message: 'Expected an id_token but did not receive one from the authorization server');
        }

        try {
            (new SignedWith(signer: $this->signer, key: $this->getPublicKey()))->assert(token: $token);
        } catch (Exception) {
            throw new OpenIDConnectException(message: 'Received an invalid id_token from authorization server');
        }

        $currentTime = new DateTime(datetime: 'now');
        $data = [
            'iss' => $this->getIdTokenIssuer(),
            'exp' => $currentTime,
            'auth_time' => $currentTime,
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'aud' => [$this->clientId],
            'azp' => $this->clientId,
            'at_hast' => $this->getATHash($accessToken->getToken()),
        ];

        if (false === $this->validatorChain->validate(data: $data, token: $token)) {
            throw new OpenIDConnectException(message: 'The id_token did not pass validation.');
        }

        $this->saveSession(accessToken: $accessToken);

        return $accessToken;
    }

    abstract public function getValidateTokenUrl(): string;

    /**
     * @throws IdentityProviderException
     */
    public function getRevokeToken($token, array $options = []): array|string|ResponseInterface
    {
        $params = [
            'token' => $token,
        ];
        $params = array_merge($params, $options);
        $request = $this->getRevokeTokenRequest(params: $params);

        return $this->getTokenResponse(request: $request);
    }

    protected function getRevokeTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getRevokeTokenUrl();
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    abstract public function getRevokeTokenUrl(): string;

    protected function getRequiredOptions(): array
    {
        return [];
    }

    protected function saveSession($accessToken): void
    {
        if ($this->useSession) {
            $this->session->set(name: 'access_token', value: $accessToken->getToken());
            $this->session->set(name: 'refresh_token', value: $accessToken->getRefreshToken());
            $this->session->set(name: 'id_token', value: $accessToken->getIdTokenHint());
        }
    }

    public function check(mixed $response = null): bool
    {
        return null !== $response;
    }

    protected function getAccessTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getAccessTokenUrl(params: $params);
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    /**
     * @throws IdentityProviderException
     */
    public function getAccessTokenFunction($grant, array $options = []): ?ParsedToken
    {
        $grant = $this->verifyGrant(grant: $grant);

        $params = [
            'redirect_uri' => $this->redirectUri,
        ];

        $params = $grant->prepareRequestParameters(defaults: $params, options: $options);
        $request = $this->getAccessTokenRequest(params: $params);
        $response = $this->getTokenResponse(request: $request);
        $prepared = $this->prepareAccessTokenResponse(result: $response);

        return $this->createAccessToken(response: $prepared, grant: $grant);
    }

    protected function createAccessToken(array $response, ?AbstractGrant $grant = null): ?ParsedToken
    {
        if ($this->check(response: $response)) {
            return new ParsedToken(options: $response);
        }

        return null;
    }

    public function getPublicKey(): Key
    {
        return Key\InMemory::plainText(contents: $this->publicKey);
    }

    protected function getAllowedClientOptions(array $options): array
    {
        return [
            'timeout',
            'proxy',
            'verify',
        ];
    }
}
