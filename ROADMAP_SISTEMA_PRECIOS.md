# 🗺️ ROADMAP - Sistema de Precios Dinámico

**Fecha de creación:** 2025-11-17
**Estado actual:** Fase 1 COMPLETADA ✅
**Próxima fase:** Integración con POS y UI de Administración

---

## 📋 Índice

1. [Resumen de lo Completado](#resumen-de-lo-completado)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Estado Actual de Archivos](#estado-actual-de-archivos)
4. [Próximos Pasos Detallados](#próximos-pasos-detallados)
5. [Integración con POS (NuevaVenta)](#integración-con-pos-nuevaventa)
6. [UI de Administración](#ui-de-administración)
7. [Datos de Prueba Disponibles](#datos-de-prueba-disponibles)
8. [Notas Importantes](#notas-importantes)

---

## ✅ Resumen de lo Completado

### FASE 1: Sistema de Precios Dinámico - COMPLETADA

**Lo que SE HIZO:**

#### 1. Base de Datos (11 Tablas Nuevas)

✅ **Categorías:**
- `000001_categorias` - 10 categorías con colores

✅ **Formas y Canales de Venta:**
- `000001_formas_venta` - 5 formas (Local, Delivery, Take Away, Mayorista, Online)
- `000001_canales_venta` - 8 canales (POS, Salón, Web, WhatsApp, etc.)

✅ **Formas de Pago:**
- `000001_formas_pago` - 8 formas de pago
- `000001_formas_pago_sucursales` - 24 configuraciones por sucursal
- `000001_formas_pago_cuotas` - 6 planes de cuotas

✅ **Precios y Promociones:**
- `000001_precios_base` - 40+ precios con 4 niveles de especificidad
- `000001_promociones` - 10 promociones variadas
- `000001_promociones_condiciones` - Condiciones de aplicación
- `000001_promociones_escalas` - Descuentos escalonados

✅ **Backup:**
- `000001_precios_old` - Tabla antigua preservada

#### 2. Modelos Eloquent (11 Archivos)

Ubicación: `app/Models/`

✅ Categoria.php
✅ FormaVenta.php
✅ CanalVenta.php
✅ FormaPago.php
✅ FormaPagoSucursal.php
✅ FormaPagoCuota.php
✅ PrecioBase.php ⭐ (con jerarquía de especificidad)
✅ Promocion.php ⭐ (con validaciones temporales)
✅ PromocionCondicion.php
✅ PromocionEscala.php
✅ Articulo.php (actualizado con nuevas relaciones)

**Todos con:**
- PHPDoc completo
- Relaciones definidas
- Scopes útiles
- Métodos auxiliares
- Documentación exhaustiva

#### 3. Seeders (8 Archivos)

Ubicación: `database/seeders/`

✅ CategoriasSeeder.php
✅ FormasVentaSeeder.php
✅ CanalesVentaSeeder.php
✅ FormasPagoSeeder.php
✅ FormasPagoSucursalesSeeder.php
✅ PreciosBaseSeeder.php
✅ PromocionesSeeder.php
✅ SistemaPreciosDinamicoSeeder.php (maestro)

**Ejecutados exitosamente con datos de ejemplo**

#### 4. Servicio de Precios (1 Archivo)

Ubicación: `app/Services/PrecioService.php` (19 KB)

✅ **Métodos principales:**
- `obtenerPrecioBase()` - Jerarquía de especificidad
- `calcularPrecioFinal()` - Cálculo completo con todas las reglas
- `calcularCarrito()` - Múltiples artículos

✅ **Características:**
- Promociones con prioridades
- Validaciones temporales (fecha, día, hora)
- Descuentos escalonados
- Límite 70% automático
- Cálculo de IVA
- Recargos por cuotas
- Completamente documentado

#### 5. Documentación (2 Archivos)

✅ `PRECIO_SERVICE_GUIA.md` (13 KB) - Guía completa de uso
✅ `ROADMAP_SISTEMA_PRECIOS.md` (este archivo)

---

## 🏗️ Arquitectura del Sistema

### Jerarquía de Especificidad de Precios

```
Nivel 4 (MÁS ESPECÍFICO): Forma de Venta + Canal de Venta
         ↓ (si no existe)
Nivel 3: Solo Forma de Venta
         ↓ (si no existe)
Nivel 2: Solo Canal de Venta
         ↓ (si no existe)
Nivel 1 (GENÉRICO): Sin forma ni canal
```

**Ejemplo con Coca Cola (Casa Central):**
- Delivery + WhatsApp → **$420** ⭐ (se usa este)
- Solo Delivery → $400
- Solo Web → $380
- Genérico → $350

### Flujo de Cálculo de Precios

```
1. PRECIO BASE (según especificidad)
         ↓
2. PROMOCIONES (por prioridad)
   - Valida condiciones
   - Valida vigencias temporales
   - Aplica según combinabilidad
         ↓
3. LÍMITE 70% (automático)
         ↓
4. CÁLCULO IVA
         ↓
5. AJUSTES FORMA DE PAGO
   - Recargos por cuotas
         ↓
6. PRECIO FINAL
```

### Sistema de Promociones

**Tipos de Promoción:**
1. `descuento_porcentaje` - Ej: 20% OFF
2. `descuento_monto` - Ej: $100 OFF
3. `precio_fijo` - Ej: $300
4. `recargo_porcentaje` - Ej: +10%
5. `recargo_monto` - Ej: +$50
6. `descuento_escalonado` - Descuentos por cantidad

**Tipos de Condición:**
1. `por_articulo` - Artículo específico
2. `por_categoria` - Categoría completa
3. `por_forma_pago` - Forma de pago específica
4. `por_forma_venta` - Forma de venta específica
5. `por_canal` - Canal específico
6. `por_cantidad` - Cantidad mínima
7. `por_total_compra` - Monto mínimo

**Prioridades y Combinabilidad:**
```
Prioridad 1 (mayor) → se aplica primero
Prioridad 999 (menor) → se aplica al final

Si combinable = true → puede sumarse con otras
Si combinable = false → es excluyente (no permite otras)
```

---

## 📁 Estado Actual de Archivos

### Migraciones Ejecutadas ✅

Todas las migraciones están en la tabla `000001_migrations` del comercio 1.

**Para verificar:**
```bash
php artisan migrate:status --database=pymes_tenant
```

### Seeders Ejecutados ✅

Todos los datos están cargados en las tablas con prefijo `000001_`.

**Para verificar:**
```bash
php artisan db:seed --class=SistemaPreciosDinamicoSeeder
# Mostrará "ya existe" en todo porque ya se ejecutó
```

### Archivos NO Modificados (Están Intactos)

❌ `app/Livewire/Ventas/NuevaVenta.php` - **NO SE TOCÓ**
❌ Vistas de Livewire - **NO SE TOCARON**
❌ Rutas - **NO SE TOCARON**

**Importante:** El POS actual sigue funcionando con el sistema viejo de precios.

---

## 🎯 Próximos Pasos Detallados

### FASE 2: Integración con POS (Prioridad Alta)

**Objetivo:** Modificar el componente NuevaVenta.php para usar el nuevo PrecioService.

**Archivos a modificar:**
1. `app/Livewire/Ventas/NuevaVenta.php` ⚠️
2. `resources/views/livewire/ventas/nueva-venta.blade.php` ⚠️

**Pasos:**

#### Paso 1: Backup del archivo actual
```bash
cp app/Livewire/Ventas/NuevaVenta.php app/Livewire/Ventas/NuevaVenta.php.backup
cp resources/views/livewire/ventas/nueva-venta.blade.php resources/views/livewire/ventas/nueva-venta.blade.php.backup
```

#### Paso 2: Agregar propiedades al componente NuevaVenta

**Ubicación:** `app/Livewire/Ventas/NuevaVenta.php`

**Agregar en la clase:**
```php
use App\Services\PrecioService;

class NuevaVenta extends Component
{
    // Nuevas propiedades para el sistema de precios
    public $formaVentaSeleccionada = null;
    public $canalVentaSeleccionado = null;
    public $formaPagoSeleccionada = null;
    public $cuotasSeleccionadas = null;

    // Colecciones para los selectores
    public $formasVenta = [];
    public $canalesVenta = [];
    public $formasPago = [];
    public $planesCuotas = [];

    protected PrecioService $precioService;

    public function boot(PrecioService $precioService)
    {
        $this->precioService = $precioService;
    }

    public function mount()
    {
        // ... código existente ...

        // Cargar formas de venta activas
        $this->formasVenta = \App\Models\FormaVenta::activas()->ordenado()->get();

        // Cargar canales de venta activos
        $this->canalesVenta = \App\Models\CanalVenta::activos()->ordenado()->get();

        // Cargar formas de pago habilitadas en esta sucursal
        $this->cargarFormasPago();
    }

    public function cargarFormasPago()
    {
        $sucursalId = $this->obtenerSucursalActual();

        $this->formasPago = \App\Models\FormaPago::activas()
            ->whereHas('sucursales', function($q) use ($sucursalId) {
                $q->where('sucursal_id', $sucursalId)
                  ->where('formas_pago_sucursales.activo', true);
            })
            ->get();
    }

    public function updatedFormaPagoSeleccionada($value)
    {
        // Cargar planes de cuotas si la forma de pago lo permite
        if ($value) {
            $formaPago = \App\Models\FormaPago::find($value);
            if ($formaPago && $formaPago->permite_cuotas) {
                $this->planesCuotas = $formaPago->obtenerCuotasDisponibles();
            } else {
                $this->planesCuotas = [];
                $this->cuotasSeleccionadas = null;
            }
        }

        // Recalcular precios
        $this->recalcularTodosLosItems();
    }
}
```

#### Paso 3: Modificar método agregarArticulo

**Reemplazar el cálculo de precio actual por:**

```php
public function agregarArticulo($articuloId)
{
    try {
        $articulo = Articulo::find($articuloId);

        if (!$articulo) {
            session()->flash('error', 'Artículo no encontrado');
            return;
        }

        // Validar stock si es necesario
        // ... código existente de validación de stock ...

        // NUEVO: Calcular precio con PrecioService
        $contexto = [
            'forma_venta_id' => $this->formaVentaSeleccionada,
            'canal_venta_id' => $this->canalVentaSeleccionado,
            'forma_pago_id' => $this->formaPagoSeleccionada,
            'cuotas' => $this->cuotasSeleccionadas,
            'fecha' => now(),
            'hora' => now()->format('H:i:s'),
            'dia_semana' => (int) now()->dayOfWeek,
            'total_compra' => $this->calcularSubtotalActual(),
        ];

        $calculo = $this->precioService->calcularPrecioFinal(
            $articuloId,
            $this->obtenerSucursalActual(),
            1, // cantidad inicial
            $contexto
        );

        // Agregar al carrito con la información calculada
        $this->items[] = [
            'articulo_id' => $articuloId,
            'nombre' => $articulo->nombre,
            'cantidad' => 1,
            'precio_unitario_base' => $calculo['precio_unitario_base'],
            'precio_unitario_final' => $calculo['precio_final_unitario'],
            'subtotal' => $calculo['precio_final'],
            'promociones_aplicadas' => $calculo['promociones_aplicadas'],
            'nivel_especificidad' => $calculo['nivel_especificidad'],
            'descripcion_precio' => $calculo['descripcion_precio'],
        ];

        $this->recalcularTotales();

    } catch (\Exception $e) {
        \Log::error('Error al agregar artículo: ' . $e->getMessage());
        session()->flash('error', 'Error al calcular precio: ' . $e->getMessage());
    }
}

private function calcularSubtotalActual()
{
    return array_sum(array_column($this->items, 'subtotal'));
}

public function recalcularTodosLosItems()
{
    foreach ($this->items as &$item) {
        $contexto = [
            'forma_venta_id' => $this->formaVentaSeleccionada,
            'canal_venta_id' => $this->canalVentaSeleccionado,
            'forma_pago_id' => $this->formaPagoSeleccionada,
            'cuotas' => $this->cuotasSeleccionadas,
            'fecha' => now(),
            'hora' => now()->format('H:i:s'),
            'dia_semana' => (int) now()->dayOfWeek,
            'total_compra' => $this->calcularSubtotalActual(),
        ];

        $calculo = $this->precioService->calcularPrecioFinal(
            $item['articulo_id'],
            $this->obtenerSucursalActual(),
            $item['cantidad'],
            $contexto
        );

        $item['precio_unitario_final'] = $calculo['precio_final_unitario'];
        $item['subtotal'] = $calculo['precio_final'];
        $item['promociones_aplicadas'] = $calculo['promociones_aplicadas'];
    }

    $this->recalcularTotales();
}
```

#### Paso 4: Actualizar la vista

**Ubicación:** `resources/views/livewire/ventas/nueva-venta.blade.php`

**Agregar selectores ANTES del listado de artículos:**

```html
<!-- Selectores de Contexto de Venta -->
<div class="bg-white rounded-lg shadow p-4 mb-4">
    <h3 class="text-lg font-semibold mb-4">Contexto de Venta</h3>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

        <!-- Forma de Venta -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Forma de Venta
            </label>
            <select wire:model.live="formaVentaSeleccionada"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar...</option>
                @foreach($formasVenta as $forma)
                    <option value="{{ $forma->id }}">{{ $forma->nombre }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Local, Delivery, Take Away, etc.</p>
        </div>

        <!-- Canal de Venta -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Canal de Venta
            </label>
            <select wire:model.live="canalVentaSeleccionado"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar...</option>
                @foreach($canalesVenta as $canal)
                    <option value="{{ $canal->id }}">{{ $canal->nombre }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">POS, Salón, Web, WhatsApp, etc.</p>
        </div>

        <!-- Forma de Pago -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Forma de Pago
            </label>
            <select wire:model.live="formaPagoSeleccionada"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Seleccionar...</option>
                @foreach($formasPago as $fp)
                    <option value="{{ $fp->id }}">{{ $fp->nombre }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">Efectivo, Tarjeta, Transferencia, etc.</p>
        </div>

        <!-- Cuotas (solo si permite) -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Cuotas
            </label>
            <select wire:model.live="cuotasSeleccionadas"
                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    @if(empty($planesCuotas)) disabled @endif>
                <option value="">1 pago</option>
                @foreach($planesCuotas as $plan)
                    <option value="{{ $plan->cantidad_cuotas }}">
                        {{ $plan->obtenerDescripcion() }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-500 mt-1">
                @if(empty($planesCuotas))
                    No disponible para esta forma de pago
                @else
                    Selecciona cantidad de cuotas
                @endif
            </p>
        </div>

    </div>
</div>
```

**Modificar el listado de items para mostrar promociones:**

```html
<!-- En cada fila de la tabla de items -->
<tr>
    <td>{{ $item['nombre'] }}</td>
    <td>{{ $item['cantidad'] }}</td>
    <td>${{ number_format($item['precio_unitario_final'], 2) }}</td>
    <td>
        ${{ number_format($item['subtotal'], 2) }}

        <!-- Mostrar promociones aplicadas -->
        @if(!empty($item['promociones_aplicadas']))
            <div class="text-xs text-green-600 mt-1">
                @foreach($item['promociones_aplicadas'] as $promo)
                    <div class="flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                        </svg>
                        <span>{{ $promo['nombre'] }}</span>
                        @if($promo['porcentaje'])
                            <span class="font-semibold">-{{ $promo['porcentaje'] }}%</span>
                        @else
                            <span class="font-semibold">-${{ number_format($promo['monto_descuento'], 2) }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </td>
    <td>
        <!-- Botones existentes -->
    </td>
</tr>
```

---

### FASE 3: UI de Administración (Prioridad Media)

**Objetivo:** Crear interfaces para administrar:
- Categorías
- Formas de Venta y Canales
- Formas de Pago
- Precios Base
- Promociones

**Estructura sugerida:**

```
app/Livewire/Admin/
├── Categorias/
│   ├── ListarCategorias.php
│   ├── CrearCategoria.php
│   └── EditarCategoria.php
├── FormasVenta/
│   ├── ListarFormasVenta.php
│   └── GestionarFormasVenta.php
├── CanalesVenta/
│   ├── ListarCanalesVenta.php
│   └── GestionarCanalesVenta.php
├── FormasPago/
│   ├── ListarFormasPago.php
│   ├── GestionarFormaPago.php
│   └── ConfigurarCuotas.php
├── Precios/
│   ├── ListarPrecios.php
│   ├── WizardPrecio.php (paso a paso)
│   └── ImportarPrecios.php
└── Promociones/
    ├── ListarPromociones.php
    ├── WizardPromocion.php (paso a paso)
    └── GestionarCondiciones.php
```

**Componentes prioritarios:**

#### 1. Administrador de Precios (Wizard)

**Características:**
- Paso 1: Seleccionar artículo y sucursal
- Paso 2: Seleccionar forma de venta (opcional)
- Paso 3: Seleccionar canal de venta (opcional)
- Paso 4: Ingresar precio
- Paso 5: Configurar vigencias (opcional)
- Paso 6: Confirmar y guardar

**Vista previa de precios existentes:**
- Mostrar tabla con todos los precios del artículo
- Indicar nivel de especificidad con badges
- Permitir editar/eliminar

#### 2. Administrador de Promociones (Wizard)

**Características:**
- Paso 1: Información básica (nombre, descripción, sucursal)
- Paso 2: Tipo de promoción (descuento %, monto, precio fijo, etc.)
- Paso 3: Condiciones (categoría, artículo, forma de pago, etc.)
- Paso 4: Vigencias (fecha, días, horarios)
- Paso 5: Configuración (prioridad, combinable, límites)
- Paso 6: Escalas (si es descuento escalonado)
- Paso 7: Confirmar y guardar

**Dashboard de promociones:**
- Filtrar por activas/inactivas
- Filtrar por sucursal
- Mostrar próximas a vencer
- Mostrar usos actuales vs máximos
- Permitir activar/desactivar rápido

#### 3. Dashboard de Estadísticas

**Mostrar:**
- Promociones más usadas
- Descuentos otorgados por período
- Artículos con más precios específicos
- Formas de pago más utilizadas
- Ventas por canal/forma de venta

---

## 📊 Datos de Prueba Disponibles

### Artículos con Precios Especiales

**Coca Cola 500ml (ID: 1) - Casa Central:**
```
Genérico: $350
Web: $380
Delivery: $400
Delivery + WhatsApp: $420 ⭐
```

**Agua Mineral 500ml (ID: 2):**
```
Casa Central: $200
Sucursal Norte: $220
Sucursal Sur: $250
```

**Papas Lays 150g (ID: 5) - Casa Central:**
```
Normal: $420
Mayorista: $350
Local + Salón: $480
```

**Arroz Gallo 1kg (ID: 11) - Casa Central:**
```
Normal: $680
Oferta (7 días): $550
```

### Promociones Activas

**1. 20% OFF en Bebidas** (Casa Central)
- Condición: Categoría Bebidas
- No combinable
- Prioridad: 10

**2. $100 OFF en compras >$1000** (Casa Central)
- Condición: Total mínimo $1000
- Combinable ✅
- Prioridad: 20

**3. Coca Cola $300** (Sucursal Norte)
- Condición: Artículo específico
- Vigencia: 15 días
- No combinable

**4. Descuentos escalonados en Snacks** (Casa Central)
- 2 unidades: 15% OFF
- 3-4 unidades: 25% OFF
- 5+ unidades: 35% OFF

**5. Happy Hour 30% OFF** (Casa Central)
- Horario: Lun-Vie 17:00-20:00
- Categoría: Bebidas

**6. Cupón VERANO2025** (Casa Central)
- Código: VERANO2025
- 15% OFF
- 100 usos totales, 3 por cliente

**7. 5% extra en efectivo** (Sucursal Sur)
- Condición: Forma de pago efectivo
- Combinable ✅

**8. 10% OFF Delivery** (Casa Central)
- Condición: Delivery + Total >$500
- Combinable ✅

**9. 12% OFF Compras Web** (Sucursal Norte)
- Condición: Canal Web
- No combinable

**10. 25% OFF Fin de semana** (Casa Central)
- Días: Sábados y Domingos
- Categoría: Alimentos

### Formas de Pago con Cuotas

**Tarjeta de Crédito:**
- 1 cuota: Sin recargo
- 3 cuotas: Sin recargo
- 6 cuotas: 10% recargo
- 9 cuotas: 15% recargo
- 12 cuotas: 20% recargo

---

## ⚠️ Notas Importantes

### 1. Compatibilidad con Sistema Actual

**IMPORTANTE:** El sistema nuevo NO rompe el sistema viejo.

- La tabla `000001_precios` antigua fue renombrada a `000001_precios_old`
- Los artículos siguen teniendo `precio_base` en la tabla `articulos`
- El POS actual seguirá funcionando hasta que lo actualices

**Migración gradual sugerida:**
1. Mantener ambos sistemas en paralelo
2. Probar el nuevo sistema con algunos artículos
3. Validar cálculos
4. Migrar completamente cuando esté validado

### 2. Límite de Descuento 70%

El servicio PrecioService automáticamente limita los descuentos finales al 70%.

**Excepción:** Descuentos por cantidad pueden ser 100% (ej: 2x1)

Si ves en los logs:
```
"Descuento de 85% excede el límite de 70%"
```
Es normal, el sistema lo ajustó automáticamente.

### 3. Promociones Combinables

**Regla:** Si una promoción es NO combinable y se aplica, las demás se ignoran.

**Orden de aplicación:** Por prioridad (número menor = mayor prioridad)

**Ejemplo:**
```
Prioridad 5: Coca Cola $300 (NO combinable) ← se aplica esta
Prioridad 10: 20% OFF Bebidas (NO combinable) ← se ignora
```

### 4. Vigencias Temporales

Las promociones validan:
- **Fecha:** vigencia_desde / vigencia_hasta
- **Día:** dias_semana (JSON array con días)
- **Hora:** hora_desde / hora_hasta

**Todas deben cumplirse** para que la promoción aplique.

### 5. Multitenancy

**CRÍTICO:** Todo el sistema usa el prefijo `000001_` para Comercio 1.

Si tienes Comercio 2, necesitas:
```bash
# Ejecutar migraciones para comercio 2
php artisan tenants:migrate --tenant=2

# Ejecutar seeders para comercio 2
php artisan db:seed --class=SistemaPreciosDinamicoSeeder
# (modificar comercioId = 2 en el seeder)
```

### 6. Performance

**Recomendaciones:**
- Los precios base tienen índices en articulo_id, sucursal_id
- Las promociones tienen índices en sucursal_id, activo, vigencia
- Usar eager loading: `->with('formaVenta', 'canalVenta')`

### 7. Logs

El servicio registra en el log:
- Precios no encontrados
- Descuentos que exceden límite
- Errores en cálculos

**Ubicación:** `storage/logs/laravel.log`

---

## 🔧 Comandos Útiles

### Verificar Migraciones
```bash
php artisan migrate:status --database=pymes_tenant
```

### Re-ejecutar Seeders
```bash
# Todos los seeders
php artisan db:seed --class=SistemaPreciosDinamicoSeeder

# Individual
php artisan db:seed --class=CategoriasSeeder
php artisan db:seed --class=PromocionesSeeder
```

### Verificar Datos
```bash
# Contar precios base
php artisan tinker
>>> \App\Models\PrecioBase::count()

# Ver promociones activas
>>> \App\Models\Promocion::activas()->count()

# Ver formas de pago
>>> \App\Models\FormaPago::all()->pluck('nombre')
```

### Limpiar Caché
```bash
php artisan optimize:clear
php artisan view:clear
php artisan livewire:discover
```

---

## 📞 Próxima Sesión - Checklist

**Cuando vuelvas, revisar:**

✅ Este archivo (ROADMAP_SISTEMA_PRECIOS.md)
✅ Archivo PRECIO_SERVICE_GUIA.md (ejemplos de uso)
✅ Estado del código (hacer git status)
✅ Datos en base de datos (verificar que siguen ahí)

**Decidir qué hacer primero:**

**OPCIÓN A: Integrar con POS (Recomendado)**
- Modificar NuevaVenta.php
- Agregar selectores en la vista
- Probar cálculos con datos reales
- Validar que todo funciona

**OPCIÓN B: Crear UI de Administración**
- Crear componentes Livewire
- Crear vistas
- Crear rutas
- Probar gestión de precios y promociones

**OPCIÓN C: Ambas en paralelo**
- UI de administración (prioridad media)
- Integración POS (prioridad alta)
- Ir probando con datos reales

**Mi recomendación:** Opción A primero (integrar con POS) porque así puedes probar el sistema completo con casos reales antes de crear la UI de administración.

---

## 📚 Referencias Rápidas

**Archivos clave:**
- Servicio: `app/Services/PrecioService.php`
- Guía: `PRECIO_SERVICE_GUIA.md`
- Modelos: `app/Models/PrecioBase.php`, `app/Models/Promocion.php`
- POS: `app/Livewire/Ventas/NuevaVenta.php`

**Documentación:**
- [Guía de uso del servicio](PRECIO_SERVICE_GUIA.md)
- [Este roadmap](ROADMAP_SISTEMA_PRECIOS.md)

**Comandos importantes:**
```bash
# Ver logs
tail -f storage/logs/laravel.log

# Tinker para probar
php artisan tinker

# Verificar datos
mysql -u root -p40500273 -e "SELECT COUNT(*) FROM pymes.000001_precios_base"
```

---

## ✅ Checklist Final

Antes de continuar, verificar:

- [ ] Base de datos tiene todas las tablas con prefijo `000001_`
- [ ] Hay datos en las tablas (40+ precios, 10 promociones)
- [ ] Archivo PrecioService.php existe en app/Services/
- [ ] Modelos están en app/Models/
- [ ] Sistema actual del POS sigue funcionando
- [ ] Tienes backups de los archivos que vas a modificar

---

**Fecha de este documento:** 2025-11-17
**Última actualización:** 2025-11-17 18:05
**Estado:** Sistema completo, listo para integración

**Éxito! 🎉**
