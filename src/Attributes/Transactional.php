<?php

namespace Pijler\Actions\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Transactional
{
    /**
     * Create a new attribute instance.
     */
    public function __construct(
        public ?string $connection = null,
    ) {}
}
