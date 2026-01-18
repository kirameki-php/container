<?php declare(strict_types=1);

namespace Kirameki\Container;

use Closure;
use Countable;
use Kirameki\Container\Exceptions\DuplicateEntryException;
use Kirameki\Container\Exceptions\EntryNotFoundException;
use function array_key_exists;
use function array_keys;

class EntryCollection implements Countable
{
    /**
     * @param array<class-string, Entry<object>> $entries
     * @param array<class-string, null> $scopedEntryIds
     */
    public function __construct(
        protected array $entries = [],
        protected array $scopedEntryIds = [],
    ) {
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @param class-string $id
     * @return bool
     */
    public function has(mixed $id): bool
    {
        return array_key_exists($id, $this->entries);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return Entry<T>
     */
    public function get(string $id): Entry
    {
        $entry = $this->getOrNull($id);
        if ($entry !== null) {
            return $entry;
        }

        throw new EntryNotFoundException("{$id} is not registered.", [
            'class' => $id,
        ]);
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return Entry<T>|null
     */
    public function getOrNull(string $id): ?Entry
    {
        /** @var Entry<T>|null */
        return $this->entries[$id] ?? null;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return Entry<T>
     */
    protected function getOrNew(string $id): Entry
    {
        /** @var Entry<T> */
        return $this->entries[$id] ??= new Entry($id);
    }

    /**
     * @template T of object
     * @param Entry<T> $entry
     * @return void
     */
    public function set(Entry $entry): void
    {
        $id = $entry->id;
        $existing = $this->getOrNull($id);

        if ($existing?->isInstantiable()) {
            throw new DuplicateEntryException("Cannot register class: {$id}. Entry already exists.", [
                'class' => $id,
                'existingEntry' => $this->entries[$id],
            ]);
        }

        // Entry exists but has no resolver or instance, merge the new entry into existing one.
        $existing?->applyTo($entry);

        $this->entries[$id] = $entry;

        if ($entry->lifetime === Lifetime::Scoped) {
            $this->scopedEntryIds[$id] = null;
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @param Closure(T, Container): T $extender
     * @return void
     */
    public function extend(string $id, Closure $extender): void
    {
        $this->getOrNew($id)->extend($extender);
    }

    /**
     * @param class-string $id
     * @return void
     */
    public function remove(mixed $id): void
    {
        unset($this->entries[$id]);
        unset($this->scopedEntryIds[$id]);
    }

    /**
     * clear all scoped entries.
     *
     * @return int
     */
    public function clearScoped(): int
    {
        $count = 0;
        foreach (array_keys($this->scopedEntryIds) as $id) {
            $this->get($id)->unsetInstance();
            $count++;
        }
        $this->scopedEntryIds = [];
        return $count;
    }
}
