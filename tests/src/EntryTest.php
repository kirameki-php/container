<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\LazyEntry;
use Kirameki\Container\Lifetime;
use Tests\Kirameki\Container\Sample\NoType;

class EntryTest extends TestCase
{
    public function test_isResolved_initially_false(): void
    {
        $entry = new LazyEntry(Lifetime::Transient, fn() => new NoType(0));
        $this->assertFalse($entry->isResolved());
    }

    public function test_isResolved_after_getInstance_singleton(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Singleton, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isResolved());
    }

    public function test_isResolved_after_getInstance_transient(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Transient, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertFalse($entry->isResolved());
    }

    public function test_isResolved_after_getInstance_scoped(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Scoped, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isResolved());
    }

    public function test_unsetInstance_transient(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Transient, fn() => new NoType(0));
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertFalse($entry->isResolved());
        $this->assertFalse($entry->unsetInstance());
        $this->assertFalse($entry->isResolved());
    }

    public function test_unsetInstance_singleton(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Singleton, fn() => new NoType(0));
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertTrue($entry->isResolved());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isResolved());
    }

    public function test_unsetInstance_scoped(): void
    {
        $container = $this->builder->build();
        $entry = new LazyEntry(Lifetime::Scoped, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isResolved());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isResolved());
    }
}
