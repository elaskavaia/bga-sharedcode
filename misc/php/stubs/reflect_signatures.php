<?php
/**
 * Extract class/method signatures from a PHP file using Reflection API.
 * Outputs JSON to stdout: { "ClassName": { "methodName": "signature", ... }, ... }
 *
 * Usage: php reflect_signatures.php <file.php>
 *
 * Must be run in a separate process per file to avoid class name conflicts.
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php reflect_signatures.php <file.php>\n");
    exit(1);
}

$file = $argv[1];
if (!file_exists($file)) {
    fwrite(STDERR, "File not found: $file\n");
    exit(1);
}

// Capture classes defined before loading the file
$classesBefore = get_declared_classes();

require_once $file;

$classesAfter = get_declared_classes();
$newClasses = array_diff($classesAfter, $classesBefore);

$result = [];

foreach ($newClasses as $className) {
    $ref = new ReflectionClass($className);

    // Skip internal PHP classes
    if ($ref->isInternal()) continue;

    $methods = [];
    foreach ($ref->getMethods() as $method) {
        // Only include methods declared in this class (not inherited)
        if ($method->getDeclaringClass()->getName() !== $className) continue;

        $sig = formatMethodSignature($method);
        $methods[$method->getName()] = $sig;
    }

    if (!empty($methods)) {
        ksort($methods);
        $result[$className] = $methods;
    }
}

ksort($result);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function formatMethodSignature(ReflectionMethod $method): string {
    $parts = [];

    if ($method->isPublic()) $parts[] = 'public';
    elseif ($method->isProtected()) $parts[] = 'protected';
    elseif ($method->isPrivate()) $parts[] = 'private';

    if ($method->isStatic()) $parts[] = 'static';
    if ($method->isFinal()) $parts[] = 'final';
    if ($method->isAbstract()) $parts[] = 'abstract';

    $parts[] = 'function';
    $parts[] = $method->getName();

    $params = [];
    foreach ($method->getParameters() as $param) {
        $p = '';
        if ($param->hasType()) {
            $p .= formatType($param->getType()) . ' ';
        }
        if ($param->isVariadic()) $p .= '...';
        $p .= '$' . $param->getName();
        if ($param->isDefaultValueAvailable()) {
            $default = $param->getDefaultValue();
            $p .= ' = ' . formatDefault($default);
        }
        $params[] = $p;
    }

    $sig = implode(' ', $parts) . '(' . implode(', ', $params) . ')';

    if ($method->hasReturnType()) {
        $sig .= ': ' . formatType($method->getReturnType());
    }

    return $sig;
}

function formatType(ReflectionType $type): string {
    if ($type instanceof ReflectionNamedType) {
        return ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '') . $type->getName();
    }
    if ($type instanceof ReflectionUnionType) {
        return implode('|', array_map(fn($t) => $t->getName(), $type->getTypes()));
    }
    if ($type instanceof ReflectionIntersectionType) {
        return implode('&', array_map(fn($t) => $t->getName(), $type->getTypes()));
    }
    return (string)$type;
}

function formatDefault($value): string {
    if (is_null($value)) return 'null';
    if (is_bool($value)) return $value ? 'true' : 'false';
    if (is_string($value)) return "'" . addslashes($value) . "'";
    if (is_array($value)) return '[]';
    if (is_object($value)) return 'new ' . get_class($value) . '(...)';
    return (string)$value;
}
