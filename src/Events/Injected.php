<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Event\Event;

class
Injected extends Event
{
    /**
     * @param string $class
     * @param mixed $instance
     */
    public function __construct(
        public readonly string $class,
        public readonly mixed $instance,
    )
    {
    }
}
