<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;

/**
 * @template T of object = object
 */
abstract class Entry
{
    /**
     * @param class-string<T> $id
     * @param Lifetime $lifetime
     * @param list<Closure(T, Container): T> $extenders
     */
    public function __construct(
        public readonly string $id,
        public readonly Lifetime $lifetime,
        protected array $extenders = [],
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

    /**
     * Extender will be executed immediately if the instance already exists.
     *
     * @param Closure(T, Container): T $extender
     * @return void
     */
    public function extend(Closure $extender): void
    {
        $this->extenders[] = $extender;
    }

    /**
     * @return bool
     */
    public function isExtended(): bool
    {
        return $this->extenders !== [];
    }
}
