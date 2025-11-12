# Sistema de Gestión de Cajas

## Índice
1. [Descripción General](#descripción-general)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Estructura de Base de Datos](#estructura-de-base-de-datos)
4. [Componentes del Sistema](#componentes-del-sistema)
5. [Flujo de Eventos](#flujo-de-eventos)
6. [Cómo Usar en Componentes](#cómo-usar-en-componentes)
7. [Gestión de Permisos](#gestión-de-permisos)
8. [Ejemplos de Uso](#ejemplos-de-uso)
9. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## Descripción General

El **Sistema de Cajas** es un módulo opcional que permite gestionar múltiples cajas registradoras por sucursal. A diferencia del sistema de sucursales (que es obligatorio para todos los componentes), **las cajas solo se requieren en componentes específicos** como Ventas, Movimientos de Caja, Compras y Cierre de Caja.

### Características principales:

- ✅ **Multi-caja por sucursal**: Cada sucursal puede tener múltiples cajas
- ✅ **Permisos granulares**: Cada usuario puede tener acceso a cajas específicas por sucursal
- ✅ **Selector flotante**: Botón flotante en componentes que requieren caja
- ✅ **Cambio sin recarga**: Los datos se actualizan automáticamente al cambiar de caja
- ✅ **Auto-selección inteligente**: Selecciona automáticamente la primera caja disponible
- ✅ **Reacción a cambios de sucursal**: Al cambiar de sucursal, se selecciona la primera caja de la nueva sucursal
- ✅ **Sistema de caché optimizado**: Caché de 3 niveles para máximo rendimiento

---

## Arquitectura del Sistema

### Diagrama de Componentes

```
┌─────────────────────────────────────────────────────────────┐
│                    CAPA DE PRESENTACIÓN                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────────┐         ┌──────────────────┐        │
│  │  CajaSelector    │         │  Componentes     │        │
│  │  (Botón Flotante)│◄────────┤  con CajaAware   │        │
│  └────────┬─────────┘         └────────┬─────────┘        │
│           │                            │                   │
│           │ cambiarCaja()              │ handleCajaChanged()│
│           ▼                            ▼                   │
└───────────┼────────────────────────────┼───────────────────┘
            │                            │
            │                            │
┌───────────┼────────────────────────────┼───────────────────┐
│           │      CAPA DE SERVICIOS     │                   │
├───────────┼────────────────────────────┼───────────────────┤
│           │                            │                   │
│           ▼                            │                   │
│  ┌────────────────────────────────────┐│                  │
│  │         CajaService                ││                  │
│  ├────────────────────────────────────┤│                  │
│  │ • getCajasDisponibles()            ││                  │
│  │ • getCajaActiva()                  ││                  │
│  │ • establecerCajaActiva()           ││                  │
│  │ • establecerPrimeraCajaDisponible()││                  │
│  │ • clearCache()                     ││                  │
│  └────────────────────────────────────┘│                  │
│           │                            │                   │
│           │                            │                   │
│           ▼                            │                   │
│  ┌────────────────────────────────────┐│                  │
│  │      Sistema de Caché 3 Niveles    ││                  │
│  ├────────────────────────────────────┤│                  │
│  │ 1. $cajasCache (Collection)        ││                  │
│  │ 2. $cajaIdsCache (array IDs)       ││                  │
│  │ 3. $cajaActivaCache (Model)        ││                  │
│  └────────────────────────────────────┘│                  │
│                                        │                   │
└────────────────────────────────────────┼───────────────────┘
                                         │
                                         │
┌────────────────────────────────────────┼───────────────────┐
│             CAPA DE PERSISTENCIA       │                   │
├────────────────────────────────────────┼───────────────────┤
│                                        ▼                   │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   cajas     │  │ user_cajas   │  │  sesiones    │     │
│  ├─────────────┤  ├──────────────┤  ├──────────────┤     │
│  │ • id        │  │ • user_id    │  │ caja_activa  │     │
│  │ • nombre    │  │ • caja_id    │  │              │     │
│  │ • tipo      │  │ • sucursal_id│  └──────────────┘     │
│  │ • estado    │  └──────────────┘                        │
│  └─────────────┘                                          │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

### Middleware Flow

```
Request
   │
   ▼
ConfigureTenantMiddleware
   │
   ├─► autoSeleccionarSucursal()
   │
   └─► autoSeleccionarCaja()
        │
        ├─► ¿Usuario autenticado? ─NO─► Skip
        │   │
        │   YES
        │   ▼
        ├─► ¿Sucursal activa? ─NO─► Skip
        │   │
        │   YES
        │   ▼
        ├─► ¿Caja actual válida? ─YES─► Continue
        │   │
        │   NO
        │   ▼
        └─► establecerPrimeraCajaDisponible()
             │
             ▼
          Continue to Route
```

---

## Estructura de Base de Datos

### Tabla: `{comercio_id}_cajas`

Almacena las cajas registradoras de cada sucursal.

```sql
CREATE TABLE `000001_cajas` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `sucursal_id` bigint UNSIGNED NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `tipo` enum('principal','secundaria') DEFAULT 'principal',
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `estado` enum('abierta','cerrada') DEFAULT 'cerrada',
  `fecha_apertura` timestamp NULL DEFAULT NULL,
  `fecha_cierre` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cajas_sucursal_id_foreign` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Campos importantes:**
- `estado`: Estado actual de la caja (abierta/cerrada)
- `fecha_apertura`: Timestamp de última apertura
- `fecha_cierre`: Timestamp de último cierre
- `tipo`: Tipo de caja (principal/secundaria)

### Tabla: `{comercio_id}_user_cajas`

Define qué cajas puede usar cada usuario en cada sucursal.

```sql
CREATE TABLE `000001_user_cajas` (
  `user_id` bigint UNSIGNED NOT NULL,      -- Usuario de config.users
  `caja_id` bigint UNSIGNED NOT NULL,      -- Caja del comercio
  `sucursal_id` bigint UNSIGNED NOT NULL,  -- Sucursal (redundante pero útil)
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  UNIQUE KEY `uk_user_caja` (`user_id`,`caja_id`),
  KEY `user_cajas_caja_id_foreign` (`caja_id`),
  KEY `user_cajas_sucursal_id_foreign` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Notas:**
- `sucursal_id` es redundante pero optimiza consultas
- La clave única `uk_user_caja` evita duplicados
- Si un usuario **NO tiene registros** en esta tabla para una sucursal, tiene acceso a **TODAS las cajas** de esa sucursal

### Session Storage

```php
session()->put('caja_activa', $cajaId); // Almacena ID de caja activa
```

---

## Componentes del Sistema

### 1. CajaService (`app/Services/CajaService.php`)

Servicio centralizado para gestión de cajas con sistema de caché.

#### Métodos principales:

```php
// Obtener cajas disponibles para el usuario en la sucursal actual
public static function getCajasDisponibles(): Collection

// Obtener ID de la caja activa
public static function getCajaActiva(): ?int

// Obtener modelo de la caja activa
public static function getCajaActivaModel(): ?Caja

// Establecer una caja como activa
public static function establecerCajaActiva(int $cajaId): bool

// Auto-seleccionar primera caja disponible
public static function establecerPrimeraCajaDisponible(): ?int

// Verificar si el usuario tiene acceso a una caja
public static function tieneAccesoACaja(int $cajaId): bool

// Limpiar caché (se llama al cambiar sucursal o modificar permisos)
public static function clearCache(): void
```

#### Sistema de caché (3 niveles):

```php
protected static ?Collection $cajasCache = null;        // Todas las cajas disponibles
protected static ?array $cajaIdsCache = null;           // Solo IDs (más ligero)
protected static ?Caja $cajaActivaCache = null;         // Modelo completo de la activa
```

### 2. CajaSelector Component (`app/Livewire/CajaSelector.php`)

Componente Livewire que renderiza el botón flotante de selección de caja.

#### Propiedades:

```php
public $cajaActual;           // Modelo de caja actual
public $cajasDisponibles;     // Collection de cajas disponibles
public $mostrarDropdown;      // Estado del dropdown
```

#### Métodos:

```php
public function cambiarCaja($cajaId)
{
    // 1. Establece la caja activa
    CajaService::establecerCajaActiva($cajaId);

    // 2. Emite evento global
    $this->dispatch('caja-changed', cajaId: $caja->id, cajaNombre: $caja->nombre);

    // 3. Notifica al usuario
    $this->dispatch('notify', message: "Cambiado a caja: {$caja->nombre}", type: 'success');
}
```

#### Eventos que escucha:

- `sucursal-changed`: Recarga cajas cuando cambia la sucursal
- `sucursal-cambiada`: Alias del evento anterior

### 3. CajaAware Trait (`app/Traits/CajaAware.php`)

Trait reutilizable para componentes que necesitan reaccionar al cambio de caja.

#### Uso básico:

```php
use App\Traits\CajaAware;

class MiComponente extends Component
{
    use CajaAware;

    // El componente automáticamente escuchará 'caja-changed'
}
```

#### Métodos que proporciona:

```php
protected function cajaActual(): ?int
protected function tieneAccesoACaja(int $cajaId): bool
protected function cajasDisponibles(): Collection
protected function cajaActivaModel(): ?Caja
```

#### Hook personalizado:

```php
// Implementa este método en tu componente si necesitas lógica personalizada
protected function onCajaChanged($cajaId, $cajaNombre)
{
    // Tu lógica aquí
    // Ejemplo: Limpiar carrito si está en POS
    if ($this->showPosModal && !empty($this->carrito)) {
        $this->resetPOS();
    }
}
```

#### Comportamiento automático al cambiar caja:

1. ✅ Resetea paginación (si usa `WithPagination`)
2. ✅ Cierra modales comunes automáticamente
3. ✅ Ejecuta hook `onCajaChanged()` si existe

### 4. Helper Functions (`app/Helpers/helpers.php`)

Funciones globales para acceso rápido:

```php
// Obtener ID de la caja activa
caja_activa(): ?int

// Obtener modelo completo de la caja activa
caja_activa_model(): ?Caja

// Verificar si el usuario tiene acceso a una caja
tiene_acceso_caja(int $cajaId): bool
```

---

## Flujo de Eventos

### Evento: `caja-changed`

Este evento se emite cuando el usuario cambia de caja manualmente.

#### Emisión:

```php
// Desde CajaSelector
$this->dispatch('caja-changed', cajaId: $cajaId, cajaNombre: $cajaNombre);
```

#### Escucha (automática con CajaAware):

```php
class Ventas extends Component
{
    use CajaAware;  // Automáticamente escucha 'caja-changed'

    protected function onCajaChanged($cajaId, $cajaNombre)
    {
        // Lógica personalizada
    }
}
```

### Flujo completo al cambiar de caja:

```
Usuario hace clic en otra caja
         │
         ▼
CajaSelector::cambiarCaja($cajaId)
         │
         ├─► CajaService::establecerCajaActiva($cajaId)
         │   └─► session()->put('caja_activa', $cajaId)
         │
         ├─► $this->dispatch('caja-changed', ...)
         │
         └─► $this->dispatch('notify', ...)
                  │
                  ▼
         Componentes con CajaAware escuchan
                  │
                  ├─► handleCajaChanged()
                  │   ├─► resetPage() [si usa paginación]
                  │   ├─► Cierra modales
                  │   └─► onCajaChanged() [hook personalizado]
                  │
                  ▼
         Componentes se re-renderizan con nueva caja
```

### Flujo al cambiar de sucursal:

```
Usuario cambia de sucursal
         │
         ▼
SucursalSelector::cambiarSucursal($sucursalId)
         │
         ├─► SucursalService::establecerSucursalActiva($sucursalId)
         │
         ├─► CajaService::clearCache()  ◄── IMPORTANTE
         │
         ├─► CajaService::establecerPrimeraCajaDisponible()
         │   └─► Selecciona 1ra caja de nueva sucursal
         │
         ├─► $this->dispatch('sucursal-changed', ...)
         │
         └─► $this->dispatch('caja-changed', ...)  ◄── Notifica cambio de caja
                  │
                  ▼
         Componentes reaccionan a ambos eventos
```

---

## Cómo Usar en Componentes

### ¿Cuándo usar CajaAware?

**SÍ usarlo en:**
- ✅ Ventas (POS)
- ✅ Movimientos de Caja
- ✅ Compras con pago en efectivo
- ✅ Cierre de Caja
- ✅ Cualquier operación que registre transacciones en caja

**NO usarlo en:**
- ❌ Stock (no necesita caja)
- ❌ Artículos (no necesita caja)
- ❌ Configuración general
- ❌ Reportes globales

### Ejemplo 1: Componente básico con CajaAware

```php
<?php

namespace App\Livewire\Ventas;

use Livewire\Component;
use App\Traits\CajaAware;

class Ventas extends Component
{
    use CajaAware;

    public function procesarVenta()
    {
        // Obtener caja actual
        $cajaId = $this->cajaActual();

        if (!$cajaId) {
            $this->dispatch('notify',
                message: 'No hay caja activa',
                type: 'error'
            );
            return;
        }

        // Procesar venta con la caja actual
        Venta::create([
            'caja_id' => $cajaId,
            'sucursal_id' => sucursal_activa(),
            // ... resto de campos
        ]);
    }

    // Hook personalizado (opcional)
    protected function onCajaChanged($cajaId, $cajaNombre)
    {
        // Resetear carrito si está en POS
        if ($this->showPosModal && !empty($this->carrito)) {
            $this->resetPOS();
            $this->dispatch('notify',
                message: 'Carrito limpiado al cambiar de caja',
                type: 'warning'
            );
        }
    }

    public function render()
    {
        return view('livewire.ventas.ventas');
    }
}
```

### Ejemplo 2: Blade view con CajaSelector

```blade
<div>
    <!-- Contenido del componente -->

    <div class="ventas-container">
        <!-- Tablas, formularios, etc -->
    </div>

    <!-- Selector de Caja Flotante al final -->
    <livewire:caja-selector />
</div>
```

### Ejemplo 3: Validar caja antes de operaciones críticas

```php
public function abrirCaja()
{
    $cajaId = $this->cajaActual();

    if (!$cajaId) {
        $this->dispatch('notify',
            message: 'No tienes una caja seleccionada',
            type: 'error'
        );
        return;
    }

    $caja = $this->cajaActivaModel();

    if ($caja->estado === 'abierta') {
        $this->dispatch('notify',
            message: 'La caja ya está abierta',
            type: 'warning'
        );
        return;
    }

    // Abrir caja
    $caja->update([
        'estado' => 'abierta',
        'fecha_apertura' => now(),
    ]);

    $this->dispatch('notify',
        message: 'Caja abierta exitosamente',
        type: 'success'
    );
}
```

### Ejemplo 4: Reaccionar con lógica compleja

```php
protected function onCajaChanged($cajaId, $cajaNombre)
{
    // 1. Verificar si hay operaciones pendientes
    if ($this->tieneOperacionesPendientes()) {
        $this->dispatch('notify',
            message: 'Advertencia: Tenías operaciones sin guardar',
            type: 'warning'
        );
        $this->limpiarOperacionesPendientes();
    }

    // 2. Recargar datos específicos de la nueva caja
    $this->cargarMovimientosDeLaCaja($cajaId);

    // 3. Log del cambio
    activity()
        ->causedBy(auth()->user())
        ->withProperties(['caja_nueva' => $cajaId])
        ->log('Cambió a caja: ' . $cajaNombre);
}
```

---

## Gestión de Permisos

### Asignar cajas a usuarios (Super Admin)

Los Super Administradores pueden asignar cajas a usuarios desde **Configuración > Usuarios**.

#### Flujo UI:

1. Editar usuario
2. Seleccionar sucursales con acceso
3. Para cada sucursal seleccionada, aparecen las cajas disponibles
4. Marcar las cajas a las que tendrá acceso
5. Guardar

#### Backend (Usuarios.php):

```php
public function save()
{
    // ... validación y guardado de usuario ...

    // Asignar cajas por sucursal
    if ($this->currentUserIsSuperAdmin && !empty($this->selectedSucursales)) {
        // Eliminar asignaciones anteriores
        DB::connection('pymes_tenant')
            ->table('user_cajas')
            ->where('user_id', $user->id)
            ->delete();

        // Insertar nuevas asignaciones
        foreach ($this->selectedSucursales as $sucursalId) {
            if (isset($this->selectedCajas[$sucursalId]) &&
                !empty($this->selectedCajas[$sucursalId])) {

                foreach ($this->selectedCajas[$sucursalId] as $cajaId) {
                    DB::connection('pymes_tenant')
                        ->table('user_cajas')
                        ->insert([
                            'user_id' => $user->id,
                            'caja_id' => $cajaId,
                            'sucursal_id' => $sucursalId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    // Limpiar caché si se modificó el usuario autenticado
    if ($user->id === auth()->id()) {
        CajaService::clearCache();
    }
}
```

### Lógica de permisos

```php
public static function getCajasDisponibles(): Collection
{
    // 1. Obtener sucursal actual
    $sucursalId = SucursalService::getSucursalActiva();

    // 2. Verificar si el usuario tiene restricciones específicas
    $cajaIdsPermitidas = DB::connection('pymes_tenant')
        ->table('user_cajas')
        ->where('user_id', auth()->id())
        ->where('sucursal_id', $sucursalId)
        ->pluck('caja_id')
        ->toArray();

    // 3. Construir query
    $query = Caja::where('sucursal_id', $sucursalId)
                 ->where('activo', true);

    // 4. Si tiene restricciones, filtrar por ellas
    if (!empty($cajaIdsPermitidas)) {
        $query->whereIn('id', $cajaIdsPermitidas);
    }
    // Si NO tiene registros, tiene acceso a TODAS

    return $query->orderBy('id', 'asc')->get();
}
```

---

## Ejemplos de Uso

### Ejemplo completo: Componente de Movimientos de Caja

**Backend: `app/Livewire/Cajas/MovimientosCaja.php`**

```php
<?php

namespace App\Livewire\Cajas;

use Livewire\Component;
use Livewire\WithPagination;
use App\Traits\CajaAware;
use App\Models\MovimientoCaja;

class MovimientosCaja extends Component
{
    use WithPagination, CajaAware;

    public $showModal = false;
    public $tipo;
    public $monto;
    public $descripcion;

    public function mount()
    {
        // Verificar que hay caja activa
        if (!$this->cajaActual()) {
            $this->dispatch('notify',
                message: 'No tienes una caja asignada',
                type: 'error'
            );
        }
    }

    public function registrarMovimiento()
    {
        $this->validate([
            'tipo' => 'required|in:ingreso,egreso',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:255',
        ]);

        $cajaId = $this->cajaActual();

        if (!$cajaId) {
            $this->dispatch('notify',
                message: 'No hay caja activa',
                type: 'error'
            );
            return;
        }

        MovimientoCaja::create([
            'caja_id' => $cajaId,
            'sucursal_id' => sucursal_activa(),
            'tipo' => $this->tipo,
            'monto' => $this->monto,
            'descripcion' => $this->descripcion,
            'usuario_id' => auth()->id(),
        ]);

        $this->reset(['tipo', 'monto', 'descripcion']);
        $this->showModal = false;

        $this->dispatch('notify',
            message: 'Movimiento registrado',
            type: 'success'
        );
    }

    protected function onCajaChanged($cajaId, $cajaNombre)
    {
        // Cerrar modal si está abierto
        $this->showModal = false;

        // Notificar al usuario
        $this->dispatch('notify',
            message: "Mostrando movimientos de: {$cajaNombre}",
            type: 'info'
        );
    }

    public function render()
    {
        $movimientos = MovimientoCaja::where('caja_id', $this->cajaActual())
            ->latest()
            ->paginate(20);

        return view('livewire.cajas.movimientos-caja', [
            'movimientos' => $movimientos,
        ]);
    }
}
```

**Frontend: `resources/views/livewire/cajas/movimientos-caja.blade.php`**

```blade
<div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="mb-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold text-gray-900">Movimientos de Caja</h2>
            <button
                wire:click="$set('showModal', true)"
                class="px-4 py-2 bg-indigo-600 text-white rounded-md"
            >
                Nuevo Movimiento
            </button>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movimientos as $movimiento)
                        <tr>
                            <td>{{ $movimiento->created_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <span class="px-2 py-1 rounded text-xs
                                    {{ $movimiento->tipo === 'ingreso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ucfirst($movimiento->tipo) }}
                                </span>
                            </td>
                            <td>${{ number_format($movimiento->monto, 2) }}</td>
                            <td>{{ $movimiento->descripcion }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-6 text-gray-500">
                                No hay movimientos en esta caja
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-6 py-4">
                {{ $movimientos->links() }}
            </div>
        </div>
    </div>

    <!-- Modal para nuevo movimiento -->
    @if($showModal)
        <!-- Modal content aquí -->
    @endif

    <!-- Selector de Caja Flotante -->
    <livewire:caja-selector />
</div>
```

---

## Preguntas Frecuentes

### ¿Qué pasa si un usuario no tiene cajas asignadas?

Si un usuario **NO tiene registros en `user_cajas`** para una sucursal, tiene acceso a **TODAS las cajas activas** de esa sucursal.

### ¿Qué pasa si cambio de sucursal?

Al cambiar de sucursal:
1. El caché de cajas se limpia automáticamente
2. Se auto-selecciona la primera caja disponible de la nueva sucursal
3. Se emite el evento `caja-changed`
4. Todos los componentes con `CajaAware` se actualizan

### ¿Cómo saber si un componente necesita CajaAware?

**Regla simple**: Si el componente **crea, lee o modifica datos que pertenecen a una caja específica** (ventas, movimientos, etc.), debe usar `CajaAware`.

### ¿Puedo usar CajaSelector en múltiples componentes de la misma vista?

Sí, pero **no es necesario**. El `CajaSelector` es un componente global que afecta la sesión del usuario. Basta con incluirlo **una vez** en cada vista que lo necesite.

### ¿Cómo evito que el usuario cambie de caja en medio de una operación?

```php
protected function onCajaChanged($cajaId, $cajaNombre)
{
    if ($this->operacionEnProceso) {
        $this->dispatch('notify',
            message: 'No puedes cambiar de caja durante una venta',
            type: 'error'
        );

        // Opcional: Deshacer el cambio (requiere lógica adicional)
        return;
    }
}
```

### ¿Cómo manejar componentes que pueden o no usar caja?

Usa el helper con verificación:

```php
public function procesarCompra()
{
    $data = [
        'proveedor_id' => $this->proveedorId,
        'sucursal_id' => sucursal_activa(),
        'total' => $this->total,
    ];

    // Solo agregar caja si hay una activa
    if ($cajaId = caja_activa()) {
        $data['caja_id'] = $cajaId;
    }

    Compra::create($data);
}
```

### ¿Cómo cachear datos por caja en el frontend?

Usa Alpine.js con wire:key:

```blade
<div wire:key="caja-{{ $cajaActual }}">
    <!-- Este contenido se re-renderiza al cambiar caja -->
    @foreach($movimientos as $movimiento)
        <!-- ... -->
    @endforeach
</div>
```

### ¿Debo limpiar el caché manualmente?

**NO** en la mayoría de casos. El caché se limpia automáticamente:
- Al cambiar de sucursal
- Al modificar permisos de usuario
- Al final de cada request (es caché de request, no persistente)

Solo llama `CajaService::clearCache()` si haces cambios estructurales a las cajas (crear, eliminar, desactivar).

---

## Resumen de Archivos Importantes

| Archivo | Propósito |
|---------|-----------|
| `app/Services/CajaService.php` | Servicio centralizado con caché |
| `app/Livewire/CajaSelector.php` | Botón flotante de selección |
| `app/Traits/CajaAware.php` | Trait reutilizable para componentes |
| `app/Helpers/helpers.php` | Funciones globales: `caja_activa()`, etc |
| `app/Http/Middleware/ConfigureTenantMiddleware.php` | Auto-selección en cada request |
| `database/migrations/*_add_estado_fields_to_cajas_table.php` | Campos estado/fechas |
| `database/migrations/*_create_user_cajas_table.php` | Permisos de cajas |
| `resources/views/livewire/caja-selector.blade.php` | UI del botón flotante |

---

## Próximos Pasos Recomendados

1. ✅ Ejecutar las migraciones
2. ✅ Crear cajas de prueba en cada sucursal
3. ✅ Asignar permisos de caja a usuarios
4. ✅ Probar cambio de caja sin recarga
5. ✅ Implementar `CajaAware` en componentes restantes (Compras, Cierre de Caja)
6. ✅ Documentar procedimientos operativos para usuarios finales

---

**Documentación creada:** 2025-11-10
**Versión del sistema:** 1.0.0
**Autor:** BCN Pymes Development Team
