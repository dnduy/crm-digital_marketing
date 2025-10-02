<?php

namespace Core;

/**
 * Dependency Injection Container
 * Simple PSR-11 compatible container for managing dependencies
 */
class Container
{
    private array $bindings = [];
    private array $instances = [];

    /**
     * Bind a service to the container
     */
    public function bind(string $abstract, $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];
    }

    /**
     * Register a singleton
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Get a service from the container
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new \Exception("Service {$id} not found in container");
        }

        // Return existing singleton instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $binding = $this->bindings[$id];
        $concrete = $binding['concrete'];

        // Create instance
        if (is_callable($concrete)) {
            $instance = $concrete($this);
        } else {
            $instance = $this->resolve($concrete);
        }

        // Store singleton
        if ($binding['singleton']) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if container has a service
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * Resolve a class with its dependencies
     */
    private function resolve(string $class)
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \Exception("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            } else if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve parameter {$parameter->getName()}");
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}