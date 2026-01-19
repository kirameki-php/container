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
     * @var Closure(Container): T
     */
    protected readonly Closure $resolver;

    /**
     * @var T|null
     */
    protected ?object $instance = null;

    /**
     * @param class-string<T> $id
     * @param Lifetime $lifetime
     * @param Closure(Container): T|null $resolver
     * @param list<Closure(T, Container): T> $extenders
     */
    public function __construct(
        string $id,
        Lifetime $lifetime,
        ?Closure $resolver = null,
        array $extenders = [],
    ) {
        parent::__construct($id, $lifetime, $extenders);
        $this->resolver = $resolver ?? static fn (Container $c) => $c->inject($id);
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
        $instance = $this->applyExtenders($instance, $container);

        if (!is_a($instance, $this->id)) {
            throw new InvalidInstanceException("Expected: instance of {$this->id}. Got: " . $instance::class . '.', [
                'this' => $this,
                'instance' => $instance,
            ]);
        }

        return $instance;
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
