<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Container\Lifetime;
use Kirameki\Event\Event;

class Resolving extends Event
{
    /**
     * @param string $id
     * @param Lifetime $lifetime
     */
    public function __construct(
        public readonly string $id,
        public readonly Lifetime $lifetime,
    )
    {
    }
}
