<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

use DateTime;

class Variadic
{
    /**
     * @var array<DateTime>
     */
    public array $list;

    public function __construct(DateTime ...$a)
    {
        $this->list = $a;
    }
}
