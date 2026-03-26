# Artículos Sucursal-Aware - Especificación

## Estado: EN PROGRESO

> Refactorizar GestionarArticulos para que sea sucursal-aware, absorbiendo la funcionalidad de ArticulosSucursal (stock, precio efectivo, recetas override, opcionales override). Luego eliminar ArticulosSucursal.

---

## Contexto y Motivación

Actualmente hay dos pantallas para gestionar artículos:
- **GestionarArticulos**: catálogo global, NO sucursal-aware. Muestra datos base, receta default, asignación de opcionales.
- **ArticulosSucursal**: configuración por sucursal. Muestra stock, precio override, receta override, opcionales override.

El usuario debe navegar entre ambas pantallas para gestionar un artículo completo, lo cual es confuso y poco práctico. Se quiere unificar en una sola pantalla que muestre directamente los datos de la sucursal activa, manteniendo el botón de crear artículo y el modal de edición de datos base.

El componente ArticulosSucursal se eliminará después de la migración.

---

## Principios de Diseño

1. **Sucursal-first**: El listado muestra la realidad de la sucursal activa (stock, precio efectivo, recetas, opcionales)
2. **Override transparente**: El usuario edita datos de la sucursal sin saber si es un override o un default — si no existe override, se crea automáticamente al guardar
3. **Datos base accesibles**: El modal de edición de datos base (nombre, categoría, IVA, etc.) se mantiene para edición rápida
4. **Sin edición inline**: Todo desde modales, manteniendo la tabla limpia
5. **Crear = sucursal activa**: Al crear un artículo se activa solo en la sucursal activa, pero se crean registros inactivos en las demás para futura activación

---

## Requisitos Funcionales

### RF-01: Listado sucursal-aware
- Solo mostrar artículos activos en la sucursal activa (`articulos_sucursales.activo = true`)
- Columnas: código, nombre, categoría, precio efectivo, stock, estado receta, cant. grupos opcionales
- Precio efectivo = `articulos_sucursales.precio_base` si existe, sino `articulos.precio_base`
- Stock = `stock.cantidad` de la sucursal activa (si `modo_stock != 'ninguno'`)
- Implementar trait `SucursalAware` — al cambiar sucursal se refresca el listado
- Mantener búsqueda, filtros por categoría y paginación

### RF-02: Modal de edición de datos base (existente)
- Se mantiene tal cual: edita `articulos.nombre`, `articulos.precio_base`, categoría, IVA, código, descripción, activo, etiquetas
- Al crear artículo nuevo:
  - Se crea registro en `articulos`
  - Se crean registros en `articulos_sucursales` para TODAS las sucursales: activo=true solo para sucursal activa, activo=false para las demás
  - Si modo_stock != 'ninguno', crear registro en `stock` para la sucursal activa
- Ya NO muestra selector de sucursales en el modal de creación

### RF-03: Modal de configuración de sucursal (nuevo)
- Accesible desde botón en cada fila del listado (icono engranaje / "Configurar")
- Campos editables:
  - **Precio base sucursal**: input numérico. Si vacío = usa precio base genérico. Mostrar placeholder con el precio genérico.
  - **Modo stock**: select (ninguno, unitario, receta)
  - **Vendible**: toggle (si aparece en pantalla de ventas)
- Al guardar actualiza `articulos_sucursales`

### RF-04: Modal de receta (refactorizado)
- Al abrir:
  - Si existe override de sucursal (`recetas` con `sucursal_id = activa`): cargar para edición
  - Si no existe override pero hay default (`sucursal_id = null`): copiar ingredientes del default a un nuevo override y abrir para edición
  - Si no hay ninguna receta: abrir formulario vacío para crear override
- Edición: agregar/quitar/modificar ingredientes (cantidad, unidad, artículo materia prima)
- Al guardar: siempre guarda como override de sucursal (`recetas.sucursal_id = activa`)
- Botón "Eliminar receta": elimina el override de esta sucursal. Si había default, vuelve a tomarlo.
- Botón "Anular receta": crea override con `activo = false` (la sucursal no produce este artículo aunque haya default)
- NO mostrar textos como "override", "default", "restablecer". Solo "Receta" como título.

### RF-05: Modal de opcionales (refactorizado)
- Dos secciones en el mismo modal:
  - **Sección superior: Grupos asignados**: lista de grupos opcionales asignados a este artículo en esta sucursal
    - Por cada grupo: nombre, mínimo, máximo, opciones con toggle activo/disponible y precio_extra editable
    - Botón para desasignar grupo
  - **Sección inferior: Asignar grupo**: selector de grupos opcionales disponibles (no asignados aún) + botón asignar
- Al asignar un grupo: crea registro en `articulo_grupo_opcional` con `sucursal_id = activa` y crea las opciones correspondientes en `articulo_grupo_opcional_opcion`
- Al desasignar: elimina el registro de `articulo_grupo_opcional` (cascade elimina opciones)
- Edición de opciones (toggle activo, disponible, precio_extra): actualiza `articulo_grupo_opcional_opcion`
- Todo escoped a la sucursal activa

### RF-06: Crear artículo
- Modal de creación se mantiene con los mismos campos (nombre, código, categoría, IVA, precio_base, etc.)
- Ya NO muestra checkboxes de sucursales
- Al guardar:
  - Crea `articulos` con datos ingresados
  - Crea `articulos_sucursales` para CADA sucursal del comercio:
    - Sucursal activa: `activo=true`, `modo_stock` según el artículo, `vendible=true`
    - Resto: `activo=false`, `modo_stock='ninguno'`, `vendible=true`
  - Si modo_stock != 'ninguno', crea registro `stock` con `cantidad=0` para sucursal activa

### RF-07: Eliminar ArticulosSucursal
- Eliminar `app/Livewire/Configuracion/ArticulosSucursal.php`
- Eliminar `resources/views/livewire/configuracion/articulos-sucursal.blade.php`
- Eliminar ruta correspondiente
- Eliminar item de menú (si aplica, o mantener ruta apuntando al nuevo componente)

---

## Modelo de Datos

### Tablas modificadas

No se requieren cambios de schema. Las tablas existentes ya soportan todo:

- `articulos` — datos base (sin cambios)
- `articulos_sucursales` — override por sucursal: activo, modo_stock, vendible, precio_base (sin cambios)
- `stock` — stock por artículo/sucursal (sin cambios)
- `recetas` — tiene `sucursal_id` nullable (null=default, valor=override) (sin cambios)
- `receta_ingredientes` — ingredientes de receta (sin cambios)
- `articulo_grupo_opcional` — tiene `sucursal_id` (sin cambios)
- `articulo_grupo_opcional_opcion` — opciones con activo, disponible, precio_extra (sin cambios)

### Migraciones necesarias

Ninguna. El schema actual soporta todos los requisitos.

---

## Pantallas UI

### Pantalla: Listado de Artículos (`/articulos`)
**Componente**: `App\Livewire\Articulos\GestionarArticulos`
**Traits**: `SucursalAware`, `WithPagination`

**Listado (tabla desktop / cards mobile)**:
| Columna | Origen |
|---------|--------|
| Código | `articulos.codigo` |
| Nombre | `articulos.nombre` |
| Categoría | `articulos.categoria_id` → `categorias.nombre` |
| Precio | `articulos_sucursales.precio_base` ?? `articulos.precio_base` |
| Stock | `stock.cantidad` (si modo_stock != ninguno) |
| Receta | badge si tiene receta activa en sucursal |
| Opcionales | count de grupos asignados en sucursal |
| Acciones | Editar, Configurar, Receta, Opcionales, Historial precios, Eliminar |

**Modales**:
1. Crear/Editar artículo (datos base) — existente, modificar creación
2. Configurar sucursal (nuevo) — precio, modo_stock, vendible
3. Receta (refactorizado) — edición directa override sucursal
4. Opcionales (refactorizado) — asignar grupos + configurar opciones
5. Historial de precios — existente, sin cambios

---

## Servicios

No se crean services nuevos. Los cambios son en el componente Livewire directamente, usando los modelos existentes (`Articulo`, `Receta`, `RecetaIngrediente`, `ArticuloGrupoOpcional`, `ArticuloGrupoOpcionalOpcion`, `Stock`).

Se reutiliza lógica existente de `ArticulosSucursal` trasladándola al componente refactorizado.

---

## Traducciones

Claves nuevas estimadas (se definirán durante implementación):

| Clave (es) | en | pt |
|------------|----|----|
| Configurar sucursal | Configure branch | Configurar filial |
| Precio sucursal | Branch price | Preço filial |
| Usar precio genérico | Use generic price | Usar preço genérico |
| Eliminar receta | Delete recipe | Excluir receita |
| Anular receta | Cancel recipe | Anular receita |
| Asignar grupo | Assign group | Atribuir grupo |
| Desasignar grupo | Unassign group | Desatribuir grupo |

---

## Criterios de Aceptación

- [ ] El listado muestra solo artículos activos en la sucursal activa
- [ ] Al cambiar de sucursal, el listado se refresca correctamente
- [ ] El precio mostrado es el efectivo de la sucursal (override > base)
- [ ] El stock de la sucursal se muestra en el listado
- [ ] El modal de receta edita directamente el override de sucursal sin mencionar "override"/"default"
- [ ] Si no existe override de receta, al abrir se copia el default automáticamente para edición
- [ ] El modal de opcionales permite asignar/desasignar grupos por sucursal
- [ ] El modal de opcionales permite editar activo, disponible y precio_extra por opción
- [ ] Crear artículo lo activa solo en la sucursal activa y crea registros inactivos en las demás
- [ ] El modal de crear ya no muestra selector de sucursales
- [ ] El modal de editar datos base sigue funcionando igual
- [ ] El componente ArticulosSucursal y su vista están eliminados
- [ ] La ruta de ArticulosSucursal redirige o se elimina

---

## Plan de Implementación

### Fase 1: Hacer GestionarArticulos sucursal-aware [COMPLETO]
1. Agregar trait `SucursalAware` al componente
2. Refactorizar query del listado: filtrar por `articulos_sucursales.activo = true` en sucursal activa
3. Incluir precio efectivo (join/subquery con `articulos_sucursales`)
4. Incluir stock de la sucursal
5. Actualizar vista blade: columnas precio efectivo y stock
6. Implementar `onSucursalChanged()`: resetear filtros y paginación

### Fase 2: Modal de configuración de sucursal [COMPLETO]
1. Agregar propiedades y modal para configurar artículo en sucursal
2. Campos: precio_base sucursal, modo_stock, vendible
3. Guardar en `articulos_sucursales` con historial de precios
4. Botón "Configurar" en listado (mobile + desktop)

### Fase 3: Refactorizar modal de receta [COMPLETO]
1. Lógica de receta override ya implementada en GestionarArticulos
2. Al abrir: cargar override si existe, sino copiar default, sino vacío
3. Guardar siempre como override (`sucursal_id = activa`)
4. Botones: eliminar override, anular receta
5. Partial `_receta-editor.blade.php` reutilizable

### Fase 4: Refactorizar modal de opcionales [COMPLETO]
1. Sección de grupos asignados con reordenamiento + sección asignar nuevo grupo
2. Lógica de opcionales ya scoped a sucursal activa
3. Asignar/desasignar grupos con OpcionalService
4. Submodal de confirmación de desasignación

### Fase 5: Refactorizar creación de artículos [COMPLETO]
1. Eliminado selector de sucursales del modal de creación
2. Al crear: activar solo en sucursal activa, crear registros inactivos en las demás
3. Auto-crear stock solo en sucursal activa si modo_stock != 'ninguno'
4. En edición: no tocar config por sucursal (se maneja desde modal dedicado)

### Fase 6: Eliminar ArticulosSucursal y cleanup [COMPLETO]
1. Eliminado componente `ArticulosSucursal.php` y vista blade
2. Eliminada ruta `configuracion.articulos-sucursal`
3. Limpiado `MenuItem.php` (rutas relacionadas)
4. Limpiado import en `routes/web.php`
5. Agregadas traducciones en 3 idiomas (es, en, pt)

---

## Notas y Decisiones

- 2026-03-25: El usuario decidió que el listado muestre solo artículos activos en la sucursal (no todos con indicador)
- 2026-03-25: No se usa edición inline en la tabla, todo desde modales
- 2026-03-25: Al crear artículo se crean registros `articulos_sucursales` para TODAS las sucursales (activo=false) para permitir activación futura desde el componente de gestión base
- 2026-03-25: El modal de datos base (nombre, categoría, IVA) se mantiene en este componente
- 2026-03-25: No se requieren migraciones — el schema actual soporta todo
- 2026-03-25: No se crean services nuevos — la lógica se mueve directamente al componente
