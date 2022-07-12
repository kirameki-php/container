<?php declare(strict_types=1);

namespace Tests\Kirameki;

use DateTime;
use LogicException;
use Tests\Kirameki\Sample\BasicExtended;
use Tests\Kirameki\Sample\Circular1;
use Tests\Kirameki\Sample\Circular2;
use Tests\Kirameki\Sample\Basic;

class ContainerTest extends TestCase
{
    public function test_bind(): void
    {
        $container = $this->container();
        $container->bind(DateTime::class, static fn() => new DateTime());

        $basic1 = $container->inject(Basic::class);
        $basic2 = $container->inject(Basic::class);

        self::assertNotSame($basic2->d, $basic1->d);
    }

    public function test_delete(): void
    {
        $container = $this->container();
        $container->bind(DateTime::class, static fn() => new DateTime());

        // Check existence and delete
        self::assertTrue($container->contains(DateTime::class));
        self::assertTrue($container->delete(DateTime::class));

        // Check after delete
        self::assertFalse($container->contains(DateTime::class));

        // Try Deleting twice
        self::assertFalse($container->delete(DateTime::class));
    }

    public function test_singleton(): void
    {
        $container = $this->container();
        $container->singleton(DateTime::class, static fn() => new DateTime());

        $basic1 = $container->inject(Basic::class);
        $basic2 = $container->inject(Basic::class);

        self::assertSame($basic2->d, $basic1->d);
    }

    public function test_extend(): void
    {
        $container = $this->container();
        $container->bind(Basic::class, fn() => new Basic(new DateTime()));
        $container->extend(Basic::class, fn() => new BasicExtended());
        $basic = $container->resolve(Basic::class);

        self::assertSame('2022-02-02 00:00:00', $basic->d->format('Y-m-d H:i:s'));
        self::assertSame(100, $basic->i);
    }

    public function test_extend_nothing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DateTime cannot be extended since it is not defined.');
        $container = $this->container();
        $container->extend(DateTime::class, fn () => new DateTime());
    }

    public function test_inject_with_bound_class(): void
    {
        $container = $this->container();
        $instance = new Basic(new DateTime());
        $container->singleton(Basic::class, fn() => $instance);

        $result = $container->inject(Basic::class);

        self::assertSame($instance, $result);
    }

    public function test_inject_with_bound_param(): void
    {
        $container = $this->container();
        $now = new DateTime();

        $container->bind(DateTime::class, static fn() => $now);
        $basic = $container->inject(Basic::class);

        self::assertSame($now, $basic->d);
        self::assertSame(1, $basic->i);
    }

    public function test_inject_with_named_params(): void
    {
        $container = $this->container();
        $now = new DateTime();

        $basic = $container->inject(Basic::class, d: $now, i: 2);
        self::assertSame($now->getTimestamp(), $basic->d->getTimestamp());
        self::assertSame(2, $basic->i);
    }

    public function test_inject_with_named_params_using_default_value(): void
    {
        $container = $this->container();
        $now = new DateTime();

        $basic = $container->inject(Basic::class, d: $now);

        self::assertSame($now->getTimestamp(), $basic->d->getTimestamp());
        self::assertSame(1, $basic->i);
    }

    public function test_inject_with_circular_dependency(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(strtr('Circular Dependency detected! %c1 -> %c2 -> %c1', [
            '%c1' => Circular1::class,
            '%c2' => Circular2::class,
        ]));
        $this->container()->inject(Circular1::class);
    }
}