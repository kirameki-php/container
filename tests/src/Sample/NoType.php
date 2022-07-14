<?php declare(strict_types=1);

namespace Tests\Kirameki\Container\Sample;

class NoType
{
    /**
     * @param int $a
     * @param int $b
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct(
        public $a,
        public $b = 1,
    )
    {
    }
}
