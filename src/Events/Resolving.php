<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Container\Entry;
use Kirameki\Event\Event;

class Resolving extends Event
{
    /**
     * @param string $id
     * @param Entry $entry
     */
    public function __construct(
        public readonly string $id,
        public readonly Entry $entry,
    ) {
    }
}
