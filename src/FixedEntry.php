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
     * @param T $instance
     */
    public function __construct(
        public object $instance,
    ) {
        parent::__construct(Lifetime::Singleton);
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
