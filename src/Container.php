<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Injecting;
use Kirameki\Container\Events\Resolved;
use Kirameki\Container\Events\Resolving;
use Kirameki\Event\EventHandler;

class Container
{
    /**
     * @var EventHandler<Resolving>|null
     */
    protected ?EventHandler $onResolvingHandler = null;

    /**
     * @var EventHandler<Resolved>|null
     */
    protected ?EventHandler $onResolvedHandler = null;

    /**
     * @var EventHandler<Injecting>|null
     */
    protected ?EventHandler $onInjectingHandler = null;

    /**
     * @var EventHandler<Injected>|null
     */
    protected ?EventHandler $onInjectedHandler = null;

    /**
     * @var EventHandler<Resolving>
     */
    public EventHandler $onResolving {
        get => $this->onResolvingHandler ??= new EventHandler(Resolving::class);
    }

    /**
     * @var EventHandler<Resolved>
     */
    public EventHandler $onResolved {
        get => $this->onResolvedHandler ??= new EventHandler(Resolved::class);
    }

    /**
     * @var EventHandler<Injecting>
     */
    public EventHandler $onInjecting {
        get => $this->onInjectingHandler ??= new EventHandler(Injecting::class);
    }

    /**
     * @var EventHandler<Injected>
     */
    public EventHandler $onInjected {
        get => $this->onInjectedHandler ??= new EventHandler(Injected::class);
    }

    /**
     * @param Injector $injector
     * @param EntryCollection $entries
     */
    public function __construct(
        protected readonly Injector $injector,
        protected EntryCollection $entries,
    ) {
        // Register itself.
        $this->entries->set(new Entry(self::class, Lifetime::Singleton, null, $this));
    }

    /**
     * Get a given class from the container.
     *
     * Resolving and Resolved callbacks are invoked on resolution.
     * For singleton entries, callbacks are only invoked once.
     *
     * Returns the resolved instance.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): mixed
    {
        $entry = $this->entries->get($id);

        if ($entry->isCached()) {
            return $entry->getInstance($this);
        }

        $this->onResolvingHandler?->emit(new Resolving($id, $entry->lifetime));

        $instance = $entry->getInstance($this);

        $this->onResolvedHandler?->emit(new Resolved($id, $entry->lifetime, $instance, $entry->isCached()));

        return $instance;
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param array<array-key, mixed> $args
     * @return T
     */
    public function make(string $id, array $args = []): object
    {
        return $this->has($id) && $args === []
            ? $this->get($id)
            : $this->inject($id, $args);
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<array-key, mixed> $args
     * @return T
     */
    public function inject(string $class, array $args = []): object
    {
        $this->onInjectingHandler?->emit(new Injecting($class));

        $instance = $this->injector->create($this, $class, $args);

        $this->onInjectedHandler?->emit(new Injected($class, $instance));

        return $instance;
    }

    /**
     * @param Closure $closure
     * @param array<array-key, mixed> $args
     * @return mixed
     */
    public function call(Closure $closure, mixed $args = []): mixed
    {
        return $this->injector->invoke($this, $closure, $args);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param class-string $id
     * @return bool
     */
    public function unset(string $id): bool
    {
        return $this->entries->unset($id);
    }

    /**
     * Delete the given entry and return the instance of the entry.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function pull(string $id): object
    {
        $instance = $this->get($id);
        $this->unset($id);
        return $instance;
    }

    /**
     * Check to see if a given class is bound to the container.
     *
     * Returns **true** if bound, **false** otherwise.
     *
     * @param class-string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return $this->entries->has($id);
    }

    /**
     * clear all scoped entries.
     *
     * @return int
     */
    public function clearScoped(): int
    {
        return $this->entries->clearScoped();
    }
}
