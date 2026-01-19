<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Entry;
use Kirameki\Container\Lifetime;
use Tests\Kirameki\Container\Sample\NoType;

class EntryTest extends TestCase
{
    public function test_isInstantiable_initially_false(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isInstantiable());
    }

    public function test_isInstantiable_with_resolver(): void
    {
        $entry = new Entry(NoType::class, Lifetime::Transient, fn() => new NoType(0));
        $this->assertTrue($entry->isInstantiable());
    }

    public function test_isInstantiable_after_extend(): void
    {
        $entry = new Entry(NoType::class);
        $entry->extend(fn(NoType $instance) => new NoType(1));
        $this->assertFalse($entry->isInstantiable());
    }

    public function test_isResolvable_initially_false(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isResolvable());
    }

    public function test_isResolvable_with_resolver(): void
    {
        $entry = new Entry(NoType::class, Lifetime::Transient, fn() => new NoType(0));
        $this->assertTrue($entry->isResolvable());
    }

    public function test_isExtended_initially_false(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isExtended());
    }

    public function test_isExtended_after_extend(): void
    {
        $entry = new Entry(NoType::class);
        $entry->extend(fn(NoType $instance) => new NoType(1));
        $this->assertTrue($entry->isExtended());
    }

    public function test_isCached_initially_false(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isCached());
    }

    public function test_isCached_after_getInstance_singleton(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Singleton, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
    }

    public function test_isCached_after_getInstance_transient(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Transient, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertFalse($entry->isCached());
    }

    public function test_isCached_after_getInstance_scoped(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Scoped, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
    }

    public function test_unsetInstance_transient(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Transient, fn() => new NoType(0));
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertFalse($entry->isCached());
        $this->assertFalse($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_singleton(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Singleton, fn() => new NoType(0));
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_extended(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Singleton, fn() => new NoType(0));
        $entry->extend(fn(NoType $instance) => new NoType(1));
        $this->assertSame(1, $entry->getInstance($container)->a);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
        $this->assertTrue($entry->isExtended());
    }

    public function test_unsetInstance_scoped(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class, Lifetime::Scoped, fn() => new NoType(0));
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }
}
