<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Entry;
use Kirameki\Container\Lifetime;
use Tests\Kirameki\Container\Sample\NoType;

class EntryTest extends TestCase
{
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
