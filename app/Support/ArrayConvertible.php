<?php

namespace App\Support;

use DateTime;
use ReflectionClass;
use ReflectionNamedType;

trait ArrayConvertible
{
    public static function fromArray(array $data): static
    {
        $ref = new ReflectionClass(static::class);
        $params = [];

        foreach ($ref->getConstructor()->getParameters() as $param) {
            $name = $param->getName();

            if (!array_key_exists($name, $data)) {
                $params[] = $param->isDefaultValueAvailable()
                    ? $param->getDefaultValue()
                    : null;
                continue;
            }

            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Caso DTO con este trait
                if (method_exists($typeName, 'fromArray') && is_array($data[$name])) {
                    $params[] = $typeName::fromArray($data[$name]);

                    // Caso DateTime
                } elseif ($typeName === DateTime::class) {
                    $params[] = new DateTime($data[$name]);

                } else {
                    $params[] = $data[$name];
                }

            } elseif ($type?->getName() === 'array' && is_array($data[$name])) {
                // Detectamos si es array de arrays -> posible array de DTOs
                $items = $data[$name];

                if (is_array(reset($items))) {
                    // Intentar deducir el tipo del docblock
                    $docComment = $param->getDeclaringFunction()->getDocComment() ?: '';
                    if (preg_match('/@var\s+([^\s\[\]]+)\[\]/', $docComment, $m)) {
                        $className = $m[1];
                        if (class_exists($className) && method_exists($className, 'fromArray')) {
                            $params[] = array_map(fn($item) => $className::fromArray($item), $items);
                            continue;
                        }
                    }
                }

                // Array normal
                $params[] = $items;

            } else {
                $params[] = $data[$name];
            }
        }

        return new static(...$params);
    }

    public function toArray(): array
    {
        $vars = get_object_vars($this);
        $result = [];

        foreach ($vars as $key => $value) {
            if (is_array($value)) {
                $result[$key] = array_map(fn($v) => $v instanceof self ? $v->toArray() : $v, $value);

            } elseif ($value instanceof self) {
                $result[$key] = $value->toArray();

            } elseif ($value instanceof DateTime) {
                $result[$key] = $value->format('Y-m-d');

            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
