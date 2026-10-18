<?php

declare(strict_types=1);

namespace PHPUnit\Framework {
    class TestCase
    {
        public static function assertGreaterThan(int|float $expected, int|float $actual): void
        {
            if (!($actual > $expected)) {
                throw new \RuntimeException("Falhou: {$actual} não é maior que {$expected}");
            }
        }

        public static function assertSame(mixed $expected, mixed $actual): void
        {
            if ($expected !== $actual) {
                throw new \RuntimeException('Falhou: esperado ' . var_export($expected, true) . ', recebido ' . var_export($actual, true));
            }
        }
    }
}

namespace {
    foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
        require_once $file;
    }
    $failures = 0;
    foreach (get_declared_classes() as $name) {
        if (!is_subclass_of($name, \PHPUnit\Framework\TestCase::class)) continue;
        $class = new \ReflectionClass($name);
        $setUp = $class->getMethod('setUp'); $setUp->setAccessible(true);
        foreach (array_filter($class->getMethods(\ReflectionMethod::IS_PUBLIC), static fn($m) => str_starts_with($m->getName(), 'test')) as $method) {
            $instance=$class->newInstance();
            try {$setUp->invoke($instance);$method->invoke($instance);echo "OK {$name}::{$method->getName()}\n";}
            catch(\Throwable $exception){++$failures;echo "FALHA {$name}::{$method->getName()}: {$exception->getMessage()}\n";}
        }
    }
    exit($failures === 0 ? 0 : 1);
}
