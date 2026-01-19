<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;

/**
 * @template T of object = object
 */
abstract class Entry
{
    /**
     * @param Lifetime $lifetime
     */
    public function __construct(
        public readonly Lifetime $lifetime,
    ) {
    }

    /**
     * @param Container $container
     * @return T
     */
    abstract public function getInstance(Container $container): object;

    /**
     * @return bool
     */
    abstract public function isResolved(): bool;
}
