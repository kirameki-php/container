<?php declare(strict_types=1);

namespace Kirameki\Container;

interface Builder
{
    /**
     * @return mixed
     */
    public function build(): mixed;
}
