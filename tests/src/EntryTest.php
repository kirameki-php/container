<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use Kirameki\Container\Entry;
use Kirameki\Container\Lifetime;
use Tests\Kirameki\Container\Sample\NoType;

class EntryTest extends TestCase
{
    public function test_unsetInstance_transient(): void
    {
        $entry = new Entry($this->container, NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Transient);
        $this->assertSame(0, $entry->getInstance()->a);
        $this->assertFalse($entry->isCached());
        $this->assertFalse($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_singleton(): void
    {
        $entry = new Entry($this->container, NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Singleton);
        $this->assertSame(0, $entry->getInstance()->a);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }

    public function test_unsetInstance_extended(): void
    {
        $entry = new Entry($this->container, NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Singleton);
        $entry->extend(fn(NoType $instance) => new NoType(1));
        $this->assertSame(1, $entry->getInstance()->a);
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
        $this->assertTrue($entry->isExtended());
    }

    public function test_unsetInstance_scoped(): void
    {
        $entry = new Entry($this->container, NoType::class);
        $entry->setResolver(fn() => new NoType(0), Lifetime::Scoped);
        $entry->getInstance();
        $this->assertTrue($entry->isCached());
        $this->assertTrue($entry->unsetInstance());
        $this->assertFalse($entry->isCached());
    }
}