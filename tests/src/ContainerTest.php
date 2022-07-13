<?php declare(strict_types=1);

namespace Tests\Kirameki;

use DateTime;
use LogicException;
use Tests\Kirameki\Sample\BasicExtended;
use Tests\Kirameki\Sample\Builtin;
use Tests\Kirameki\Sample\Circular1;
use Tests\Kirameki\Sample\Circular2;
use Tests\Kirameki\Sample\Basic;
use Tests\Kirameki\Sample\Intersect;
use Tests\Kirameki\Sample\NoType;
use Tests\Kirameki\Sample\NoTypeDefault;
use Tests\Kirameki\Sample\Nullable;
use Tests\Kirameki\Sample\ParentType;
use Tests\Kirameki\Sample\SelfType;
use Tests\Kirameki\Sample\Union;
use Tests\Kirameki\Sample\Variadic;

class ContainerTest extends TestCase
{
    public function test_resolve(): void
    {
        $now = new DateTime();

        $this->container->bind(DateTime::class, static fn() => $now);

        $resolved = $this->container->resolve(DateTime::class);

        self::assertSame($now, $resolved);
    }

    public function test_resolve_not_registered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DateTime::class . ' is not registered.');
        $this->container->resolve(DateTime::class);
    }

    public function test_bind(): void
    {
        $this->container->bind(DateTime::class, static fn() => new DateTime());

        $basic1 = $this->container->inject(Basic::class);
        $basic2 = $this->container->inject(Basic::class);

        self::assertNotSame($basic2->d, $basic1->d);
        $this->assertTotalResolvingCount(2);
        $this->assertTotalResolvedCount(2);
    }

    public function test_bind_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->container->bind(DateTime::class, static fn() => new DateTime());
        $this->container->bind(DateTime::class, static fn() => new DateTime());
    }

    public function test_contains(): void
    {
        self::assertFalse($this->container->contains(DateTime::class));

        $this->container->bind(DateTime::class, static fn() => new DateTime());

        self::assertTrue($this->container->contains(DateTime::class));
    }

    public function test_notContains(): void
    {
        self::assertTrue($this->container->notContains(DateTime::class));

        $this->container->bind(DateTime::class, static fn() => new DateTime());

        self::assertFalse($this->container->notContains(DateTime::class));
    }

    public function test_delete(): void
    {
        $this->container->bind(DateTime::class, static fn() => new DateTime());

        // Check existence and delete
        self::assertTrue($this->container->contains(DateTime::class));
        self::assertTrue($this->container->delete(DateTime::class));

        // Check after delete
        self::assertFalse($this->container->contains(DateTime::class));

        // Try Deleting twice
        self::assertFalse($this->container->delete(DateTime::class));

        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
    }

    public function test_singleton(): void
    {
        $this->container->singleton(DateTime::class, static fn() => new DateTime());

        $basic1 = $this->container->inject(Basic::class);
        $basic2 = $this->container->inject(Basic::class);

        self::assertSame($basic2->d, $basic1->d);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
    }

    public function test_singleton_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->container->singleton(DateTime::class, static fn() => new DateTime());
        $this->container->singleton(DateTime::class, static fn() => new DateTime());
    }

    public function test_extend(): void
    {
        $this->container->bind(Basic::class, fn() => new Basic(new DateTime()));
        $this->container->extend(Basic::class, fn() => new BasicExtended());
        $basic = $this->container->resolve(Basic::class);

        self::assertSame('2022-02-02', $basic->d->format('Y-m-d'));
        self::assertSame(100, $basic->i);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
    }

    public function test_extend_resolved_singleton(): void
    {
        $this->container->singleton(Basic::class, fn() => new Basic(new DateTime('1970-01-01')));
        $basic1 = $this->container->resolve(Basic::class);

        // extend after resolved will reset registration
        $this->container->extend(Basic::class, fn() => new BasicExtended());
        $basic2 = $this->container->resolve(Basic::class);

        self::assertSame('1970-01-01', $basic1->d->format('Y-m-d'));
        self::assertSame(1, $basic1->i);
        self::assertSame('2022-02-02', $basic2->d->format('Y-m-d'));
        self::assertSame(100, $basic2->i);
        $this->assertTotalResolvingCount(2);
        $this->assertTotalResolvedCount(2);
    }

    public function test_extend_nothing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DateTime cannot be extended since it is not defined.');
        $this->container->extend(DateTime::class, fn() => new DateTime());
    }

    public function test_extend_invalid_return_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Instance of ' . DateTime::class . ' expected. ' . NoTypeDefault::class . ' given.');
        $this->container->bind(DateTime::class, fn() => new DateTime());
        $this->container->extend(DateTime::class, fn() => new NoTypeDefault());
        $this->container->resolve(DateTime::class);
    }

    public function test_resolving(): void
    {
        $this->container->resolving(static function(string $class) {
            self::assertSame(DateTime::class, $class);
        });

        $this->container->singleton(DateTime::class, fn() => new DateTime());
        $this->container->resolve(DateTime::class);
    }

    public function test_resolved(): void
    {
        $now = new DateTime();

        $this->container->resolved(static function(string $class, mixed $instance) use ($now): void {
            self::assertSame(DateTime::class, $class);
            self::assertSame($now, $instance);
        });

        $this->container->singleton(DateTime::class, fn() => $now);
        $this->container->resolve(DateTime::class);
    }

    public function test_inject_with_bound_class(): void
    {
        $instance = new Basic(new DateTime());
        $this->container->singleton(Basic::class, fn() => $instance);

        $result = $this->container->inject(Basic::class);

        self::assertSame($instance, $result);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
    }

    public function test_inject_with_parameters(): void
    {
        $now = new DateTime();

        $basic = $this->container->inject(Basic::class, $now, 2);
        self::assertSame($now, $basic->d);
        self::assertSame(2, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
    }

    public function test_inject_with_named_parameters(): void
    {
        $now = new DateTime();

        $basic = $this->container->inject(Basic::class, d: $now, i: 2);
        self::assertSame($now, $basic->d);
        self::assertSame(2, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
    }

    public function test_inject_with_named_params_using_default_value(): void
    {
        $now = new DateTime();

        $basic = $this->container->inject(Basic::class, d: $now);

        self::assertSame($now->getTimestamp(), $basic->d->getTimestamp());
        self::assertSame(1, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
    }

    public function test_inject_with_bound_parameter(): void
    {
        $now = new DateTime();

        $this->container->bind(DateTime::class, static fn() => $now);
        $basic = $this->container->inject(Basic::class);

        self::assertSame($now, $basic->d);
        self::assertSame(1, $basic->i);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
    }

    public function test_inject_with_no_types(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Argument $a for ' . NoType::class . ' must be a class or have a default value.');
        $this->container->inject(NoType::class);
    }

    public function test_inject_with_no_types_but_has_default(): void
    {
        $noType = $this->container->inject(NoTypeDefault::class);

        self::assertSame(1, $noType->a);
    }

    public function test_inject_variadic_type(): void
    {
        $variadic = $this->container->inject(Variadic::class);

        self::assertEmpty($variadic->list);
    }

    public function test_inject_variadic_with_bindings(): void
    {
        $this->container->singleton(DateTime::class, fn() => new DateTime());
        $variadic = $this->container->inject(Variadic::class);

        self::assertEmpty($variadic->list);
    }

    public function test_inject_variadic_with_arguments(): void
    {
        $now = new DateTime();
        $variadic = $this->container->inject(Variadic::class, $now, $now);

        self::assertSame($now, $variadic->list[0]);
        self::assertSame($now, $variadic->list[1]);
    }

    public function test_inject_with_intersect_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tests\Kirameki\Sample\Intersect Invalid type on argument: Tests\Kirameki\Sample\Basic&Tests\Kirameki\Sample\BasicExtended $a. Union/intersect/built-in types are not allowed.');
        $this->container->inject(Intersect::class);
    }

    public function test_inject_with_builtin_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tests\Kirameki\Sample\Builtin Invalid type on argument: int $a. Union/intersect/built-in types are not allowed.');
        $this->container->inject(Builtin::class);
    }

    public function test_inject_with_union_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tests\Kirameki\Sample\Union Invalid type on argument: Tests\Kirameki\Sample\Basic|Tests\Kirameki\Sample\BasicExtended $a. Union/intersect/built-in types are not allowed.');
        $this->container->inject(Union::class);
    }

    public function test_inject_with_self_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular Dependency detected! Tests\Kirameki\Sample\SelfType -> Tests\Kirameki\Sample\SelfType');
        $this->container->inject(SelfType::class);
    }

    public function test_inject_with_parent_type(): void
    {
        $parentType = $this->container->inject(ParentType::class);
        self::assertSame(1, $parentType->a);
    }

    public function test_inject_with_nullable_type(): void
    {
        $this->container->bind(DateTime::class, fn () => new DateTime());
        $nullable = $this->container->inject(Nullable::class);
        self::assertInstanceOf(DateTime::class, $nullable->a);

    }

    public function test_inject_with_missing_parameter(): void
    {
        $this->expectError();
        $this->expectErrorMessage('Argument #1 ($d) not passed');
        $this->container->inject(Basic::class, i: 2);
    }

    public function test_inject_with_circular_dependency(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(strtr('Circular Dependency detected! %c1 -> %c2 -> %c1', [
            '%c1' => Circular1::class,
            '%c2' => Circular2::class,
        ]));
        $this->container->inject(Circular1::class);
    }
}