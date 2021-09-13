<?php declare(strict_types = 1);

namespace Vairogs\Extra\Specification;

interface ExtendedSpecificationInterface extends SpecificationInterface
{
    public function getName(): string;

    public function getMessage(): string;
}
