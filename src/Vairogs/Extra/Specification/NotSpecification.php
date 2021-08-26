<?php declare(strict_types = 1);

namespace Vairogs\Extra\Specification;

class NotSpecification extends CompositeSpecification
{
    public function __construct(private SpecificationInterface $specification)
    {
    }

    public function isSatisfiedBy($expectedValue, $actualValue = null): bool
    {
        return !$this->specification->isSatisfiedBy($expectedValue);
    }
}
