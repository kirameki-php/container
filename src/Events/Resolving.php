<?php declare(strict_types=1);

namespace Kirameki\Container\Events;

use Kirameki\Container\Lifetime;
use Kirameki\Core\Event;

class Resolving extends Event
{
    public function __construct(
        public readonly string $id,
        public readonly Lifetime $lifetime,
    )
    {
    }
}
