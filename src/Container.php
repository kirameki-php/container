<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Core\Exceptions\LogicException;
use function array_key_exists;

class Container
{
    /** @var Injector */
    protected Injector $injector;

    /** @var array<class-string, Entry<object>> */
    protected array $entries = [];

    /** @var array<class-string, null> */
    protected array $scopedEntries = [];

    /** @var list<Closure(Entry<object>): mixed> */
    protected array $resolvingCallbacks = [];

    /** @var list<Closure(Entry<object>, mixed): mixed> */
    protected array $resolvedCallbacks = [];

    /**
     * @param Injector|null $injector
     */
    public function __construct(?Injector $injector = null)
    {
        $this->injector = $injector ?? new Injector($this);
    }

    /**
     * Get a given class from the container.
     *
     * Resolving and Resolved callbacks are invoked on resolution.
     * For singleton entries, callbacks are only invoked once.
     *
     * Returns the resolved instance.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function get(string $class, array $args = []): mixed
    {
        $entry = $this->getEntry($class);
        $invokeCallbacks = !$entry->isCached();

        if($invokeCallbacks) {
            foreach ($this->resolvingCallbacks as $callback) {
                $callback($entry);
            }
        }

        $instance =  $entry->getInstance($args);

        if ($invokeCallbacks) {
            foreach ($this->resolvedCallbacks as $callback) {
                $callback($entry, $instance);
            }
        }

        return $instance;
    }

    /**
     * Register a given class.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container, array<array-key, mixed>): TEntry $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function set(string $class, Closure $resolver, Lifetime $lifetime = Lifetime::Transient): void
    {
        $this->setEntry($class)->setResolver($resolver, $lifetime);
    }

    /**
     * Register a given class with scoped lifetime.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container, array<array-key, mixed>): TEntry $resolver
     * @return void
     */
    public function scoped(string $class, Closure $resolver): void
    {
        $this->scopedEntries[$class] = null;
        $this->set($class, $resolver, Lifetime::Scoped);
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(Container, array<array-key, mixed>): TEntry $resolver
     * @return void
     */
    public function singleton(string $class, Closure $resolver): void
    {
        $this->set($class, $resolver, Lifetime::Singleton);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param class-string $class
     * @return bool
     */
    public function unset(string $class): bool
    {
        if ($this->has($class)) {
            unset($this->entries[$class], $this->scopedEntries[$class]);
            return true;
        }
        return false;
    }

    /**
     * Check to see if a given class is bound to the container.
     *
     * Returns **true** if bound, **false** otherwise.
     *
     * @param class-string $class
     * @return bool
     */
    public function has(string $class): bool
    {
        return array_key_exists($class, $this->entries) && $this->entries[$class]->isResolvable();
    }

    /**
     * @param class-string $class
     * @return bool
     */
    public function isCached(string $class): bool
    {
        return array_key_exists($class, $this->entries) && $this->entries[$class]->isCached();
    }

    /**
     * Clears all entries.
     *
     * @return $this
     */
    public function clearEntries(): static
    {
        $this->entries = [];
        $this->scopedEntries = [];
        return $this;
    }

    /**
     * Clears all scope for a scoped entry.
     *
     * @return $this
     */
    public function clearScopedEntries(): static
    {
        foreach (array_keys($this->scopedEntries) as $class) {
            unset($this->entries[$class]);
        }
        $this->scopedEntries = [];
        return $this;
    }

    /**
     * Extend a bound class.
     *
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param Closure(TEntry, Container, array<array-key, mixed>): TEntry $extender
     * @return $this
     */
    public function extend(string $class, Closure $extender): static
    {
        array_key_exists($class, $this->entries)
            ? $this->getEntry($class)->extend($extender)
            : $this->setEntry($class)->extend($extender);
        return $this;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return Entry<TEntry>
     */
    protected function getEntry(string $class): Entry
    {
        if (!array_key_exists($class, $this->entries)) {
            throw new LogicException("{$class} is not registered.", [
                'class' => $class,
            ]);
        }
        /** @var Entry<TEntry> */
        return $this->entries[$class];
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return Entry<TEntry>
     */
    protected function setEntry(string $class): Entry
    {
        if ($this->has($class)) {
            throw new LogicException("Cannot register class: {$class}. Entry already exists.", [
                'class' => $class,
            ]);
        }
        /** @var Entry<TEntry> */
        return $this->entries[$class] ??= new Entry($this, $class);
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function resolve(string $class, array $args = []): object
    {
        return $this->has($class)
            ? $this->get($class, $args)
            : $this->make($class, $args);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function make(string $class, array $args = []): object
    {
        return $this->injector->constructorInjection($class, $args);
    }

    /**
     * @template TResult
     * @param Closure(): TResult $closure
     * @param array<array-key, mixed> $args
     * @return TResult
     */
    public function call(Closure $closure, mixed $args): mixed
    {
        return $this->injector->closureInjection($closure, $args);
    }

    /**
     * Set a callback which is called when a class is resolving.
     *
     * @param Closure(Entry<object>): mixed $callback
     * @return void
     */
    public function onResolving(Closure $callback): void
    {
        $this->resolvingCallbacks[] = $callback;
    }

    /**
     * Set a callback which is called when a class is resolved.
     *
     * @param Closure(Entry<object>, mixed): mixed $callback
     * @return void
     */
    public function onResolved(Closure $callback): void
    {
        $this->resolvedCallbacks[] = $callback;
    }
}
