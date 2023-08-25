<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Core\Event;

class
Resolved extends Event
{
    /**
     * @param string $id
     * @param mixed $instance
     */
    public function __construct(
        public readonly string $id,
        public readonly mixed $instance,
    )
    {
    }
}
