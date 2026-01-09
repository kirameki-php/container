<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Container\Lifetime;
use Kirameki\Event\Event;

class Resolved extends Event
{
    /**
     * @param string $id
     * @param Lifetime $lifetime
     * @param mixed $instance
     * @param bool $cached
     */
    public function __construct(
        public readonly string $id,
        public readonly Lifetime $lifetime,
        public readonly mixed $instance,
        public readonly bool $cached,
    ) {
    }
}
