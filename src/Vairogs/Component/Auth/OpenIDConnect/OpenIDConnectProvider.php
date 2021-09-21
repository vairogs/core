<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenIDConnect;

use DateTime;
use Exception;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use JsonException;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use League\OAuth2\Client\Grant\AbstractGrant;
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
use Vairogs\Component\Auth\OpenIDConnect\Configuration\AbstractProvider;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\ParsedToken;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\Uri;
use Vairogs\Component\Auth\OpenIDConnect\Configuration\ValidatorChain;
use Vairogs\Component\Auth\OpenIDConnect\Exception\OpenIDConnectException;
use Vairogs\Component\Auth\OpenIDConnect\Utils\Traits\OpenIDConnectProviderVariables;
use Vairogs\Component\Utils\Helper\Iteration;
use Vairogs\Component\Utils\Helper\Json;
use Vairogs\Component\Utils\Helper\Text;
use Vairogs\Extra\Constants\ContentType;
use Vairogs\Extra\Specification;
use function array_merge;
use function base64_encode;
use function json_last_error;
use function property_exists;
use function sprintf;

abstract class OpenIDConnectProvider extends AbstractProvider
{
    use OpenIDConnectProviderVariables;

    public function __construct(protected string $name, protected Router $router, RequestStack $requestStack, array $options = [], array $collaborators = [])
    {
        $this->signer = new Signer\Rsa\Sha256();
        $this->validatorChain = new ValidatorChain();
        try {
            $this->session = $requestStack->getCurrentRequest()?->getSession();
        } catch (Exception) {
            $this->session = null;
        }
        parent::__construct(options: $options, collaborators: $collaborators);
        $this->configure(options: $options);
    }

    /**
     * @throws IdentityProviderException
     */
    public function getAccessToken($grant, array $options = []): AccessTokenInterface|BaseAccessToken
    {
        /** @var ParsedToken $accessToken */
        $accessToken = $this->getAccessTokenFunction(grant: $grant, options: $options);

        if (null === $accessToken) {
            throw new OpenIDConnectException(message: 'Invalid access token');
        }

        // id_token is empty.
        if (null === $token = $accessToken->getIdToken()) {
            throw new OpenIDConnectException(message: 'Expected an id_token but did not receive one from the authorization server');
        }

        $currentTime = new DateTime();
        $data = [
            'token' => $token,
            'iss' => $this->getIdTokenIssuer(),
            'exp' => $currentTime,
            'auth_time' => $currentTime,
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'aud' => [$this->clientId],
            'azp' => $this->clientId,
            'at_hast' => Text::getHash(hashable: $accessToken->getToken()),
        ];
        $this->setValidators();

        if (false === $this->validatorChain->validate(data: $data, object: $token)) {
            throw new OpenIDConnectException(message: 'The id_token did not pass validation');
        }

        return $this->saveSession(accessToken: $accessToken);
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

    public function getPublicKey(): Key
    {
        return Key\InMemory::plainText(contents: $this->publicKey);
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

    protected function configure(array $options = []): void
    {
        if ([] !== $options) {
            $this->state = $this->getRandomState();

            $this->redirectUri = match ($options['redirect']['type']) {
                'uri' => $options['redirect']['uri'],
                'route' => $this->router->generate(name: $options['redirect']['route'], parameters: $options['redirect']['params'] ?? [], referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
                default => null,
            };

            $uris = $options['uris'] ?? [];
            unset($options['redirect'], $options['uris']);

            foreach (Iteration::makeOneDimension(array: $options, onlyLast: false, maxDepth: 0) as $key => $value) {
                if (property_exists(object_or_class: $this, property: $var = Text::toCamelCase(string: $key))) {
                    $this->{$var} = $value;
                }
            }

            $this->publicKey = 'file://' . $this->publicKey;

            foreach ($uris as $name => $uri) {
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
    }

    protected function getRefreshTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getRefreshTokenUrl();
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    protected function getValidateTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getValidateTokenUrl();
        $options = $this->getBaseTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    protected function getRevokeTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getRevokeTokenUrl();
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    protected function setValidators(): void
    {
        // @formatter:off
        $this->validatorChain
            ->setAssertions(assertions: [
                'token' => new SignedWith(signer: $this->signer, key: $this->getPublicKey()),
                'iss' => new IssuedBy($this->getIdTokenIssuer()),
            ])
            ->setValidators(validators: [
                'at_hash' => new Specification\EqualsTo(required: true),
                'aud' => new Specification\EqualsTo(required: true),
                'azp' => new Specification\EqualsTo(),
                'jti' => new Specification\EqualsTo(),
                'nonce' => new Specification\EqualsTo(),
                'exp' => new Specification\GreaterOrEqualsTo(required: true),
                'nbf' => new Specification\LesserOrEqualsTo(),
                'iat' => new Specification\NotEmpty(required: true),
                'sub' => new Specification\NotEmpty(required: true),
            ]);
        // @formatter:on
    }

    /**
     * @throws JsonException
     * @throws UnexpectedValueException
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

    protected function saveSession(ParsedToken $accessToken): ParsedToken
    {
        if ($this->useSession && null !== $this->session) {
            $this->session->set(name: 'access_token', value: $accessToken->getToken());
            $this->session->set(name: 'refresh_token', value: $accessToken->getRefreshToken());
            $this->session->set(name: 'id_token', value: $accessToken->getIdTokenHint());
        }

        return $accessToken;
    }

    protected function getAccessTokenRequest(array $params): RequestInterface
    {
        $method = $this->getAccessTokenMethod();
        $url = $this->getAccessTokenUrl(params: $params);
        $options = $this->getAccessTokenOptions(params: $params);

        return $this->getRequest(method: $method, url: $url, options: $options);
    }

    protected function createAccessToken(array $response, ?AbstractGrant $grant = null): ?ParsedToken
    {
        if ($this->check(response: $response)) {
            return new ParsedToken(options: $response);
        }

        return null;
    }
}
