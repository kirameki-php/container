<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Injecting;
use Kirameki\Container\Events\Resolved;
use Kirameki\Container\Events\Resolving;
use Kirameki\Container\Exceptions\DuplicateEntryException;
use Kirameki\Container\Exceptions\EntryNotFoundException;
use Kirameki\Event\EventHandler;
use Psr\Container\ContainerInterface;
use function array_key_exists;
use function array_keys;

class Container implements ContainerInterface
{
    /**
     * @var Injector
     */
    protected readonly Injector $injector;

    /**
     * @var array<string, Entry>
     */
    protected array $entries = [];

    /**
     * @var array<string, null>
     */
    protected array $scopedEntryIds = [];

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
        get { return $this->onResolvingHandler ??= new EventHandler(Resolving::class); }
    }

    /**
     * @var EventHandler<Resolved>
     */
    public EventHandler $onResolved {
        get { return $this->onResolvedHandler ??= new EventHandler(Resolved::class); }
    }

    /**
     * @var EventHandler<Injecting>
     */
    public EventHandler $onInjecting {
        get { return $this->onInjectingHandler ??= new EventHandler(Injecting::class); }
    }

    /**
     * @var EventHandler<Injected>
     */
    public EventHandler $onInjected {
        get { return $this->onInjectedHandler ??= new EventHandler(Injected::class); }
    }

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
     * @param class-string<TEntry>|string $id
     * @return ($id is class-string<TEntry> ? TEntry : object)
     */
    public function get(string $id): mixed
    {
        $entry = $this->getEntry($id);

        if ($entry->isCached()) {
            return $entry->getInstance();
        }

        $this->onResolvingHandler?->emit(new Resolving($id, $entry->getLifetime()));

        $instance = $entry->getInstance();

        $this->onResolvedHandler?->emit(new Resolved($id, $entry->getLifetime(), $instance, $entry->isCached()));

        return $instance;
    }

    /**
     * Register a given id.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param Closure(Container): TEntry $resolver
     * @param Lifetime $lifetime
     * @return void
     */
    public function set(string $id, Closure $resolver, Lifetime $lifetime = Lifetime::Transient): void
    {
        $this->setEntry($id)->setResolver($resolver, $lifetime);
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param Closure(Container): TEntry $resolver
     * @return void
     */
    public function scoped(string $id, Closure $resolver): void
    {
        $this->set($id, $resolver, Lifetime::Scoped);
        $this->scopedEntryIds[$id] = null;
    }

    /**
     * Register a given class as a singleton.
     *
     * Singletons will cache the result upon resolution.
     *
     * Returns itself for chaining.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param Closure(Container): TEntry $resolver
     * @return void
     */
    public function singleton(string $id, Closure $resolver): void
    {
        $this->set($id, $resolver, Lifetime::Singleton);
    }

    /**
     * Delete a given entry.
     *
     * Returns **true** if entry is found, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function unset(string $id): bool
    {
        if ($this->has($id)) {
            unset($this->entries[$id]);
            unset($this->scopedEntryIds[$id]);
            return true;
        }
        return false;
    }

    /**
     * Check to see if a given class is bound to the container.
     *
     * Returns **true** if bound, **false** otherwise.
     *
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->entries) && $this->entries[$id]->isResolvable();
    }

    /**
     * Extend a registered class.
     *
     * The given Closure must return an instance of the original class or else Exception is thrown.
     *
     * @template TEntry of object
     * @param class-string<TEntry>|string $id
     * @param Closure(TEntry, Container): TEntry $extender
     * @return $this
     */
    public function extend(string $id, Closure $extender): static
    {
        $entry = $this->entries[$id] ?? $this->setEntry($id);
        $entry->extend($extender);
        return $this;
    }

    /**
     * Unset all scoped entries.
     *
     * @return void
     */
    public function unsetScopedEntries(): void
    {
        foreach (array_keys($this->scopedEntryIds) as $id) {
            $this->unset($id);
        }
        $this->scopedEntryIds = [];
    }

    /**
     * @param string $id
     * @return Entry
     */
    public function getEntry(string $id): Entry
    {
        if (!array_key_exists($id, $this->entries)) {
            throw new EntryNotFoundException("{$id} is not registered.", [
                'class' => $id,
            ]);
        }
        return $this->entries[$id];
    }

    /**
     * @param string $id
     * @return Entry
     */
    protected function setEntry(string $id): Entry
    {
        if (array_key_exists($id, $this->entries)) {
            throw new DuplicateEntryException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
                'existingEntry' => $this->entries[$id],
            ]);
        }
        return $this->entries[$id] = new Entry($this, $id);
    }

    /**
     * Instantiate class and inject parameters if given class is not registered, or resolve if registered.
     *
     * @template TEntry of object
     * @param class-string<TEntry> $id
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function make(string $id, array $args = []): object
    {
        return $this->has($id) && $args === []
            ? $this->get($id)
            : $this->inject($id, $args);
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @param array<array-key, mixed> $args
     * @return TEntry
     */
    public function inject(string $class, array $args = []): object
    {
        $this->onInjectingHandler?->emit(new Injecting($class));

        $instance = $this->injector->create($class, $args);

        $this->onInjectedHandler?->emit(new Injected($class, $instance));

        return $instance;
    }

    /**
     * @template TEntry of object
     * @param class-string<TEntry> $class
     * @return ContextProvider
     */
    public function whenInjecting(string $class): ContextProvider
    {
        return $this->injector->setContext($class, new ContextProvider($class));
    }

    /**
     * @param Closure $closure
     * @param array<array-key, mixed> $args
     * @return mixed
     */
    public function call(Closure $closure, mixed $args = []): mixed
    {
        return $this->injector->invoke($closure, $args);
    }
}
