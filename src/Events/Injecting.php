<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Event\Event;

class Injecting extends Event
{
    /**
     * @param string $class
     */
    public function __construct(
        public readonly string $class,
    )
    {
    }
}
