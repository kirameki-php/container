<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Container\Entry;
use Kirameki\Event\Event;

class Resolved extends Event
{
    /**
     * @param string $id
     * @param Entry $entry
     * @param object $instance
     */
    public function __construct(
        public readonly string $id,
        public readonly Entry $entry,
        public readonly object $instance,
    ) {
    }
}
