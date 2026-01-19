<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Exceptions\InvalidInstanceException;
use Override;

/**
 * @template T of object = object
 * @extends Entry<T>
 */
class EntryLazy extends Entry
{
    /**
     * @var T|null
     */
    protected ?object $instance = null;

    /**
     * @param Lifetime $lifetime
     * @param Closure(Container): T $resolver
     * @param list<Closure(T, Container): T> $extenders
     */
    public function __construct(
        Lifetime $lifetime,
        protected Closure $resolver,
        array $extenders = [],
    ) {
        parent::__construct($lifetime, $extenders);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getInstance(Container $container): object
    {
        $instance = $this->instance ?? $this->resolve($container);

        if ($this->lifetime !== Lifetime::Transient) {
            $this->instance = $instance;
        }

        return $instance;
    }

    /**
     * Unset the instance if it exists.
     * Returns **true** if the instance existed and was unset, **false** otherwise.
     *
     * @return bool
     */
    public function unsetInstance(): bool
    {
        if ($this->instance !== null) {
            $this->instance = null;
            return true;
        }
        return false;
    }

    /**
     * @param Container $container
     * @return T
     */
    protected function resolve(Container $container): object
    {
        $instance = ($this->resolver)($container);
        return $this->applyExtenders($instance, $container);
    }

    /**
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->instance !== null;
    }

    /**
     * @param T $instance
     * @param Container $container
     * @return T
     */
    protected function applyExtenders(object $instance, Container $container): object
    {
        foreach ($this->extenders as $extender) {
            $instance = $extender($instance, $container);
        }
        return $instance;
    }
}
