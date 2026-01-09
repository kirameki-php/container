<?php declare(strict_types=1);

namespace Tests\Kirameki\Container;

use DateTime;
use Kirameki\Container\ContainerBuilder;
use Kirameki\Container\Exceptions\InjectionException;
use Kirameki\Container\Exceptions\ResolverNotFoundException;
use Kirameki\Exceptions\LogicException;
use Tests\Kirameki\Container\Sample\Basic;
use Tests\Kirameki\Container\Sample\BasicExtended;
use Tests\Kirameki\Container\Sample\Builtin;
use Tests\Kirameki\Container\Sample\NoType;
use Tests\Kirameki\Container\Sample\NoTypeDefault;
use Tests\Kirameki\Container\Sample\Variadic;
use TypeError;

final class ContainerBuilderTest extends TestCase
{
    public function test_has(): void
    {
        $this->assertFalse($this->builder->has(DateTime::class));

        $this->builder->set(DateTime::class, static fn() => new DateTime());

        $this->assertTrue($this->builder->has(DateTime::class));
    }

    public function test_unset(): void
    {
        $this->builder->set(DateTime::class, static fn() => new DateTime());

        // Check existence and delete
        $this->assertTrue($this->builder->has(DateTime::class));
        $this->assertTrue($this->builder->unset(DateTime::class));

        // Check after delete
        $this->assertFalse($this->builder->has(DateTime::class));

        // Try Deleting twice
        $this->assertFalse($this->builder->unset(DateTime::class));
    }

    public function test_scoped(): void
    {
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic1 = $container->make(Basic::class);
        $basic2 = $container->make(Basic::class);

        $this->assertSame($basic2->d, $basic1->d);
        $this->assertTrue($this->builder->has(DateTime::class));
        $this->assertTrue($container->has(DateTime::class));

        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(2);
        $this->assertTotalInjectedCount(2);
    }

    public function test_scoped_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
        $this->builder->scoped(DateTime::class, static fn() => new DateTime());
    }

    public function test_singleton(): void
    {
        $this->builder->singleton(DateTime::class, static fn() => new DateTime());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic1 = $container->make(Basic::class);
        $basic2 = $container->make(Basic::class);

        $this->assertSame($basic2->d, $basic1->d);
        $this->assertTrue($this->builder->has(DateTime::class));
        $this->assertTrue($container->has(DateTime::class));

        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(2);
        $this->assertTotalInjectedCount(2);
    }

    public function test_singleton_twice(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot register class: ' . DateTime::class . '. Entry already exists.');
        $this->builder->singleton(DateTime::class, static fn() => new DateTime());
        $this->builder->singleton(DateTime::class, static fn() => new DateTime());
    }

    public function test_extend(): void
    {
        $this->builder->set(Basic::class, fn() => new Basic(new DateTime()));
        $this->builder->extend(Basic::class, fn() => new BasicExtended());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic = $container->get(Basic::class);

        $this->assertSame('2022-02-02', $basic->d->format('Y-m-d'));
        $this->assertSame(100, $basic->i);

        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_extend_resolved_singleton(): void
    {
        $this->builder->singleton(Basic::class, fn() => new Basic(new DateTime('1970-01-01')));
        $this->builder->extend(Basic::class, fn() => new BasicExtended());
        $container = $this->builder->build();
        $this->addCallbackCounters($container);

        $basic2 = $container->get(Basic::class);

        $this->assertSame('2022-02-02', $basic2->d->format('Y-m-d'));
        $this->assertSame(100, $basic2->i);

        $this->assertTotalResolvingCount(1);
        $this->assertTotalResolvedCount(1);
        $this->assertTotalInjectingCount(0);
        $this->assertTotalInjectedCount(0);
    }

    public function test_extend_nothing(): void
    {
        $this->expectException(ResolverNotFoundException::class);
        $this->expectExceptionMessage('DateTime is not set.');
        $this->builder->extend(DateTime::class, fn() => new DateTime());
        $container = $this->builder->build();
        $container->get(DateTime::class);
    }

    public function test_extend_invalid_return_type(): void
    {
        $this->expectExceptionMessage('Expected: instance of ' . DateTime::class . '. Got: ' . NoTypeDefault::class . '.');
        $this->expectException(LogicException::class);
        $this->builder->set(DateTime::class, fn() => new DateTime());
        $this->builder->extend(DateTime::class, fn() => new NoTypeDefault());
        $container = $this->builder->build();
        $container->get(DateTime::class);
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
        $var = $container->inject(Builtin::class);

        $this->assertSame(2, $var->a);
    }

    public function test_instance(): void
    {
        $now = new DateTime();
        $basic = new Basic($now, 42);
        $this->builder->instance(Basic::class, $basic);
        $container = $this->builder->build();

        $resolved = $container->get(Basic::class);
        $this->assertSame($basic, $resolved);
    }
}
