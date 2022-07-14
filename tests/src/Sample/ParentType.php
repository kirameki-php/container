<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class ParentType extends NoTypeDefault
{
    public function __construct(
        parent $self,
    )
    {
        parent::__construct($self->a);
    }
}
