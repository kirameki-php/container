<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Override;

/**
 * @template T of object = object
 * @extends Entry<T>
 */
class LazyEntry extends Entry
{
    /**
     * @var T|null
     */
    protected ?object $instance = null;

    /**
     * @param Lifetime $lifetime
     * @param Closure(Container): T $resolver
     * @param list<Closure(T): mixed> $configurators
     */
    public function __construct(
        Lifetime $lifetime,
        protected readonly Closure $resolver,
        protected ?array $configurators = null,
    ) {
        parent::__construct($lifetime);
    }   

    /**
     * @inheritDoc
     */
    #[Override]
    public function getInstance(Container $container): object
    {
        $instance = $this->instance;

        if ($instance === null) {
            $instance = ($this->resolver)($container);

            if ($this->configurators !== null) {
                foreach ($this->configurators as $configurator) {
                    $configurator($instance);
                }
            }
        }

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
     * @param list<Closure(T): mixed> $configurators
     * @return void
     */
    public function setConfigurators(array $configurators): void
    {
        $this->configurators = $configurators;
    }

    /**
     * @return bool
     */
    public function isResolved(): bool
    {
        return $this->instance !== null;
    }
}
