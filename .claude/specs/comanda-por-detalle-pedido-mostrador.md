# Comanda por Detalle - Pedidos Mostrador - Especificación

## Estado: COMPLETO

> Spec creado el 2026-05-26. Aprobado por el usuario el 2026-05-26. Implementación completada el 2026-05-26 (7 fases).

---

## Contexto y Motivación

Al 2026-05-26 el módulo Pedidos por Mostrador maneja la comanda a nivel del pedido completo: cuando se confirma el pedido (o se reimprime), se manda a cocina la lista completa de artículos. El estado del pedido avanza a `en_preparacion` y de ahí a `listo`/`entregado`.

El problema aparece cuando el cliente quiere **agregar más items a un pedido que ya está en cocina o entregado**:

- Hoy se permite editar el carrito (PR #105) pero **no hay forma de comunicarle a cocina qué es nuevo y qué no**.
- Si se reimprime, cocina recibe todo el ticket de nuevo y duplica la producción.
- Si no se reimprime, los items nuevos no llegan a cocina.

Solución: trackear "está comandado" **por detalle**, no por pedido. Agregar una acción "Comandar" que sabe cuáles ya fueron a cocina y cuáles no, y que imprime solo el delta cuando corresponde.

Como corolario operativo, este feature también **desacopla cobro de entrega**: hoy (PR #105) un pedido tiene que estar cobrado para marcarse como entregado. La realidad del mostrador es que el cliente paga *después* de retirar la comida en muchos casos. Se mantiene el gate de cobro únicamente para "convertir en venta" (la facturación real).

---

## Principios de Diseño

1. **Estado de comandado a nivel línea, no de pedido**. El estado del pedido (`comandado/parcial/no comandado`) se *deriva* de los detalles. Una sola fuente de verdad: `pedidos_mostrador_detalle.comandado_at`.
2. **El service es autoritativo**. Toda la lógica de marcar timestamps, decidir alcance, transicionar estado y disparar impresión vive en `PedidoMostradorService::comandarPedido()`. Livewire/API/CLI lo consumen igual. (Memoria: `feedback-api-first-services`).
3. **La máquina de estados no se afloja**. La transición LISTO→EN_PREPARACION y ENTREGADO→EN_PREPARACION **no se agregan a `TRANSICIONES_PERMITIDAS`**. Sólo `comandarPedido()` puede hacerla, bypaseando la validación internamente. El operario no la encuentra en el modal "Cambiar estado".
4. **Cobro y comanda son ortogonales**. El operario puede entregar sin cobrar (un cliente que paga después está bien). Solo la conversión a venta requiere pagos materializados al 100%.
5. **Multi-tenant correcto**. Migración por comercio con prefijo, conexión `pymes_tenant`, regen de `database/sql/tenant_tables.sql`.
6. **Tests obligatorios proporcionales**. Cobertura de service (lógica core), smoke de Livewire (interacción), integración para regresiones del gate de cobro quitado.

---

## Requisitos Funcionales

### RF-01: Campo `comandado_at` por detalle
- Nueva columna `comandado_at` (timestamp nullable, default NULL) en `pedidos_mostrador_detalle`.
- `null` = detalle no comandado. Valor = momento en que fue impreso a cocina.

### RF-02: Estado de comanda derivado
- `PedidoMostrador` expone accessor `estado_comanda`: retorna `'no_comandado'` | `'parcial'` | `'comandado'`.
  - `'no_comandado'`: todos los detalles con `comandado_at = null`.
  - `'parcial'`: algunos null y otros con timestamp.
  - `'comandado'`: todos con timestamp.
- No se persiste en BD: se calcula desde la colección de detalles.

### RF-03: Acción "Comandar" — service `comandarPedido()`
Nuevo método público:

```php
public function comandarPedido(
    PedidoMostrador $pedido,
    string $alcance = 'todos' // 'todos' | 'nuevos'
): array
```

Comportamiento:
1. Resuelve qué detalles van: si `alcance='nuevos'`, solo `comandado_at = null`; si `'todos'`, todos.
2. Setea `comandado_at = now()` en los detalles del alcance (incluso si ya estaban comandados — D5).
3. Transiciona el pedido:
   - Desde CONFIRMADO → EN_PREPARACION (via `cambiarEstado`, transición legal).
   - Desde LISTO o ENTREGADO → EN_PREPARACION **bypaseando la validación** (transición no presente en `TRANSICIONES_PERMITIDAS`).
   - Desde EN_PREPARACION → no transiciona (ya está donde tiene que estar).
4. Llama a `PlantillasComanda::generar...` con `detalleIds` del alcance.
5. Retorna el mismo payload que `imprimirComanda` actual: `['escpos', 'html', 'tipo_documento', 'pedido_id']` para que el caller (Livewire) lo despache a QZ.

### RF-04: Comanda automática al confirmar
- Cuando `sucursal.imprime_comanda_automatico = true`, al confirmar el pedido el service invoca internamente `comandarPedido($pedido, 'todos')` en lugar del flujo viejo `maybeImprimirComandaAutomatica + avanzarAEnPreparacionSiCorresponde`.
- Resultado: todos los detalles del pedido confirmado quedan con `comandado_at` seteado y el pedido en EN_PREPARACION.

### RF-05: Flujo decisor de "Comandar" en Livewire
La acción del botón "Comandar" en la lista (`PedidosMostrador::comandarPedido(int $pedidoId)`):

- Lee el pedido y calcula `$nuevos` (detalles con `comandado_at=null`) y `$comandados`.
- Si `$nuevos.count() > 0 && $comandados.count() > 0` (mezcla) → abre modal `comandar-modal` con dos opciones + conteos. NO ejecuta nada todavía.
- Si `$comandados.count() === $detalles.count()` (todos comandados) → ejecuta directo `service->comandarPedido(pedido, 'todos')` (reimpresión completa). Sin modal.
- Si `$nuevos.count() === $detalles.count()` (ninguno comandado, primera comanda) → ejecuta directo `service->comandarPedido(pedido, 'todos')`. Sin modal.

Método `confirmarComandar(string $alcance)` ejecuta tras la elección en el modal.

### RF-06: Impresión diferenciada para alcance "nuevos"
- `PlantillasComanda` acepta nuevo parámetro `?array $detalleIds = null` y un flag `bool $esParcial = false`.
- Si `$esParcial = true`, el ticket lleva un header destacado: `*** AGREGADO ***` (línea aparte, centrado, doble alto si soporta ESC/POS).
- Si `$detalleIds` provisto, itera solo esos detalles. Si null, itera todos (comportamiento actual).

### RF-07: Edición de items ya comandados — sin restricción adicional
- Las reglas actuales de edición (PR #105) **se mantienen**: editable mientras `estado_pago=pendiente` y sin cobros materializados activos.
- Si el operario edita un detalle con `comandado_at != null` (cambia cantidad, descuentos, etc.), el `comandado_at` se preserva. Queda a criterio del operario comunicarlo a cocina por canal externo.
- Si el operario elimina un detalle con `comandado_at != null`, simplemente desaparece (no se trackea un "comandado pero cancelado").
- (D7 abierto a futuro: badge "modificado tras comandar" — no parte de este spec).

### RF-08: Desacoplar cobro de entrega
Eliminar del componente `PedidosMostrador`:

- En `entregarRapido()`: la llamada a `gatearPorCobro($pedido, 'entregar')`.
- En `cambiarEstadoDrag()`: el bloque que intercepta `$nuevoEstado === ESTADO_ENTREGADO && !pedidoEstaCobrado()`.
- En `confirmarCambiarEstado()`: el bloque equivalente para destino `entregado`.
- En `reanudarAccionPendienteSiCobrado()`: el arm `'entregar' => $this->entregarRapido(...)` (queda solo `'convertir'`).

El sistema `accionPendiente` se mantiene **completo** pero se reduce a un único caso de uso: `'convertir'`. Refactor de naming opcional (mantener `accionPendiente` por compat con la prop ya pusheada en producción).

### RF-09: Mantener gate de cobro para convertir
- `abrirConvertir($pedidoId)` sigue llamando a `gatearPorCobro($pedido, 'convertir')` cuando el pedido no está 100% cubierto.
- `reanudarAccionPendienteSiCobrado()` sigue ejecutando `$this->abrirConvertir($pedidoId)` cuando la acción pendiente era `'convertir'`.

---

## Modelo de Datos

### Tabla modificada: `pedidos_mostrador_detalle`

Agregar columna:

| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `comandado_at` | timestamp NULL | NULL | Momento en que el detalle se envió a cocina. Null = no comandado todavía. |

Ubicación: `AFTER pagado_con_puntos` (cerca del resto de timestamps lógicos). Sin índice — la cardinalidad por pedido es baja (decenas), no se filtra a nivel global.

Migración multi-tenant: itera todos los comercios, prefijo `{NNNNNN}_`, `ALTER TABLE`, try/catch por comercio.

### Modelo: `PedidoMostradorDetalle`
- `$fillable[]` += `comandado_at`
- `$casts['comandado_at'] = 'datetime'`

### Modelo: `PedidoMostrador`
- Accessor `estado_comanda` (RF-02). Implementación:
```php
public function getEstadoComandaAttribute(): string
{
    $detalles = $this->relationLoaded('detalles') ? $this->detalles : $this->detalles()->get();
    if ($detalles->isEmpty()) return 'no_comandado';
    $comandados = $detalles->whereNotNull('comandado_at')->count();
    if ($comandados === 0) return 'no_comandado';
    if ($comandados === $detalles->count()) return 'comandado';
    return 'parcial';
}
```

### Constantes (no DB)
- En `PedidoMostrador`:
  - `ESTADO_COMANDA_NO = 'no_comandado'`
  - `ESTADO_COMANDA_PARCIAL = 'parcial'`
  - `ESTADO_COMANDA_TOTAL = 'comandado'`
- En `PedidoMostradorService`:
  - `ALCANCE_COMANDA_TODOS = 'todos'`
  - `ALCANCE_COMANDA_NUEVOS = 'nuevos'`

---

## Pantallas UI

### Componente `PedidosMostrador` (modificado)

**Cambios en props**:
- Eliminar el manejo del path `'entregar'` en `accionPendiente`. El sistema queda solo para `'convertir'`.
- Nuevas props del modal Comandar:
  - `public bool $showComandarModal = false;`
  - `public ?int $pedidoComandarId = null;`
  - `public int $comandarNuevosCount = 0;`
  - `public int $comandarComandadosCount = 0;`

**Cambios en acciones**:
- Renombrar/refactorizar `reimprimirComanda(int $pedidoId)` → `comandarPedido(int $pedidoId)`:
  - Lee el pedido con `detalles`.
  - Si mezcla → setea props del modal y abre.
  - Si no → ejecuta `service->comandarPedido(...)` directo y despacha QZ.
- Nuevo `confirmarComandar(string $alcance)`:
  - Lee `$pedidoComandarId`, valida que aún sea válido.
  - Llama `service->comandarPedido(pedido, $alcance)`.
  - Despacha QZ, cierra modal, refresca lista, dispatch toast.
- Nuevo `cerrarComandarModal()`.

**Eliminaciones (RF-08)**:
- En `entregarRapido()`: quitar el `if ($this->gatearPorCobro($pedido, 'entregar')) return;`
- En `cambiarEstadoDrag()`: quitar el bloque `if ($nuevoEstado === ESTADO_ENTREGADO && ! $this->pedidoEstaCobrado(...))`
- En `confirmarCambiarEstado()`: quitar el bloque equivalente.
- En `reanudarAccionPendienteSiCobrado()`: dejar solo el arm `'convertir'`.

### Vista `pedidos-mostrador.blade.php` (modificada)

**Botón "Comandar" (reemplaza "Imprimir comanda")**:
- Color: **azul** (`border-blue-300 dark:border-blue-600 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-900/30`) — alineado con el sistema (D3 cerrado: azul kitchen).
- Icono: `chef-hat` (silueta de cocinero) o fallback al actual icono de impresora si no hay SVG inline a mano. Decisión final: copiar el icono de impresora actual pero swap de color.
- Tooltip dinámico:
  - Si `estado_comanda === 'no_comandado'`: "Comandar pedido"
  - Si `estado_comanda === 'parcial'`: "Comandar (hay items nuevos)" + badge contador
  - Si `estado_comanda === 'comandado'`: "Reimprimir comanda"
- Aplica en 3 vistas: cards móvil, tabla desktop, cards kanban.

**Modal `comandar-modal`** (nuevo, `<x-bcn-modal>`):
- Color de header: **azul** (matchea el botón).
- Contenido: dos botones grandes apilados:
  - Botón A: "Comandar solo los nuevos ({{ $count }})" — fondo amber/yellow.
  - Botón B: "Comandar todo el pedido ({{ $total }})" — fondo blue.
- Footer: botón "Cancelar" (cierra sin acción).
- Cada botón dispara `wire:click="confirmarComandar('nuevos'|'todos')"`.

**Badge "Nuevo" por línea** (D2 cerrado):
- Solo en el **modal de edición** (`nuevo-pedido-mostrador`, columna del carrito) y en el **modal "Ver detalle"**.
- NO en listado ni en kanban (sería ruidoso).
- Visual: badge pequeño amber `bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200` con texto "Nuevo", aparece a la derecha del nombre del artículo si `detalle.comandado_at === null` y el pedido está en algún estado posterior a borrador.
- En pedidos borrador todos los items son "nuevos" → no mostrar badge (sería redundante).

---

## Servicios

### `PedidoMostradorService` — `app/Services/Pedidos/PedidoMostradorService.php`

**Métodos nuevos**:
- `comandarPedido(PedidoMostrador $pedido, string $alcance = 'todos'): array` — orquesta marcar timestamps, transicionar estado y generar payload.
- Helper privado `protected function marcarDetallesComoComandados(PedidoMostrador $pedido, ?array $detalleIds = null): int` — UPDATE masivo con `comandado_at = now()`. Retorna count.
- Helper privado `protected function transicionarTrasComanda(PedidoMostrador $pedido): void` — encapsula la lógica:
  - Si CONFIRMADO → `cambiarEstado(ESTADO_EN_PREPARACION)` (legal).
  - Si LISTO o ENTREGADO → `forzarEstado(ESTADO_EN_PREPARACION)` (bypass transitions). Log con motivo "re-comandado".
- Helper privado `protected function forzarEstado(PedidoMostrador $pedido, string $nuevoEstado, ?string $motivo = null): void` — UPDATE directo sin validar `TRANSICIONES_PERMITIDAS`, registra Log::info para auditar.

**Métodos modificados**:
- `maybeImprimirComandaAutomatica(PedidoMostrador $pedido): void` — pasa a delegar en `comandarPedido($pedido, 'todos')`. Mantiene el guard `sucursal.imprime_comanda_automatico`.
- `avanzarAEnPreparacionSiCorresponde(...)` — método introducido en PR #105. Queda como compat layer interno o se elimina si nadie más lo llama. Verificar consumidores antes de borrar.
- `imprimirComanda(PedidoMostrador $pedido): array` — método existente. Pasa a ser un wrapper de `comandarPedido($pedido, 'todos')` para preservar el contrato externo (consumido por `PedidosMostrador::reimprimirComanda` hoy).

### `PlantillasComanda` — `app/Services/Impresion/PlantillasComanda.php`

**Métodos modificados**:
- `generarComandaESCPOS(PedidoMostrador $pedido, ?array $detalleIds = null, bool $esParcial = false): string`
- `generarComandaHTML(PedidoMostrador $pedido, ?array $detalleIds = null, bool $esParcial = false): string`

Implementación:
- Si `$detalleIds` provisto: filtra `$pedido->detalles->whereIn('id', $detalleIds)`.
- Si `$esParcial = true`: agrega un header `*** AGREGADO ***` antes de la lista de items, en ESC/POS con doble alto (`\x1D\x21\x11`) y centrado (`\x1B\x61\x01`). En HTML, un `<div class="text-center text-2xl font-bold">*** AGREGADO ***</div>`.

---

## Migraciones Necesarias

1. **`2026_05_27_HHMMSS_add_comandado_at_to_pedidos_mostrador_detalle.php`** (tenant)
   - Itera todos los comercios.
   - `ALTER TABLE {prefix}pedidos_mostrador_detalle ADD COLUMN comandado_at TIMESTAMP NULL DEFAULT NULL AFTER pagado_con_puntos`
   - try/catch por comercio.
   - **Crítico**: regenerar `database/sql/tenant_tables.sql` después de testear.

> Nota: no se requiere backfill. Los pedidos existentes con detalles quedan con `comandado_at=null`. Esto significa que pedidos antiguos en estado ENTREGADO/LISTO/FACTURADO aparecerán visualmente como "todos nuevos" si se vuelven a abrir, pero al estar FACTURADO/CANCELADO no se podrán recomandar. Para pedidos en estados activos (en_preparacion/listo/entregado al momento del deploy), un operario que apriete "Comandar" entraría al flujo de reimpresión completa — comportamiento aceptable como migración suave.

---

## Traducciones

Claves nuevas a agregar en `lang/{es,en,pt}.json`:

| Clave (es) | en | pt |
|------------|----|----|
| `Comandar` | `Send to kitchen` | `Enviar à cozinha` |
| `Comandar pedido` | `Send order to kitchen` | `Enviar pedido à cozinha` |
| `Reimprimir comanda` | `Reprint kitchen order` | `Reimprimir comanda` |
| `Comandar (hay items nuevos)` | `Send to kitchen (new items)` | `Enviar à cozinha (novos itens)` |
| `Comandar solo los nuevos` | `Send only new items` | `Enviar somente os novos` |
| `Comandar todo el pedido` | `Send entire order` | `Enviar o pedido inteiro` |
| `Items nuevos` | `New items` | `Novos itens` |
| `Items ya comandados` | `Already sent items` | `Itens já enviados` |
| `Nuevo` | `New` | `Novo` |
| `AGREGADO` | `ADDED` | `ADICIONADO` |
| `Pedido reenviado a cocina` | `Order sent to kitchen` | `Pedido enviado à cozinha` |

Claves a **eliminar/actualizar**:
- Si el botón viejo decía "Imprimir comanda" en la vista pero no aparecía en los lang files, no hay que tocar. Verificar antes.

---

## Criterios de Aceptación

### Comportamiento del service

- [ ] **CA-01**: `comandarPedido($pedido, 'todos')` con pedido CONFIRMADO marca todos los detalles con `comandado_at=now()` y transiciona el pedido a EN_PREPARACION.
- [ ] **CA-02**: `comandarPedido($pedido, 'todos')` con pedido LISTO regresa el estado a EN_PREPARACION (bypass de transition map).
- [ ] **CA-03**: `comandarPedido($pedido, 'todos')` con pedido ENTREGADO regresa el estado a EN_PREPARACION.
- [ ] **CA-04**: `comandarPedido($pedido, 'nuevos')` con un pedido que tiene 2 detalles comandados + 1 nuevo solo actualiza el nuevo. Los otros conservan su `comandado_at` original.
- [ ] **CA-05**: `comandarPedido($pedido, 'todos')` con un pedido ya 100% comandado **actualiza** los `comandado_at` al timestamp actual (D5 cerrado).
- [ ] **CA-06**: Al confirmar un pedido con `sucursal.imprime_comanda_automatico=true`, todos sus detalles quedan con `comandado_at != null` y el pedido en EN_PREPARACION.
- [ ] **CA-07**: Al confirmar un pedido con `sucursal.imprime_comanda_automatico=false`, los detalles quedan con `comandado_at=null` y el pedido queda en CONFIRMADO (la comanda manual la dispara el operario).
- [ ] **CA-08**: Cancelar un pedido con detalles ya comandados **preserva** los `comandado_at` (D4 cerrado).
- [ ] **CA-09**: Agregar un detalle via `actualizarPedido` a un pedido ya comandado crea el nuevo con `comandado_at=null`. Los existentes conservan su valor.

### Comportamiento de la vista/Livewire

- [ ] **CA-10**: Click en "Comandar" sobre un pedido sin items previos comandados (todos null) ejecuta `comandarPedido(..., 'todos')` directo sin modal.
- [ ] **CA-11**: Click en "Comandar" sobre un pedido 100% comandado ejecuta `comandarPedido(..., 'todos')` directo sin modal (reimpresión).
- [ ] **CA-12**: Click en "Comandar" sobre un pedido con mezcla abre `comandar-modal` con conteos correctos.
- [ ] **CA-13**: Modal cerrado con "Cancelar" no marca ningún detalle ni cambia estado.
- [ ] **CA-14**: Modal con "Comandar solo los nuevos" marca solo los `comandado_at=null` y ejecuta impresión parcial con header "AGREGADO".
- [ ] **CA-15**: Modal con "Comandar todo el pedido" marca/refresca todos los detalles e imprime ticket completo (sin header AGREGADO).

### Regresión del gate de cobro (RF-08, RF-09)

- [ ] **CA-16**: Llamar `entregarRapido($pedidoId)` sobre un pedido NO cobrado lo entrega exitosamente (sin abrir cobro rápido).
- [ ] **CA-17**: Drag a la columna ENTREGADO en kanban con pedido no cobrado transiciona el estado.
- [ ] **CA-18**: Cambiar estado manual a ENTREGADO desde el modal funciona sin cobro previo.
- [ ] **CA-19**: Click en "Convertir en venta" sobre un pedido NO cobrado abre cobro rápido (gate sigue activo).
- [ ] **CA-20**: Al cobrar el 100% en el modal disparado por convertir, se reanuda `abrirConvertir` automáticamente.

### Impresión

- [ ] **CA-21**: `PlantillasComanda::generarComandaESCPOS($pedido, [3, 5])` retorna un ticket con solo los detalles de IDs 3 y 5.
- [ ] **CA-22**: Cuando `esParcial=true`, el output contiene el string "AGREGADO" (case-sensitive).
- [ ] **CA-23**: Cuando `esParcial=false`, el output NO contiene "AGREGADO".

### UI - Badges

- [ ] **CA-24**: En el modal de edición, cada detalle con `comandado_at=null` muestra badge "Nuevo" amber, excepto si el pedido está en BORRADOR.
- [ ] **CA-25**: En el modal "Ver detalle", cada detalle con `comandado_at=null` muestra el mismo badge.
- [ ] **CA-26**: La vista lista (cards/tabla/kanban) NO muestra badges "Nuevo" por línea.

### Multi-tenant / migración

- [ ] **CA-27**: La migración corre para todos los comercios y agrega la columna sin error.
- [ ] **CA-28**: `database/sql/tenant_tables.sql` queda actualizado con la nueva columna en `pedidos_mostrador_detalle`.
- [ ] **CA-29**: Pedidos existentes (creados antes de la migración) tienen `comandado_at=null` en todos sus detalles.

### Tests

- [ ] **CA-30**: `tests/Integration/Services/Pedidos/PedidoMostradorComandaTest.php` cubre CA-01 a CA-09. Todos verdes.
- [ ] **CA-31**: `tests/Feature/Livewire/Pedidos/SmokePedidosTest.php` agrega tests para CA-10 a CA-18 (los aplicables a Livewire). Todos verdes.
- [ ] **CA-32**: `tests/Unit/Services/Impresion/PlantillasComandaTest.php` (o equivalente) cubre CA-21 a CA-23.

---

## Plan de Implementación

### Fase 1: Migración + Modelo [COMPLETO]
1. Crear migración tenant `add_comandado_at_to_pedidos_mostrador_detalle` (skill `/migration`).
2. Correr `php artisan migrate` en local + verificar en una BD de comercio.
3. Regenerar `database/sql/tenant_tables.sql`.
4. Actualizar `app/Models/PedidoMostradorDetalle.php`: `$fillable += ['comandado_at']`, `$casts['comandado_at'] = 'datetime'`.
5. Agregar accessor `getEstadoComandaAttribute()` y constantes ESTADO_COMANDA_* a `PedidoMostrador`.

### Fase 2: Service [COMPLETO]
1. Implementar `comandarPedido(PedidoMostrador, string): array` en `PedidoMostradorService`.
2. Implementar helpers `marcarDetallesComoComandados`, `transicionarTrasComanda`, `forzarEstado`.
3. Refactorizar `maybeImprimirComandaAutomatica` → delegar en `comandarPedido(..., 'todos')`.
4. Refactorizar `imprimirComanda` → wrapper de `comandarPedido(..., 'todos')` (mantener firma pública).
5. Verificar si `avanzarAEnPreparacionSiCorresponde` queda obsoleto; si no se usa más, eliminarlo. Si tests u otros consumidores lo usan, dejarlo como facade interno.
6. Escribir tests integration en `tests/Integration/Services/Pedidos/PedidoMostradorComandaTest.php` (CA-01 a CA-09).

### Fase 3: Plantillas [COMPLETO]
1. Modificar `PlantillasComanda::generarComandaESCPOS` y `generarComandaHTML` para aceptar `?array $detalleIds, bool $esParcial`.
2. Implementar header "AGREGADO" centrado y doble alto en ESC/POS, equivalente en HTML.
3. Tests unitarios para snapshot del header parcial (CA-21 a CA-23).

### Fase 4: Livewire — quitar gate entregar [COMPLETO]
1. Editar `PedidosMostrador.php`: eliminar las 3 intercepciones de cobro para entregar (RF-08).
2. Limpiar arm `'entregar'` de `reanudarAccionPendienteSiCobrado`.
3. Smoke tests: verificar que entregar sin cobrar funciona (CA-16 a CA-18) y que convertir sin cobrar sigue interceptado (CA-19, CA-20).
4. Revisar y ajustar/eliminar tests del PR #105 que asumían el gate activo en entregar.

### Fase 5: Livewire — action Comandar + modal [COMPLETO]
1. Refactorizar `reimprimirComanda` → `comandarPedido(int $pedidoId)` con el flujo decisor (RF-05).
2. Agregar props del modal y método `confirmarComandar(string $alcance)`.
3. Smoke tests CA-10 a CA-15.

### Fase 6: Vista + Modal Blade + Badges [COMPLETO]
1. Renombrar botón "Imprimir comanda" → "Comandar" en las 3 vistas (cards/tabla/kanban). Color azul.
2. Tooltip dinámico según `estado_comanda`.
3. Crear `comandar-modal.blade.php` (o inline en la misma vista) con dos botones grandes apilados.
4. Badge "Nuevo" por línea en modal edición (`nuevo-pedido-mostrador`) y modal "Ver detalle".
5. Traducciones: agregar las 11 claves en es/en/pt (skill `/traducir`).

### Fase 7: Docs + PR [COMPLETO]
1. Invocar `@docs-sync` antes de crear PR.
2. Verificar pre-flight: `pint --test`, `artisan test --filter=PedidoMostrador`, `artisan test --filter=SmokePedidos`.
3. Crear PR siguiendo el template del proyecto.
4. Cerrar el spec a `COMPLETO` post-merge.

---

## Notas y Decisiones

- **2026-05-26 — D1** Transición LISTO/ENTREGADO → EN_PREPARACION: NO se agrega a `TRANSICIONES_PERMITIDAS`. Solo `comandarPedido()` la realiza vía `forzarEstado()` (bypass + log). Razón: mantener semántica estricta de la máquina, evitar que el operario regrese pedidos sin reimprimir.

- **2026-05-26 — D2** Badge "Nuevo" por línea: solo en modal de edición y modal "Ver detalle". NO en listado/kanban. Razón: ruido visual en filas con varios items.

- **2026-05-26 — D3** Color del botón "Comandar": azul (`border-blue-300/600 text-blue-700/300`). Diferencia del gris del viejo "Imprimir comanda". Mismo color que el modal correspondiente.

- **2026-05-26 — D4** Cancelación preserva `comandado_at`. Razón: auditoría de comandas perdidas para análisis de desperdicio futuro.

- **2026-05-26 — D5** Reimpresión total actualiza timestamps a `now()`. Razón: refleja la reimpresión real; "edad de la última comanda" queda fresca.

- **2026-05-26 — Gate de cobro** Se quita de entregar (RF-08), se mantiene en convertir (RF-09). Esto revierte parcialmente la decisión del PR #105 (que aplicaba gate a entregar). Razón: el flujo real del mostrador permite entregar antes de cobrar; la materialización a venta (que sí requiere pagos definitivos) es el único momento donde el gate aporta.

- **2026-05-26 — Sin backfill** La migración no rellena `comandado_at` en pedidos existentes. Quedan como "no comandados" según el accessor. Para pedidos en estados activos al deploy, el operario que dispare "Comandar" entra al flujo de reimpresión completa — comportamiento aceptable como transición.

- **Pendiente futuro (no en este spec)** Badge "Modificado tras comandar" cuando un detalle ya comandado cambia cantidad/precio. Tracking de timestamp `modificado_at` por detalle. Out of scope.

- **Pendiente futuro (no en este spec)** Cancelar un detalle ya comandado podría imprimir un ticket "*** CANCELADO ***" a cocina. Out of scope.
