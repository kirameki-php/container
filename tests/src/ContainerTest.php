<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use DateTime;
use Kirameki\Container\Container;
use Kirameki\Container\Events\Injected;
use Kirameki\Container\Events\Injecting;
use Kirameki\Container\Events\Resolved;
use Kirameki\Container\Events\Resolving;
use Kirameki\Container\Exceptions\InjectionException;
use Kirameki\Container\Lifetime;
use Kirameki\Exceptions\LogicException;
use Tests\Kirameki\Container\Sample\Basic;
use Tests\Kirameki\Container\Sample\BasicExtended;
use Tests\Kirameki\Container\Sample\Builtin;
use Tests\Kirameki\Container\Sample\Circular1;
use Tests\Kirameki\Container\Sample\Circular2;
use Tests\Kirameki\Container\Sample\InterfaceDefault;
use Tests\Kirameki\Container\Sample\Intersect;
use Tests\Kirameki\Container\Sample\NoType;
use Tests\Kirameki\Container\Sample\NoTypeDefault;
use Tests\Kirameki\Container\Sample\Nullable;
use Tests\Kirameki\Container\Sample\ParentType;
use Tests\Kirameki\Container\Sample\SelfType;
use Tests\Kirameki\Container\Sample\Union;
use Tests\Kirameki\Container\Sample\Variadic;
use TypeError;

final class ContainerTest extends TestCase
{
    public function test___construct(): void
    {
        $container = $this->builder->build();

        $this->assertInstanceOf(Container::class, $container);
        $this->assertTrue($container->has(Container::class));
        $this->assertSame($container, $container->get(Container::class));
    }


    public function test_has(): void
    {
        $this->builder->transient(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();

        $this->assertTrue($container->has(DateTime::class));
    }

    public function test_unset(): void
    {
        $this->builder->transient(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        // Check existence and delete
        $this->assertTrue($container->has(DateTime::class));
        $this->assertTrue($container->unset(DateTime::class));

        // Check after delete
        $this->assertFalse($container->has(DateTime::class));

        // Try Deleting twice
        $this->assertFalse($container->unset(DateTime::class));

        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_scoped(): void
    {
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic1 = $container->make(Basic::class);
        $basic2 = $container->make(Basic::class);

        $this->assertSame($basic2->d, $basic1->d);
        $this->assertTrue($container->has(DateTime::class));
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(2);
        $this->assertTotalInjectedCount(2);
    }

    public function test_clearScoped(): void
    {
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic1 = $container->make(Basic::class);
        $this->assertSame(1, $container->clearScoped());
        $basic2 = $container->make(Basic::class);

        $this->assertNotSame($basic2->d, $basic1->d);
        $this->assertTrue($container->has(DateTime::class));
        $this->assertTotalResolvingCount(2);
        $this->assertTotalResolvedCount(2);
        $this->assertTotalInjectingCount(2);
        $this->assertTotalInjectedCount(2);
    }

    public function test_make_with_bound_class(): void
    {
        $instance = new Basic(new DateTime());
        $this->builder->singleton(Basic::class, fn() => $instance);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $result = $container->make(Basic::class);

        $this->assertSame($instance, $result);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_make_with_args_and_bound_class(): void
    {
        $now = new DateTime();
        $basic = new Basic($now);
        $this->builder->singleton(Basic::class, fn() => $basic);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $result = $container->make(Basic::class, [$now, 2]);

        $this->assertNotSame($basic, $result);
        $this->assertSame($now, $result->d);
        $this->assertSame(2, $result->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_parameters(): void
    {
        $now = new DateTime();
        $container = $this->builder->build();
        $this->addCallbackCounters($container);
        $basic = $container->make(Basic::class, [$now, 2]);

        $this->assertSame($now, $basic->d);
        $this->assertSame(2, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_named_parameters(): void
    {
        $now = new DateTime();
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic = $container->make(Basic::class, ['d' => $now, 'i' => 2]);
        $this->assertSame($now, $basic->d);
        $this->assertSame(2, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_null_parameters(): void
    {
        $this->expectExceptionMessage(Basic::class . '::__construct(): Argument #1 ($d) must be of type DateTime, null given');
        $this->expectException(TypeError::class);
        $container = $this->builder->build();
        $container->make(Basic::class, [null]);
    }

    public function test_make_with_non_existing_positional_parameter(): void
    {
        $this->expectExceptionMessage('Argument with position: 1 does not exist for class: ' . Builtin::class . '.');
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(Builtin::class, [1, 2]);
    }

    public function test_make_with_non_existing_named_parameter(): void
    {
        $this->expectExceptionMessage('Argument with name: z does not exist for class: ' . Builtin::class . '.');
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(Builtin::class, ['z' => 1]);
    }

    public function test_make_with_named_params_using_default_value(): void
    {
        $now = new DateTime();
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic = $container->make(Basic::class, ['d' => $now]);

        $this->assertSame($now->getTimestamp(), $basic->d->getTimestamp());
        $this->assertSame(1, $basic->i);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_bound_parameter(): void
    {
        $now = new DateTime();
        $this->builder->transient(DateTime::class, static fn() => $now);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);
        $basic = $container->make(Basic::class);

        $this->assertSame($now, $basic->d);
        $this->assertSame(1, $basic->i);
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_no_types(): void
    {
        $this->expectExceptionMessage('[' . NoType::class . '] Argument: $a must be a class or have a default value.');
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(NoType::class);
    }

    public function test_make_with_no_types_but_has_default(): void
    {
        $container = $this->builder->build();
        $this->addCallbackCounters($container);
        $noType = $container->make(NoTypeDefault::class);

        $this->assertSame(1, $noType->a);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_type(): void
    {
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $variadic = $container->make(Variadic::class);

        $this->assertEmpty($variadic->list);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_with_bindings(): void
    {
        $this->builder->singleton(DateTime::class, fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $variadic = $container->make(Variadic::class);

        $this->assertEmpty($variadic->list);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_variadic_with_arguments(): void
    {
        $now = new DateTime();
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $variadic = $container->make(Variadic::class, [$now, $now]);

        $this->assertSame($now, $variadic->list[0]);
        $this->assertSame($now, $variadic->list[1]);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_intersect_type(): void
    {
        $this->expectExceptionMessage('[' . Intersect::class . '] Invalid type on argument: ' . Basic::class . '&' . BasicExtended::class . ' $a. Intersection types are not allowed.');
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(Intersect::class);
    }

    public function test_make_with_builtin_type(): void
    {
        $this->expectExceptionMessage('[' . Builtin::class . '] Invalid type on argument: int $a. Built-in types are not allowed.');
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(Builtin::class);
    }

    public function test_make_with_union_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('[' . Union::class . '] Invalid type on argument: ' . Basic::class . '|' . BasicExtended::class . ' $a. Union types are not allowed.');
        $container = $this->builder->build();
        $container->make(Union::class);
    }

    public function test_make_with_self_type(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular Dependency detected! ' . SelfType::class . ' -> ' . SelfType::class);
        $container = $this->builder->build();
        $container->make(SelfType::class);
    }

    public function test_make_with_parent_type(): void
    {
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $parentType = $container->make(ParentType::class);
        $this->assertSame(1, $parentType->a);
        $this->assertTotalInjectedCount(2);
    }

    public function test_make_with_nullable_type(): void
    {
        $this->builder->transient(DateTime::class, fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $nullable = $container->make(Nullable::class);
        $this->assertInstanceOf(DateTime::class, $nullable->a);
        $this->assertTotalInjectedCount(1);
    }

    public function test_make_with_missing_parameter(): void
    {
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $data = $container->make(Basic::class, ['i' => 2]);

        $this->assertInstanceOf(DateTime::class, $data->d);
        $this->assertSame(2, $data->i);
        $this->assertTotalInjectedCount(2);
    }

    public function test_make_with_circular_dependency(): void
    {
        $this->expectExceptionMessage(strtr('Circular Dependency detected! %c1 -> %c2 -> %c1', [
            '%c1' => Circular1::class,
            '%c2' => Circular2::class,
        ]));
        $this->expectException(LogicException::class);
        $container = $this->builder->build();
        $container->make(Circular1::class);
    }

    public function test_make_with_interface_with_default(): void
    {
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $interfaceDefault = $container->make(InterfaceDefault::class);

        $this->assertInstanceOf(DateTime::class, $interfaceDefault->d);
        $this->assertTotalInjectedCount(1);
    }

    public function test_whenInjecting_with_provided_type(): void
    {
        $now = new DateTime();
        $this->builder->whenInjecting(Basic::class)->provide(DateTime::class, $now);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $var = $container->inject(Basic::class);

        $this->assertSame($now, $var->d);
        $this->assertSame(1, $var->i);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_whenInjecting_with_provided_non_existing_type(): void
    {
        $this->expectExceptionMessage('Provided injections: ' . Builtin::class . ' do not exist for class: ' . Basic::class);
        $this->expectException(InjectionException::class);
        $this->builder->whenInjecting(Basic::class)
            ->provide(Builtin::class, new Builtin(1))
            ->provide(DateTime::class, new DateTime());
        $container = $this->builder->build();
        $container->inject(Basic::class);
    }

    public function test_whenInjecting_with_positional_args_no_type(): void
    {
        $this->builder->whenInjecting(NoType::class)->pass(a: true, b: 2);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $var = $container->inject(NoType::class);

        $this->assertSame(true, $var->a);
        $this->assertSame(2, $var->b);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_whenInjecting_with_positional_args_variadic(): void
    {
        $now = new DateTime();
        $this->builder->whenInjecting(Variadic::class)->pass($now, $now);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $var = $container->inject(Variadic::class);

        $this->assertSame([$now, $now], $var->list);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectedCount(1);
    }

    public function test_whenInjecting_with_named_args(): void
    {
        $this->builder->whenInjecting(Builtin::class)->pass(a: 2);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $var = $container->inject(Builtin::class);

        $this->assertSame(2, $var->a);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(1);
        $this->assertTotalInjectedCount(1);
    }

    public function test_call(): void
    {
        $this->builder->transient(Builtin::class, fn() => new Builtin(1));
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $this->assertSame(1, $container->call(static fn (Builtin $o) => $o->a));
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_onResolving(): void
    {
        $this->builder->singleton(DateTime::class, fn() => new DateTime());
        $container = $this->builder->build();

        $container->onResolving->do(function (Resolving $event): void {
            $this->assertSame(DateTime::class, $event->id);
            $this->assertSame(Lifetime::Singleton, $event->lifetime);
        });

        $container->get(DateTime::class);
    }

    public function test_onResolved(): void
    {
        $now = new DateTime();
        $this->builder->singleton(DateTime::class, fn() => $now);
        $container = $this->builder->build();

        $container->onResolved->do(function (Resolved $event) use ($now): void {
            $this->assertSame(DateTime::class, $event->id);
            $this->assertSame($now, $event->instance);
        });

        $container->get(DateTime::class);
    }

    public function test_onInjecting(): void
    {
        $container = $this->builder->build();

        $container->onInjecting->do(function (Injecting $event): void {
            $this->assertSame(Variadic::class, $event->class);
        });

        $container->make(Variadic::class);
    }

    public function test_onInjected(): void
    {
        $container = $this->builder->build();

        $container->onInjected->do(function (Injected $event): void {
            $this->assertSame(Variadic::class, $event->class);
            $this->assertInstanceOf(Variadic::class, $event->instance);
        });

        $container->make(Variadic::class);
    }

    public function test_instance(): void
    {
        $now = new DateTime();
        $basic = new Basic($now, 42);
        $this->builder->instance(Basic::class, $basic);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $resolved = $container->get(Basic::class);
        $this->assertSame($basic, $resolved);
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_pull_with_instance(): void
    {
        $basic = new Basic(new DateTime(), 42);
        $this->builder->instance(Basic::class, $basic);
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $pulled = $container->pull(Basic::class);

        $this->assertSame($basic, $pulled);
        $this->assertFalse($container->has(Basic::class));
        $this->assertTotalResolvingCount(0);
        $this->assertTotalResolvedCount(0);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_pull_with_transient(): void
    {
        $this->builder->transient(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $pulled = $container->pull(DateTime::class);

        $this->assertInstanceOf(DateTime::class, $pulled);
        $this->assertFalse($container->has(DateTime::class));
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_pull_with_singleton(): void
    {
        $this->builder->singleton(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $pulled = $container->pull(DateTime::class);

        $this->assertInstanceOf(DateTime::class, $pulled);
        $this->assertFalse($container->has(DateTime::class));
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_pull_with_scoped(): void
    {
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $pulled = $container->pull(DateTime::class);

        $this->assertInstanceOf(DateTime::class, $pulled);
        $this->assertFalse($container->has(DateTime::class));
        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_pull_not_registered(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DateTime::class . ' is not registered.');
        $container = $this->builder->build();
        $container->pull(DateTime::class);
    }

    public function test_pull_multiple_times(): void
    {
        $this->builder->singleton(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();

        $pulled1 = $container->pull(DateTime::class);
        $this->assertInstanceOf(DateTime::class, $pulled1);
        $this->assertFalse($container->has(DateTime::class));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(DateTime::class . ' is not registered.');
        $container->pull(DateTime::class);
    }
}
