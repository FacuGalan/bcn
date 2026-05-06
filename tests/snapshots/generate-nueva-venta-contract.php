<?php

/**
 * Generador del snapshot del contrato publico de App\Livewire\Ventas\NuevaVenta.
 *
 * Extrae con Reflection todo lo que hace al contrato externo del componente:
 *  - Propiedades publicas (nombre, tipo si esta declarado, default si lo hay).
 *  - Metodos publicos (firma con tipos y defaults).
 *  - Computed properties (#[Computed]) con sus opciones (cache, persist, etc.).
 *  - Listeners de eventos (#[On('event')]) con el evento exacto.
 *  - Traits usados (incluye los que se vayan agregando con la extraccion).
 *  - $dispatch / $this->dispatch(...) y emisiones a hijos via dispatchTo.
 *
 * Salida ordenada y deterministica en tests/snapshots/nueva-venta-contract.txt.
 *
 * Uso:
 *   php artisan tinker --execute="require 'tests/snapshots/generate-nueva-venta-contract.php';"
 *   o bien:
 *   php tests/snapshots/generate-nueva-venta-contract.php
 *
 * Si se corre standalone, levanta el bootstrap de Laravel primero.
 */

if (! class_exists(\Illuminate\Foundation\Application::class)) {
    require __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}

use App\Livewire\Ventas\NuevaVenta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

$class = NuevaVenta::class;
$ref = new ReflectionClass($class);

$out = [];
$out[] = '# Snapshot del contrato publico de '.$class;
$out[] = '# Generado: '.date('Y-m-d H:i:s');
$out[] = '# Archivo: '.str_replace('\\', '/', $ref->getFileName());
$out[] = '# Lineas: '.count(file($ref->getFileName()));
$out[] = '';

// -----------------------------------------------------------------
// TRAITS
// -----------------------------------------------------------------
$out[] = '## Traits';
$traits = [];
$cursor = $ref;
while ($cursor) {
    foreach ($cursor->getTraitNames() as $t) {
        $traits[$t] = true;
    }
    $cursor = $cursor->getParentClass();
}
ksort($traits);
foreach (array_keys($traits) as $t) {
    $out[] = '- '.$t;
}
$out[] = '';

// -----------------------------------------------------------------
// PROPIEDADES PUBLICAS
// -----------------------------------------------------------------
$out[] = '## Propiedades publicas';
$props = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
$propNames = [];
foreach ($props as $p) {
    if ($p->isStatic()) {
        continue;
    }
    $type = $p->hasType() ? ltrim((string) $p->getType(), '?').' ' : '';
    $nullable = $p->hasType() && $p->getType()->allowsNull() ? '?' : '';
    $default = '';
    if ($p->hasDefaultValue()) {
        $val = $p->getDefaultValue();
        $default = ' = '.var_export_compact($val);
    }
    $propNames[] = $p->getName();
    $out[] = '- '.$nullable.$type.'$'.$p->getName().$default;
}
sort($out);
$out[] = '';

// Re-armar manteniendo header
$header = [
    '# Snapshot del contrato publico de '.$class,
    '# Generado: '.date('Y-m-d H:i:s'),
    '# Archivo: '.str_replace('\\', '/', $ref->getFileName()),
    '# Lineas: '.count(file($ref->getFileName())),
    '',
    '## Traits',
];
foreach (array_keys($traits) as $t) {
    $header[] = '- '.$t;
}
$header[] = '';
$header[] = '## Propiedades publicas (count: '.count($propNames).')';

$lines = $header;
$propLines = [];
foreach ($props as $p) {
    if ($p->isStatic()) {
        continue;
    }
    $type = $p->hasType() ? ltrim((string) $p->getType(), '?').' ' : '';
    $nullable = $p->hasType() && $p->getType()->allowsNull() ? '?' : '';
    $default = '';
    if ($p->hasDefaultValue()) {
        $val = $p->getDefaultValue();
        $default = ' = '.var_export_compact($val);
    }
    $propLines[$p->getName()] = '- '.$nullable.$type.'$'.$p->getName().$default;
}
ksort($propLines);
foreach ($propLines as $l) {
    $lines[] = $l;
}
$lines[] = '';

// -----------------------------------------------------------------
// METODOS PUBLICOS
// -----------------------------------------------------------------
$methods = $ref->getMethods(ReflectionMethod::IS_PUBLIC);
$methodLines = [];
$computedLines = [];
$listenerLines = [];

foreach ($methods as $m) {
    if ($m->isStatic()) {
        continue;
    }
    if ($m->isConstructor() || $m->isDestructor()) {
        continue;
    }

    // Firma legible: nombre(tipo $arg = default, ...): retorno
    $params = [];
    foreach ($m->getParameters() as $p) {
        $pType = $p->hasType() ? (string) $p->getType().' ' : '';
        $variadic = $p->isVariadic() ? '...' : '';
        $byRef = $p->isPassedByReference() ? '&' : '';
        $def = '';
        if ($p->isDefaultValueAvailable()) {
            $def = ' = '.var_export_compact($p->getDefaultValue());
        } elseif ($p->isOptional()) {
            $def = ' = ?';
        }
        $params[] = $pType.$byRef.$variadic.'$'.$p->getName().$def;
    }
    $ret = $m->hasReturnType() ? ': '.(string) $m->getReturnType() : '';
    $signature = $m->getName().'('.implode(', ', $params).')'.$ret;

    // Atributos: Computed, On
    $isComputed = false;
    $computedOpts = '';
    $events = [];

    foreach ($m->getAttributes() as $attr) {
        $name = $attr->getName();
        if ($name === Computed::class || str_ends_with($name, '\\Computed')) {
            $isComputed = true;
            try {
                $args = $attr->getArguments();
                if (! empty($args)) {
                    $parts = [];
                    foreach ($args as $k => $v) {
                        $parts[] = (is_string($k) ? $k.': ' : '').var_export_compact($v);
                    }
                    $computedOpts = '('.implode(', ', $parts).')';
                }
            } catch (\Throwable $e) {
                $computedOpts = '(?)';
            }
        }
        if ($name === On::class || str_ends_with($name, '\\On')) {
            try {
                $args = $attr->getArguments();
                $event = $args[0] ?? '?';
                $events[] = is_string($event) ? $event : json_encode($event);
            } catch (\Throwable $e) {
                $events[] = '?';
            }
        }
    }

    if ($isComputed) {
        $computedLines[$m->getName()] = '- '.$signature.($computedOpts ? '  '.$computedOpts : '');
    }
    foreach ($events as $ev) {
        $listenerLines[] = '- '.$ev.' -> '.$m->getName().'(...)';
    }

    // Metodo "publico normal": no es computed (los computed se acceden como props),
    // pero siempre lo listamos en metodos para mantener la firma completa.
    $methodLines[$m->getName()] = '- '.$signature;
}

ksort($methodLines);
ksort($computedLines);
sort($listenerLines);

$lines[] = '## Computed properties (count: '.count($computedLines).')';
foreach ($computedLines as $l) {
    $lines[] = $l;
}
$lines[] = '';

$lines[] = '## Listeners #[On(...)] (count: '.count($listenerLines).')';
foreach ($listenerLines as $l) {
    $lines[] = $l;
}
$lines[] = '';

$lines[] = '## Metodos publicos (count: '.count($methodLines).')';
foreach ($methodLines as $l) {
    $lines[] = $l;
}
$lines[] = '';

// -----------------------------------------------------------------
// DISPATCHES (heuristica textual sobre el archivo fuente y traits)
// -----------------------------------------------------------------
$src = file_get_contents($ref->getFileName());
$dispatches = [];

// $this->dispatch('evento', ...) y this->dispatch("evento", ...)
preg_match_all('/->dispatch\(\s*([\'"])([a-zA-Z0-9_\-:.]+)\1/', $src, $mm);
foreach ($mm[2] ?? [] as $ev) {
    $dispatches[$ev] = true;
}
// $this->dispatchTo('Comp', 'evento', ...)
preg_match_all('/->dispatchTo\(\s*[\'"][^\'"]+[\'"]\s*,\s*[\'"]([a-zA-Z0-9_\-:.]+)[\'"]/', $src, $mm2);
foreach ($mm2[1] ?? [] as $ev) {
    $dispatches[$ev.' (dispatchTo)'] = true;
}

ksort($dispatches);
$lines[] = '## Eventos despachados (heuristica) (count: '.count($dispatches).')';
foreach (array_keys($dispatches) as $d) {
    $lines[] = '- '.$d;
}
$lines[] = '';

// -----------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------
function var_export_compact($v): string
{
    if (is_array($v) && empty($v)) {
        return '[]';
    }
    if (is_array($v)) {
        // Compactamos a una linea legible
        return preg_replace('/\s+/', ' ', var_export($v, true));
    }
    if (is_null($v)) {
        return 'null';
    }
    if (is_bool($v)) {
        return $v ? 'true' : 'false';
    }
    if (is_string($v)) {
        return "'".addslashes($v)."'";
    }

    return (string) $v;
}

$dest = __DIR__.'/nueva-venta-contract.txt';
file_put_contents($dest, implode("\n", $lines)."\n");

echo 'Snapshot generado: '.$dest."\n";
echo '  Traits: '.count($traits)."\n";
echo '  Propiedades publicas: '.count($propLines)."\n";
echo '  Computed properties: '.count($computedLines)."\n";
echo '  Listeners On: '.count($listenerLines)."\n";
echo '  Metodos publicos: '.count($methodLines)."\n";
echo '  Dispatches detectados: '.count($dispatches)."\n";
