<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use DateTime;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Resolved;
use Kirameki\Container\Exceptions\ResolverNotFoundException;
use Kirameki\Core\Exceptions\LogicException;
use Tests\Kirameki\Container\Sample\Basic;
use Tests\Kirameki\Container\Sample\BasicExtended;
use Tests\Kirameki\Container\Sample\Builtin;
use Tests\Kirameki\Container\Sample\Circular1;
use Tests\Kirameki\Container\Sample\Circular2;
use Tests\Kirameki\Container\Sample\Intersect;
use Tests\Kirameki\Container\Sample\NoType;
use Tests\Kirameki\Container\Sample\NoTypeDefault;
use Tests\Kirameki\Container\Sample\Nullable;
use Tests\Kirameki\Container\Sample\ParentType;
use Tests\Kirameki\Container\Sample\SelfType;
use Tests\Kirameki\Container\Sample\Union;
use Tests\Kirameki\Container\Sample\Variadic;
use TypeError;

class ContainerTest extends TestCase
{
    public function test_get(): void
    {
        $now = new DateTime();

        $this->container->set(DateTime::class, static fn() => $now);

        $resolved = $this->container->get(DateTime::class);

        $this->assertSame($now, $resolved);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(0);
    }

    public function test_get_not_registered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DateTime::class . ' is not registered.');
        $this->container->get(DateTime::class);
    }

    public function test_bind(): void
    {
        $this->container->set(DateTime::class, static fn() => new DateTime());

        $basic1 = $this->container->make(Basic::class);
        $basic2 = $this->container->make(Basic::class);

        $this->assertNotSame($basic2->d, $basic1->d);
        $this->assertTotalResolvedCount(2);
        $this->assertTotalInjectedCount(2);
    }

    public function test_bind_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->container->set(DateTime::class, static fn() => new DateTime());
        $this->container->set(DateTime::class, static fn() => new DateTime());
    }

    public function test_has(): void
    {
        $this->assertFalse($this->container->has(DateTime::class));

        $this->container->set(DateTime::class, static fn() => new DateTime());

        $this->assertTrue($this->container->has(DateTime::class));
    }

    public function test_delete(): void
    {
        $this->container->set(DateTime::class, static fn() => new DateTime());

        // Check existence and delete
        $this->assertTrue($this->container->has(DateTime::class));
        $this->assertTrue($this->container->unset(DateTime::class));

        // Check after delete
        $this->assertFalse($this->container->has(DateTime::class));

        // Try Deleting twice
        $this->assertFalse($this->container->unset(DateTime::class));

        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_scoped(): void
    {
        $this->container->scoped(DateTime::class, static fn() => new DateTime());

        $basic1 = $this->container->make(Basic::class);
        $basic2 = $this->container->make(Basic::class);

        $this->assertSame($basic2->d, $basic1->d);
        $this->assertTrue($this->container->has(DateTime::class));
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(2);
    }

    public function test_scoped_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->container->scoped(DateTime::class, static fn() => new DateTime());
        $this->container->scoped(DateTime::class, static fn() => new DateTime());
    }

    public function test_singleton(): void
    {
        $this->container->singleton(DateTime::class, static fn() => new DateTime());

        $basic1 = $this->container->make(Basic::class);
        $basic2 = $this->container->make(Basic::class);

        $this->assertSame($basic2->d, $basic1->d);
        $this->assertTrue($this->container->has(DateTime::class));
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(2);
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
        $this->container->set(Basic::class, fn() => new Basic(new DateTime()));
        $this->container->extend(Basic::class, fn() => new BasicExtended());
        $basic = $this->container->get(Basic::class);

        $this->assertSame('2022-02-02', $basic->d->format('Y-m-d'));
        $this->assertSame(100, $basic->i);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(0);
    }

    public function test_extend_resolved_singleton(): void
    {
        $this->container->singleton(Basic::class, fn() => new Basic(new DateTime('1970-01-01')));
        $basic1 = $this->container->get(Basic::class);

        // extend after resolved will reset registration
        $this->container->extend(Basic::class, fn() => new BasicExtended());
        $basic2 = $this->container->get(Basic::class);

        $this->assertSame('1970-01-01', $basic1->d->format('Y-m-d'));
        $this->assertSame(1, $basic1->i);
        $this->assertSame('2022-02-02', $basic2->d->format('Y-m-d'));
        $this->assertSame(100, $basic2->i);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(0);
    }

    public function test_extend_nothing(): void
    {
        $this->expectException(ResolverNotFoundException::class);
        $this->expectExceptionMessage('DateTime is not set.');
        $this->container->extend(DateTime::class, fn() => new DateTime());
        $this->container->get(DateTime::class);
    }

    public function test_extend_invalid_return_type(): void
    {
        $this->expectExceptionMessage('Expected: instance of ' . DateTime::class . '. Got: ' . NoTypeDefault::class . '.');
        $this->expectException(LogicException::class);
        $this->container->set(DateTime::class, fn() => new DateTime());
        $this->container->extend(DateTime::class, fn() => new NoTypeDefault());
        $this->container->get(DateTime::class);
    }

    public function test_unsetScopedEntries(): void
    {
        $this->container->scoped(DateTime::class, static fn() => new DateTime());
        $this->assertTrue($this->container->has(DateTime::class));
        $this->container->unsetScopedEntries();
        $this->assertFalse($this->container->has(DateTime::class));
    }

    public function test_onResolved(): void
    {
        $now = new DateTime();

        $this->container->onResolved(function(Resolved $event) use ($now): void {
            $this->assertSame(DateTime::class, $event->id);
            $this->assertSame($now, $event->instance);
        });

        $this->container->singleton(DateTime::class, fn() => $now);
        $this->container->get(DateTime::class);
    }

    public function test_make_with_bound_class(): void
    {
        $instance = new Basic(new DateTime());
        $this->container->singleton(Basic::class, fn() => $instance);

        $result = $this->container->make(Basic::class);

        $this->assertSame($instance, $result);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(0);
    }

    public function test_make_with_args_and_bound_class(): void
    {
        $now = new DateTime();
        $basic = new Basic($now);
        $this->container->singleton(Basic::class, fn() => $basic);

        $result = $this->container->make(Basic::class, [$now, 2]);

        $this->assertNotSame($basic, $result);
        $this->assertSame($now, $result->d);
        $this->assertSame(2, $result->i);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_parameters(): void
    {
        $now = new DateTime();
        $basic = $this->container->make(Basic::class, [$now, 2]);

        $this->assertSame($now, $basic->d);
        $this->assertSame(2, $basic->i);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_named_parameters(): void
    {
        $now = new DateTime();

        $basic = $this->container->make(Basic::class, ['d' => $now, 'i' => 2]);
        $this->assertSame($now, $basic->d);
        $this->assertSame(2, $basic->i);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_null_parameters(): void
    {
        $this->expectExceptionMessage(Basic::class . '::__construct(): Argument #1 ($d) must be of type DateTime, null given');
        $this->expectException(TypeError::class);
        $this->container->make(Basic::class, [null]);
    }

    public function test_make_with_non_existing_positional_parameter(): void
    {
        $this->expectExceptionMessage('Argument with position: 1 does not exist for class: ' . Builtin::class . '.');
        $this->expectException(LogicException::class);
        $this->container->make(Builtin::class, [1, 2]);
    }

    public function test_make_with_non_existing_named_parameter(): void
    {
        $this->expectExceptionMessage('Argument with name: z does not exist for class: ' . Builtin::class . '.');
        $this->expectException(LogicException::class);
        $this->container->make(Builtin::class, ['z' => 1]);
    }

    public function test_make_with_named_params_using_default_value(): void
    {
        $now = new DateTime();
        $basic = $this->container->make(Basic::class, ['d' => $now]);

        $this->assertSame($now->getTimestamp(), $basic->d->getTimestamp());
        $this->assertSame(1, $basic->i);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_bound_parameter(): void
    {
        $now = new DateTime();
        $this->container->set(DateTime::class, static fn() => $now);
        $basic = $this->container->make(Basic::class);

        $this->assertSame($now, $basic->d);
        $this->assertSame(1, $basic->i);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_no_types(): void
    {
        $this->expectExceptionMessage('[' . NoType::class . '] Argument: $a must be a class or have a default value.');
        $this->expectException(LogicException::class);
        $this->container->make(NoType::class);
    }

    public function test_make_with_no_types_but_has_default(): void
    {
        $noType = $this->container->make(NoTypeDefault::class);

        $this->assertSame(1, $noType->a);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_type(): void
    {
        $variadic = $this->container->make(Variadic::class);

        $this->assertEmpty($variadic->list);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_with_bindings(): void
    {
        $this->container->singleton(DateTime::class, fn() => new DateTime());
        $variadic = $this->container->make(Variadic::class);

        $this->assertEmpty($variadic->list);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_with_arguments(): void
    {
        $now = new DateTime();
        $variadic = $this->container->make(Variadic::class, [$now, $now]);

        $this->assertSame($now, $variadic->list[0]);
        $this->assertSame($now, $variadic->list[1]);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_intersect_type(): void
    {
        $this->expectExceptionMessage('[' . Intersect::class . '] Invalid type on argument: ' . Basic::class . '&' . BasicExtended::class . ' $a. Intersection types are not allowed.');
        $this->expectException(LogicException::class);
        $this->container->make(Intersect::class);
    }

    public function test_make_with_builtin_type(): void
    {
        $this->expectExceptionMessage('[' . Builtin::class . '] Invalid type on argument: int $a. Built-in types are not allowed.');
        $this->expectException(LogicException::class);
        $this->container->make(Builtin::class);
    }

    public function test_make_with_union_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[' . Union::class . '] Invalid type on argument: ' . Basic::class . '|' . BasicExtended::class . ' $a. Union types are not allowed.');
        $this->container->make(Union::class);
    }

    public function test_make_with_self_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular Dependency detected! ' . SelfType::class . ' -> '  . SelfType::class);
        $this->container->make(SelfType::class);
    }

    public function test_make_with_parent_type(): void
    {
        $parentType = $this->container->make(ParentType::class);
        $this->assertSame(1, $parentType->a);
        $this->assertTotalInjectedCount(2);
    }

    public function test_make_with_nullable_type(): void
    {
        $this->container->set(DateTime::class, fn () => new DateTime());
        $nullable = $this->container->make(Nullable::class);
        $this->assertInstanceOf(DateTime::class, $nullable->a);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_missing_parameter(): void
    {
        $this->expectExceptionMessage('[DateTimeZone] Invalid type on argument: string $timezone. Built-in types are not allowed');
        $this->expectException(LogicException::class);
        $this->container->make(Basic::class, ['i' => 2]);
    }

    public function test_make_with_circular_dependency(): void
    {
        $this->expectExceptionMessage(strtr('Circular Dependency detected! %c1 -> %c2 -> %c1', [
            '%c1' => Circular1::class,
            '%c2' => Circular2::class,
        ]));
        $this->expectException(LogicException::class);
        $this->container->make(Circular1::class);
    }

    public function test_onInjected(): void
    {
        $this->container->onInjected(function(Injected $event): void {
            $this->assertSame(Variadic::class, $event->class);
            $this->assertInstanceOf(Variadic::class, $event->instance);
        });

        $this->container->make(Variadic::class);
    }
}