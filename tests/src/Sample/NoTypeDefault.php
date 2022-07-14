<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class NoTypeDefault
{
    /**
     * @param int $a
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct(
        public $a = 1,
    )
    {
    }
}
