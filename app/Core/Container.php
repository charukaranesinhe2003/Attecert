<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;

final class Container
{
    private array $instances = [];

    public function instance(string $id, object $object): void
    {
        $this->instances[$id] = $object;
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        $reflection = new ReflectionClass($id);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $this->instances[$id] = $reflection->newInstance();
        }
        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type === null || $type->isBuiltin()) {
                throw new \RuntimeException('Cannot resolve ' . $id);
            }
            $arguments[] = $this->get($type->getName());
        }

        return $this->instances[$id] = $reflection->newInstanceArgs($arguments);
    }
}
