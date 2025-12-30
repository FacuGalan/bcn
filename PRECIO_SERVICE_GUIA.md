# Guía de Uso: PrecioService

## Descripción General

`PrecioService` es el servicio centralizado que maneja toda la lógica del sistema de precios dinámico. Implementa:

- ✅ Jerarquía de especificidad de precios (4 niveles)
- ✅ Sistema de promociones con prioridades
- ✅ Promociones combinables vs excluyentes
- ✅ Validaciones temporales (fecha, día, horario)
- ✅ Descuentos escalonados por cantidad
- ✅ Límites de descuento (70% máximo)
- ✅ Cálculos de IVA
- ✅ Recargos/descuentos por forma de pago
- ✅ Cálculos con cuotas

---

## Métodos Principales

### 1. `obtenerPrecioBase()`

Obtiene el precio base más específico para un artículo.

**Firma:**
```php
public function obtenerPrecioBase(
    int $articuloId,
    int $sucursalId,
    ?int $formaVentaId = null,
    ?int $canalVentaId = null,
    ?Carbon $fecha = null
): ?array
```

**Ejemplo de Uso:**
```php
use App\Services\PrecioService;

$precioService = new PrecioService();

// Caso 1: Precio genérico (sin forma ni canal)
$precio = $precioService->obtenerPrecioBase(
    articuloId: 1,      // Coca Cola
    sucursalId: 1       // Casa Central
);
// Resultado: ['precio' => 350.00, 'nivel_especificidad' => 1, 'descripcion' => 'Precio genérico']

// Caso 2: Precio por canal Web
$precio = $precioService->obtenerPrecioBase(
    articuloId: 1,
    sucursalId: 1,
    formaVentaId: null,
    canalVentaId: 4     // Web
);
// Resultado: ['precio' => 380.00, 'nivel_especificidad' => 2, 'descripcion' => 'Canal: Web']

// Caso 3: Precio Delivery
$precio = $precioService->obtenerPrecioBase(
    articuloId: 1,
    sucursalId: 1,
    formaVentaId: 2,    // Delivery
    canalVentaId: null
);
// Resultado: ['precio' => 400.00, 'nivel_especificidad' => 3, 'descripcion' => 'Forma: Delivery']

// Caso 4: Precio más específico (Delivery + WhatsApp)
$precio = $precioService->obtenerPrecioBase(
    articuloId: 1,
    sucursalId: 1,
    formaVentaId: 2,    // Delivery
    canalVentaId: 6     // WhatsApp
);
// Resultado: ['precio' => 420.00, 'nivel_especificidad' => 4, 'descripcion' => 'Delivery + WhatsApp']
```

---

### 2. `calcularPrecioFinal()`

**MÉTODO PRINCIPAL** - Calcula el precio final aplicando todas las reglas del sistema.

**Firma:**
```php
public function calcularPrecioFinal(
    int $articuloId,
    int $sucursalId,
    float $cantidad,
    array $contexto = []
): array
```

**Contexto (Parámetros Opcionales):**
```php
$contexto = [
    'forma_venta_id' => int|null,     // ID forma de venta
    'canal_venta_id' => int|null,     // ID canal de venta
    'forma_pago_id' => int|null,      // ID forma de pago
    'cuotas' => int|null,             // Cantidad de cuotas
    'fecha' => Carbon|null,           // Fecha de la venta
    'hora' => string|null,            // Hora (HH:MM:SS)
    'dia_semana' => int|null,         // 0=Domingo, 6=Sábado
    'total_compra' => float|null,     // Total para validar monto mínimo
    'codigo_cupon' => string|null,    // Código de cupón
];
```

**Ejemplo Básico:**
```php
$precioService = new PrecioService();

$resultado = $precioService->calcularPrecioFinal(
    articuloId: 1,      // Coca Cola
    sucursalId: 1,      // Casa Central
    cantidad: 2
);

/*
Resultado:
[
    'articulo_id' => 1,
    'articulo_nombre' => 'Coca Cola 500ml',
    'cantidad' => 2,

    'precio_unitario_base' => 350.00,
    'nivel_especificidad' => 1,
    'descripcion_precio' => 'Precio genérico',

    'subtotal_sin_descuento' => 700.00,

    'promociones_aplicadas' => [
        [
            'nombre' => '20% OFF en Bebidas',
            'tipo' => 'descuento',
            'porcentaje' => 20,
            'monto_descuento' => 140.00,
            'prioridad' => 10,
            'combinable' => false,
        ]
    ],
    'descuento_total' => 140.00,
    'descuento_porcentaje' => 20.00,
    'subtotal_con_descuento' => 560.00,

    'iva_porcentaje' => 21.00,
    'iva_incluido' => false,
    'precio_sin_iva' => 560.00,
    'iva_monto' => 117.60,
    'precio_con_iva' => 677.60,

    'ajuste_forma_pago' => [
        'tipo' => 'ninguno',
        'monto' => 0,
        'detalle' => []
    ],

    'precio_final' => 677.60,
    'precio_final_unitario' => 338.80,
]
*/
```

**Ejemplo con Contexto Completo:**
```php
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 1,
    sucursalId: 1,
    cantidad: 2,
    contexto: [
        'forma_venta_id' => 2,        // Delivery
        'canal_venta_id' => 6,        // WhatsApp
        'forma_pago_id' => 3,         // Tarjeta de Crédito
        'cuotas' => 6,                // 6 cuotas
        'fecha' => now(),
        'hora' => '18:30:00',         // Happy Hour
        'total_compra' => 1200.00,    // Para validar monto mínimo
    ]
);

// Este cálculo aplicará:
// 1. Precio específico $420 (Delivery + WhatsApp)
// 2. Promoción Happy Hour 30% OFF (si aplica en horario)
// 3. Recargo de 10% por 6 cuotas
```

**Ejemplo con Cupón:**
```php
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 1,
    sucursalId: 1,
    cantidad: 1,
    contexto: [
        'codigo_cupon' => 'VERANO2025',  // Cupón 15% OFF
    ]
);

// Aplicará el cupón VERANO2025 (15% de descuento)
// Incrementará el contador de usos del cupón
```

---

### 3. `calcularCarrito()`

Calcula el precio de múltiples artículos (carrito completo).

**Firma:**
```php
public function calcularCarrito(
    array $items,
    int $sucursalId,
    array $contexto = []
): array
```

**Ejemplo:**
```php
$items = [
    ['articulo_id' => 1, 'cantidad' => 2],  // 2x Coca Cola
    ['articulo_id' => 5, 'cantidad' => 3],  // 3x Papas Lays
    ['articulo_id' => 11, 'cantidad' => 1], // 1x Arroz
];

$contexto = [
    'forma_venta_id' => 1,  // Local
    'canal_venta_id' => 2,  // Salón
    'forma_pago_id' => 1,   // Efectivo
];

$carrito = $precioService->calcularCarrito($items, 1, $contexto);

/*
Resultado:
[
    'items' => [
        [...desglose item 1...],
        [...desglose item 2...],
        [...desglose item 3...],
    ],
    'cantidad_items' => 3,
    'subtotal' => 1500.00,
    'descuento_total' => 250.00,
    'iva_total' => 262.50,
    'total' => 1512.50,
]
*/
```

---

## Ejemplos de Casos de Uso Reales

### Caso 1: Happy Hour (Promoción por Horario)

```php
// Lunes 18:00hs - Compra de cerveza en el salón
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 3,      // Cerveza Quilmes
    sucursalId: 1,
    cantidad: 2,
    contexto: [
        'forma_venta_id' => 1,  // Local
        'canal_venta_id' => 2,  // Salón
        'hora' => '18:00:00',   // Happy Hour
        'dia_semana' => 1,      // Lunes
    ]
);

// Aplicará:
// - Precio salón (si existe precio específico)
// - Promoción Happy Hour 30% OFF (17-20hs Lun-Vie)
```

### Caso 2: Descuentos Escalonados por Cantidad

```php
// Comprando 5 alfajores (snacks)
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 6,      // Alfajor Jorgito
    sucursalId: 1,
    cantidad: 5         // 5+ unidades = 35% OFF
);

// Aplicará:
// - Precio base
// - Promoción escalonada: 35% OFF por 5+ unidades
```

### Caso 3: Compra Web con Tarjeta en Cuotas

```php
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 1,
    sucursalId: 2,      // Sucursal Norte
    cantidad: 1,
    contexto: [
        'canal_venta_id' => 4,   // Web
        'forma_pago_id' => 3,    // Tarjeta de Crédito
        'cuotas' => 12,          // 12 cuotas
    ]
);

// Aplicará:
// - Precio por canal Web (más caro)
// - Promoción 12% OFF Compras Web
// - Recargo 20% por 12 cuotas
```

### Caso 4: Delivery con Descuento por Pago en Efectivo

```php
$items = [
    ['articulo_id' => 1, 'cantidad' => 2],
    ['articulo_id' => 5, 'cantidad' => 1],
];

$resultado = $precioService->calcularCarrito(
    items: $items,
    sucursalId: 3,      // Sucursal Sur
    contexto: [
        'forma_venta_id' => 2,   // Delivery
        'forma_pago_id' => 1,    // Efectivo
        'total_compra' => 800,   // Se calcula automáticamente
    ]
);

// Aplicará:
// - Precios por forma Delivery
// - Promoción 10% OFF Delivery (si total > $500)
// - Promoción 5% extra en efectivo (combinable)
```

### Caso 5: Fin de Semana con Cupón

```php
$resultado = $precioService->calcularPrecioFinal(
    articuloId: 11,     // Arroz (categoría Alimentos)
    sucursalId: 1,
    cantidad: 2,
    contexto: [
        'dia_semana' => 6,              // Sábado
        'codigo_cupon' => 'VERANO2025', // Cupón 15% OFF
    ]
);

// Aplicará:
// - Precio base o precio en oferta (si vigente)
// - Promoción 25% OFF Fin de semana (solo sábados y domingos)
// - NO aplicará el cupón porque la promo de fin de semana no es combinable
//   (se aplicará la de mayor beneficio según prioridad)
```

---

## Integración con Livewire (Nueva Venta)

**Ejemplo en el componente NuevaVenta.php:**

```php
use App\Services\PrecioService;

class NuevaVenta extends Component
{
    protected PrecioService $precioService;

    public function boot(PrecioService $precioService)
    {
        $this->precioService = $precioService;
    }

    public function agregarArticulo($articuloId)
    {
        $cantidad = 1; // o la cantidad seleccionada

        $contexto = [
            'forma_venta_id' => $this->formaVentaSeleccionada,
            'canal_venta_id' => $this->canalVentaSeleccionado,
            'forma_pago_id' => $this->formaPagoSeleccionada,
            'cuotas' => $this->cuotasSeleccionadas,
            'hora' => now()->format('H:i:s'),
            'dia_semana' => (int) now()->dayOfWeek,
        ];

        $calculo = $this->precioService->calcularPrecioFinal(
            $articuloId,
            $this->sucursalActual,
            $cantidad,
            $contexto
        );

        // Usar $calculo para mostrar en la UI
        $this->items[] = [
            'articulo_id' => $articuloId,
            'nombre' => $calculo['articulo_nombre'],
            'cantidad' => $cantidad,
            'precio_unitario' => $calculo['precio_final_unitario'],
            'subtotal' => $calculo['precio_final'],
            'promociones' => $calculo['promociones_aplicadas'],
        ];

        $this->recalcularTotales();
    }
}
```

---

## Notas Importantes

### Límites de Descuento

El servicio valida automáticamente que los descuentos finales no excedan el 70%:

```php
// Si las promociones suman más de 70%, se limita automáticamente
$resultado = $precioService->calcularPrecioFinal(...);

// En el log verás:
// "Descuento de 85% excede el límite de 70%"
// El descuento se ajustará a 70% máximo
```

### Orden de Aplicación

1. **Precio Base** (según especificidad)
2. **Promociones** (por orden de prioridad)
3. **Validación de límite 70%**
4. **Cálculo de IVA**
5. **Ajustes por forma de pago**
6. **Precio Final**

### Promociones Combinables vs Excluyentes

```php
// Ejemplo de promociones que se combinan:
// 1. Descuento 10% Delivery (prioridad 18, combinable)
// 2. Descuento 5% Efectivo (prioridad 25, combinable)
// Total: 15% de descuento acumulado

// Ejemplo de promoción excluyente:
// 1. Descuento 20% Bebidas (prioridad 10, NO combinable)
// Se aplica solo esta, ignorando las demás
```

### Incremento de Uso de Promociones

El servicio automáticamente incrementa el contador `usos_actuales` cuando aplica una promoción. Esto permite:
- Limitar usos totales
- Limitar usos por cliente (requiere identificación del cliente)

---

## Testing

**Ejemplo de prueba unitaria:**

```php
use Tests\TestCase;
use App\Services\PrecioService;

class PrecioServiceTest extends TestCase
{
    public function test_calcula_precio_con_especificidad()
    {
        $service = new PrecioService();

        $resultado = $service->calcularPrecioFinal(
            articuloId: 1,
            sucursalId: 1,
            cantidad: 1
        );

        $this->assertEquals(350.00, $resultado['precio_unitario_base']);
        $this->assertEquals(1, $resultado['nivel_especificidad']);
    }

    public function test_aplica_promociones_happy_hour()
    {
        $service = new PrecioService();

        $resultado = $service->calcularPrecioFinal(
            articuloId: 1,
            sucursalId: 1,
            cantidad: 1,
            contexto: [
                'hora' => '18:00:00',
                'dia_semana' => 1,
            ]
        );

        $this->assertNotEmpty($resultado['promociones_aplicadas']);
        $this->assertEquals('Happy Hour - Bebidas', $resultado['promociones_aplicadas'][0]['nombre']);
    }
}
```

---

## Resumen

`PrecioService` es el corazón del sistema de precios dinámico. Proporciona:

✅ **Cálculos precisos** con jerarquía de especificidad
✅ **Promociones flexibles** con múltiples condiciones
✅ **Validaciones automáticas** de límites y vigencias
✅ **Trazabilidad completa** de todos los cálculos
✅ **Fácil integración** con componentes Livewire
✅ **Bien documentado** para mantenimiento futuro

Para más detalles, consulta el código fuente en `app/Services/PrecioService.php`.
