<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Entry;
use Kirameki\Container\Lifetime;
use Tests\Kirameki\Container\Sample\NoType;

class EntryTest extends TestCase
{
    public function test_isResolvable_initiallyFalse(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isResolvable());
    }

    public function test_isResolvable_afterSetResolver(): void
    {
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Transient);
        $this->assertTrue($entry->isResolvable());
    }

    public function test_isExtended_initiallyFalse(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isExtended());
    }

    public function test_isExtended_afterExtend(): void
    {
        $entry = new Entry(NoType::class);
        $entry->extend(fn(NoType $instance) => new NoType(1));
        $this->assertTrue($entry->isExtended());
    }

    public function test_isCached_initiallyFalse(): void
    {
        $entry = new Entry(NoType::class);
        $this->assertFalse($entry->isCached());
    }

    public function test_isCached_afterGetInstanceSingleton(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Singleton);
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
    }

    public function test_isCached_afterGetInstanceTransient(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Transient);
        $entry->getInstance($container);
        $this->assertFalse($entry->isCached());
    }

    public function test_isCached_afterGetInstanceScoped(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Scoped);
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
    }

    public function test_unsetInstance_transient(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Transient);
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertFalse($entry->isCached());
        $this->assertFalse($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_singleton(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Singleton);
        $this->assertSame(0, $entry->getInstance($container)->a);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_extended(): void
    {
        $container = $this->builder->build();
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Singleton);
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
        $entry = new Entry(NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Scoped);
        $entry->getInstance($container);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }
}
