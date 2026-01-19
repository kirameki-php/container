<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Override;

/**
 * @template T of object = object
 * @extends Entry<T>
 */
class FixedEntry extends Entry
{
    /**
     * @param class-string<T> $id
     * @param T $instance
     * @param list<Closure(T, Container): T> $extenders
     */
    public function __construct(
        string $id,
        public object $instance,
        array $extenders = [],
    ) {
        parent::__construct($id, Lifetime::Singleton, $extenders);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getInstance(Container $container): object
    {
        return $this->instance;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function isResolved(): bool
    {
        return true;
    }
}
