# Recetas, Opcionales y Stock por Sucursal - Documento de Diseño

> **Estado:** FASES 1-6 COMPLETAS. Implementado en 2026-02. Este archivo se preserva como referencia historica del diseño original.
> **Migrado desde memory/recetas-opcionales-design.md el 2026-05-18.**

## Estado historico
- Fases 1-5 implementadas hace 96+ dias.
- Fase 6 (integracion con ventas) completada despues.
- UI: GestionarArticulos es hub central con modales inline para opcionales y recetas.
- GestionarGruposOpcionales tiene disponibilidad por sucursal y asignacion masiva.
- GestionarRecetas muestra todas las recetas genericas con copiar y nueva receta wizard.
- AsignarOpcionales deprecado.

---

## Resumen del Cambio
Agregar a los articulos: recetas (formulas de ingredientes), grupos de opcionales (modificadores para ventas), y mover la configuracion de stock a nivel articulo-sucursal.

---

## Principios de Diseño

1. **Catalogo global unico**: Articulos, grupos opcionales y opcionales existen UNA sola vez por comercio. Esto permite reportes cruzados entre sucursales (ej: "cuantos pan de papa se vendieron por sucursal").
2. **Asignacion explicita por sucursal**: Lo que varia por sucursal es la ASIGNACION (que grupos/opciones tiene cada articulo), los precios, el orden, y la disponibilidad. Sin override patterns en opcionales = queries simples con INNER JOINs.
3. **Recetas con default + override por sucursal**: Las recetas (de articulos y opcionales) se definen una vez como default y se pueden overridear por sucursal. Es un lookup secuencial simple, no un JOIN complejo. Justificado porque las recetas tienen datos hijos (ingredientes) y duplicarlas seria costoso.
4. **Stock siempre por sucursal**: La tabla `stock` ya funciona asi.
5. **Opcionales son entidades separadas** (no son articulos), pero sus recetas apuntan a articulos como ingredientes.

---

## Cambios en Tabla `articulos`

### Quitar:
- `es_servicio` (boolean) - ya no se usa
- `controla_stock` (boolean) - se mueve a `articulos_sucursales`

### Agregar:
- `es_materia_prima` (boolean, default false) - solo informativo, para filtrado

---

## Cambios en Tabla `articulos_sucursales` (pivot existente)

### Columnas actuales:
- `id`, `articulo_id`, `sucursal_id`, `activo`, `created_at`, `updated_at`

### Agregar:
| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `modo_stock` | enum('ninguno','unitario','receta') | 'ninguno' | Mutuamente excluyentes. `unitario` = descuenta el articulo, `receta` = descuenta ingredientes |
| `vendible` | tinyint(1) | 1 | Si aparece en pantalla de ventas (puede ser reemplazado por canales en el futuro) |

> Nota: `canales` se manejara via tabla pivot `articulo_sucursal_canal` en vez de JSON, porque `canales_venta` ya es una tabla dinamica.

---

## Tablas Nuevas

### `grupos_opcionales` (catalogo global)
Grupos de opciones reutilizables (ej: "Panes a eleccion", "Salsas", "Agregados").
Disponibles para todas las sucursales. Lo que determina si se usan es la asignacion.

| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `nombre` | varchar(150) | | Ej: "Panes a eleccion" |
| `descripcion` | text, nullable | null | |
| `obligatorio` | tinyint(1) | 0 | Si el cliente DEBE elegir |
| `tipo` | enum('seleccionable','cuantitativo') | 'seleccionable' | `seleccionable` = si/no por opcion, `cuantitativo` = cantidad por opcion |
| `min_seleccion` | int unsigned | 0 | Minimo de opciones/cantidad total |
| `max_seleccion` | int unsigned, nullable | null | Maximo (null = sin limite) |
| `activo` | tinyint(1) | 1 | Activo/inactivo global por admin |
| `orden` | int | 0 | Orden de visualizacion |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

**Validacion de tipo:**
- `seleccionable`: cada opcion es si/no. Cada "si" cuenta como 1. Total de "si" debe estar entre min y max.
- `cuantitativo`: cada opcion tiene cantidad (0, 1, 2, 3...). Suma de cantidades debe estar entre min y max. Ej: "3 de jamon + 2 de queso" = 5 total.

---

### `opcionales` (catalogo global)
Las opciones individuales dentro de un grupo (ej: "Pan de papa", "Pan de sesamo").
Unicas por comercio. El mismo "Pan de papa" (mismo id) se usa en todas las sucursales para reportes.

| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `grupo_opcional_id` | bigint FK | | -> grupos_opcionales.id ON DELETE CASCADE |
| `nombre` | varchar(150) | | Ej: "Pan de papa" |
| `descripcion` | text, nullable | null | |
| `precio_extra` | decimal(12,2) | 0.00 | Precio template/default. Se copia a las asignaciones al crear |
| `activo` | tinyint(1) | 1 | Activo/inactivo global por admin. Si false, no aparece en ningun lado |
| `orden` | int | 0 | Orden default |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |

---

### `articulo_grupo_opcional` (asignacion por articulo + sucursal)
Dice: "Este articulo tiene este grupo de opcionales en esta sucursal".
Siempre explicito con sucursal_id (NOT NULL). Sin override patterns.

| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `articulo_id` | bigint FK | | -> articulos.id ON DELETE CASCADE |
| `grupo_opcional_id` | bigint FK | | -> grupos_opcionales.id ON DELETE CASCADE |
| `sucursal_id` | bigint FK | | -> sucursales.id ON DELETE CASCADE |
| `activo` | tinyint(1) | 1 | Permite desactivar el grupo para este articulo en esta sucursal sin borrar la fila |
| `orden` | int | 0 | Orden de visualizacion del grupo para este articulo |
| `created_at` | timestamp | | |
| `updated_at` | timestamp | | |
| UNIQUE | | | `(articulo_id, grupo_opcional_id, sucursal_id)` |

---

### `articulo_grupo_opcional_opcion` (detalle: que opciones, precio, orden, estado)

| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `articulo_grupo_opcional_id` | bigint FK | | -> articulo_grupo_opcional.id ON DELETE CASCADE |
| `opcional_id` | bigint FK | | -> opcionales.id ON DELETE CASCADE |
| `precio_extra` | decimal(12,2) | 0.00 | Precio concreto para esta asignacion. Se copia del template al crear |
| `activo` | tinyint(1) | 1 | Decision del admin: desactivar sin borrar |
| `disponible` | tinyint(1) | 1 | Estado de stock: false = agotado en esta sucursal |
| `orden` | int | 0 | Orden de esta opcion en este contexto |
| UNIQUE | | | `(articulo_grupo_opcional_id, opcional_id)` |

**Diferencia entre `activo` y `disponible`:**
- `activo` = decision administrativa ("no quiero esta opcion aca")
- `disponible` = estado operativo ("se agoto")

---

### `recetas` (polimorfica - para articulos Y opcionales)
Usa default + override por sucursal.

| Campo | Tipo | Default | Descripcion |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `recetable_type` | varchar(50) | | `'Articulo'` o `'Opcional'` |
| `recetable_id` | bigint | | ID del articulo u opcional |
| `sucursal_id` | bigint FK, **nullable** | null | null = receta default para todas. Con valor = override para esa sucursal |
| `cantidad_producida` | decimal(12,3) | 1.000 | "Esta receta produce X unidades del producto" |
| `notas` | text, nullable | null | |
| `activo` | tinyint(1) | 1 | |
| UNIQUE | | | `(recetable_type, recetable_id, sucursal_id)` - permite 1 default + 1 por sucursal |

---

### `receta_ingredientes`

| Campo | Tipo | Descripcion |
|-------|------|-------------|
| `id` | bigint PK | |
| `receta_id` | bigint FK | -> recetas.id ON DELETE CASCADE |
| `articulo_id` | bigint FK | -> articulos.id (el ingrediente) ON DELETE CASCADE |
| `cantidad` | decimal(12,3) | Cantidad necesaria del ingrediente |

---

## Query de Venta (todo INNER JOIN, sin COALESCE)

```sql
SELECT go.id as grupo_id, go.nombre as grupo_nombre, go.tipo, go.obligatorio,
       go.min_seleccion, go.max_seleccion,
       o.id as opcional_id, o.nombre as opcional_nombre,
       agoo.precio_extra, agoo.disponible, agoo.activo as opcion_activa,
       ago.orden as grupo_orden, agoo.orden as opcion_orden
FROM articulo_grupo_opcional ago
JOIN grupos_opcionales go ON ago.grupo_opcional_id = go.id
JOIN articulo_grupo_opcional_opcion agoo ON ago.id = agoo.articulo_grupo_opcional_id
JOIN opcionales o ON agoo.opcional_id = o.id
WHERE ago.articulo_id = ? AND ago.sucursal_id = ?
  AND ago.activo = 1
  AND go.activo = 1
  AND o.activo = 1
  AND agoo.activo = 1
  AND agoo.disponible = 1
ORDER BY ago.orden, agoo.orden
```

---

## Pantallas UI implementadas

### `GestionarArticulos.php` — HUB CENTRAL
- CRUD de articulos con filtros, busqueda, paginacion
- Boton "Opcionales" en cada fila → modal inline (agregar/quitar/reordenar grupos)
- Boton "Receta" en cada fila → modal inline edita receta default (usa partial `_receta-editor.blade.php`)
- Badges en la tabla: cantidad de grupos opcionales y estado de receta
- Boton "Configuracion" → navega a ArticulosSucursal

### `GestionarGruposOpcionales.php` — CATALOGO GLOBAL
- NO es SucursalAware (es catalogo global)
- CRUD global + CRUD de opciones inline
- Boton "Disponibilidad" por grupo → modal con todas las sucursales × opciones, toggles disponible
- Boton "Asignar" por grupo → asignacion masiva a multiples articulos

### `GestionarRecetas.php` — TODAS LAS RECETAS GENERICAS
- Tabla con todas las recetas genericas (sucursal_id=null) de articulos y opcionales
- Filtros: busqueda morph-aware, filtro por tipo
- Editar, copiar (modal con checkboxes), nueva receta wizard (2 pasos)

### `ArticulosSucursal.php` — CONFIG POR SUCURSAL
- Usa `SucursalAware` trait (selector del header)
- modo_stock, vendible por articulo por sucursal
- Opcionales: activar/desactivar grupo, opciones individuales, precio, orden, "Restablecer defaults"
- Receta override: editar override, "Personalizar para esta sucursal", "Restablecer default"

### Partial `_receta-editor.blade.php`
Usado por GestionarArticulos, GestionarRecetas y ArticulosSucursal.
Props: `$recetaIngredientes`, `$busquedaIngrediente`, `$resultadosBusqueda`, `$recetaCantidadProducida`, `$recetaNotas`, `$recetaEsOverride`, `$recetaSucursalNombre`.

---

## Servicios

### `OpcionalService`
- `asignarGrupoAArticulo(int $articuloId, int $grupoId)` — crea filas para todas las sucursales con defaults
- `desasignarGrupoDeArticulo(int $articuloId, int $grupoId)`
- `marcarAgotado(int $opcionalId, int $sucursalId)` — `disponible=false` en todas las asignaciones de esa sucursal
- `marcarDisponible(int $opcionalId, int $sucursalId)`
- `restablecerDefaults(int $articuloGrupoOpcionalId)` — resetea precios, orden, activo
- `obtenerOpcionalesParaVenta(int $articuloId, int $sucursalId)` — query optimizada

### Resolucion de receta
- `Articulo::resolverReceta($sucursalId)` y `Opcional::resolverReceta($sucursalId)` — sucursal especifica primero, default si no hay override

---

## Tablas resultantes (9)

1. modify_articulos_table
2. modify_articulos_sucursales_table
3. create_grupos_opcionales_table
4. create_opcionales_table
5. create_articulo_grupo_opcional_table
6. create_articulo_grupo_opcional_opcion_table
7. create_recetas_table
8. create_receta_ingredientes_table
9. create_articulo_sucursal_canal_table (futuro)

---

## Modelos Laravel

### Nuevos:
- `GrupoOpcional`, `Opcional`, `ArticuloGrupoOpcional`, `ArticuloGrupoOpcionalOpcion`, `Receta` (morfable), `RecetaIngrediente`

### morphMap en AppServiceProvider:
- `'Articulo'` → `Articulo::class`
- `'Opcional'` → `Opcional::class`

---

## Flujo de Descuento de Stock en Venta

```
Venta de 2 unidades de "Hamburguesa Clasica" con "Pan de papa" (seleccionable)
y "3x Salsa BBQ" (cuantitativo):

1. Articulo segun articulos_sucursales.modo_stock:
   - 'unitario': descuenta 2 del stock de "Hamburguesa Clasica"
   - 'receta': resuelve receta → descuenta 2x ingredientes (proporcional a cantidad_producida)
   - 'ninguno': no descuenta

2. Opcional "Pan de papa" (cantidad=1 por unidad vendida):
   - Resuelve receta del opcional, descuenta 2x ingredientes

3. Opcional "Salsa BBQ" (cuantitativo, cantidad=3 por unidad):
   - Resuelve receta, descuenta 2x3 = 6x ingredientes

Los opcionales SOLO descuentan stock via receta.
Los ingredientes de las recetas SIEMPRE son articulos con stock.
```
