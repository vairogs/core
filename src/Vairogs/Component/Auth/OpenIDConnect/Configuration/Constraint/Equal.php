<?php declare(strict_types = 1);

namespace Vairogs\Component\Auth\OpenIDConnect\Configuration\Constraint;

use DateTimeInterface;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\ConstraintViolation;
use Vairogs\Component\Auth\OpenIDConnect\Exception\InvalidConstraintException;
use function sprintf;

final class Equal extends AbstractConstraint
{
    public function __construct(private mixed $expected)
    {
    }

    public function assert(Token $token): void
    {
        parent::assert(token: $token);
        $this->assertClaimSet();

        $value = $token->claims()->get($this->claim);
        if ($value instanceof DateTimeInterface) {
            $value = $value->getTimestamp();
        }
        if ($this->expected !== $value) {
            $message = sprintf('%s expected, got %s', $this->expected, $value);
            if ($this->required) {
                throw new ConstraintViolation(message: $message);
            }

            throw new InvalidConstraintException(message: $message);
        }
    }
}
