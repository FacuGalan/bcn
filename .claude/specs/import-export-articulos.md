# Import/Export de Artículos — Especificación

## Estado: PENDIENTE

> Spec escrito el 2026-04-17. Reutiliza el patrón implementado en el PR #41 (import/export de categorías). Requiere aprobación del usuario antes de implementar.

---

## Contexto y Motivación

El PR #41 agregó import/export en `Gestionar Categorías` usando `.xlsx` con PhpSpreadsheet. El objetivo original siempre fue llegar a **artículos**, que es donde está el verdadero volumen de carga inicial y mantenimiento. Artículos tiene dos complicaciones extra:

1. **Datos base vs por sucursal**: el artículo vive en `articulos` (datos genéricos) y tiene un pivot `articulos_sucursales` por cada sucursal donde se usa, con overrides (precio, activo, vendible, modo_stock, puntos_canje)
2. **Precios con historial**: todo cambio de precio debe registrarse en `historial_precios`

La importación opera sobre la **sucursal activa** (igual que el resto del módulo `Articulos`, que usa `SucursalAware`).

---

## Principios de Diseño

1. **Reutilizar el patrón del PR #41**: modal con plantilla vacía/con datos, ID oculto (gris), dry-run con preview, confirmación antes de persistir
2. **Precio efectivo único**: el usuario ve y edita un solo campo "Precio" — la lógica decide si es override de sucursal o si coincide con el base
3. **No registrar cambios cosméticos**: si un precio coincide con el base genérico, el override queda null y no se crea entrada en el historial
4. **Multi-sucursal transparente**: los campos del pivot (activo, vendible, modo_stock, precio) operan sobre la sucursal activa sin que el usuario tenga que saberlo
5. **Dropdowns donde haya enumerables**: Categoría, Tipo IVA, Sí/No, modo de stock — evita faltas de ortografía y coincidencias por string
6. **Soft-deleted visibles pero inmutables**: salen marcados en la exportación pero el import los ignora

---

## Requisitos Funcionales

### RF-01: Botón "Plantilla" en GestionarArticulos
- Abre modal con 2 opciones: **Plantilla vacía** / **Con datos actuales**
- "Con datos actuales" exporta todos los artículos de la sucursal activa (incluyendo inactivos y soft-deleted)
- Respeta los mismos patrones visuales del modal de categorías

### RF-02: Botón "Importar" en GestionarArticulos
- Flujo idéntico a categorías: subir archivo → preview (dry-run) → confirmar
- Preview muestra 4 métricas: **Se crearán / Se actualizarán / Sin cambios / Errores**
- El botón "Confirmar importación" se deshabilita si `creadas + actualizadas = 0`

### RF-03: Formato del archivo `.xlsx`
- 15 columnas visibles (más columna "Eliminado" solo en export con datos):

| Col | Header | Tipo | Obligatorio al crear | Va a |
|-----|--------|------|----------------------|------|
| A | ID | número, gris | no | sistema |
| B | Código | texto | no (autogenero) | `articulos.codigo` |
| C | Código de barras | texto | no | `articulos.codigo_barras` |
| D | Nombre | texto | **sí** | `articulos.nombre` |
| E | Descripción | texto | no | `articulos.descripcion` |
| F | Categoría | dropdown | **sí** | `articulos.categoria_id` (resuelto por nombre) |
| G | Unidad | texto | no (default `unidad`) | `articulos.unidad_medida` |
| H | Tipo IVA | dropdown | **sí** | `articulos.tipo_iva_id` (resuelto por nombre) |
| I | Precio IVA incluido | dropdown Sí/No | no (default Sí) | `articulos.precio_iva_incluido` |
| J | Materia prima | dropdown Sí/No | no (default No) | `articulos.es_materia_prima` |
| K | Pesable | dropdown Sí/No | no (default No) | `articulos.pesable` |
| L | Activo | dropdown Sí/No | no (default Sí) | `articulos_sucursales.activo` *de la sucursal activa* |
| M | Vendible | dropdown Sí/No | no (default Sí) | `articulos_sucursales.vendible` *de la sucursal activa* |
| N | Modo stock | dropdown (ninguno/unitario/receta) | no (default ninguno) | `articulos_sucursales.modo_stock` *de la sucursal activa* |
| O | Precio | número | no | `articulos_sucursales.precio_base` *de la sucursal activa*, con lógica de override |
| P *(solo export con datos)* | Eliminado | texto | — | informativo: "Sí" si el registro está soft-deleted |

### RF-04: Autogeneración de código
- Si la fila es nueva (sin ID) y el campo "Código" está vacío:
  - Buscar el prefijo de la `categoría` indicada
  - Con prefijo: formato `{PREFIJO}0001`, `{PREFIJO}0002`... (primer número libre)
  - Sin prefijo: formato `000001`, `000002`...
  - Reutilizar `GestionarArticulos::calcularSiguienteCodigo()` o una versión del service
- Si viene código manual: validar unicidad

### RF-05: Cambio de categoría con prefijo distinto
- Si una fila **con ID** cambia la categoría a una con prefijo distinto al actual:
  - **Regenerar el código** con el nuevo prefijo (opción B aprobada)
  - El código anterior queda libre para ser reutilizado

### RF-06: Lógica del precio efectivo

**Al exportar:**
```
precio_a_mostrar = AS.precio_base ?? Articulo.precio_base
```
(donde `AS` es `articulos_sucursales` para la sucursal activa)

**Al importar:**
```
precio_recibido = fila.precio  (puede ser null/vacío, cero, o número)

si precio_recibido == Articulo.precio_base:
    # El usuario no cambió nada respecto al base
    AS.precio_base = null
    # NO se registra en historial_precios (no hubo cambio efectivo)

sino:
    precio_efectivo_anterior = AS.precio_base ?? Articulo.precio_base

    si precio_efectivo_anterior != precio_recibido:
        AS.precio_base = precio_recibido
        historial_precios.insert({
            articulo_id,
            sucursal_id: sucursal_activa,
            precio_anterior: precio_efectivo_anterior,
            precio_nuevo: precio_recibido,
            usuario_id: user(),
            origen: 'importacion',
            porcentaje_cambio: calc(...),
            detalle: "Importado desde {nombre_archivo} por {email}"
        })
    # si precio_efectivo_anterior == precio_recibido: nada que hacer
```

**Casos concretos:**

| precio_base base | AS.precio_base antes | precio importado | AS.precio_base después | Historial |
|------------------|----------------------|------------------|------------------------|-----------|
| 100 | null | 100 (no tocó) | null | no |
| 100 | null | 150 | 150 | sí (100 → 150) |
| 100 | 120 | 120 (no tocó) | 120 | no (ya estaba en 120) |
| 100 | 120 | 150 | 150 | sí (120 → 150) |
| 100 | 120 | 100 | null (vuelve al base) | sí (120 → 100) |
| 100 | 120 | vacío | null (vuelve al base) | sí (120 → 100) |

### RF-07: Dropdowns en la plantilla
- PhpSpreadsheet soporta `DataValidation` tipo `LIST`
- Para Categoría y Tipo IVA: usar una hoja auxiliar `_datos` (oculta) con los nombres actuales
- Para Sí/No: lista inline `"Sí,No"`
- Para Modo stock: lista inline `"ninguno,unitario,receta"`

### RF-08: Artículos soft-deleted
- Al exportar con datos: incluirlos con fila en fondo rojo claro + columna "Eliminado" = "Sí"
- Al importar:
  - Si la fila tiene ID de un soft-deleted → **ignorar con error**: `"Fila X: el artículo '{nombre}' está eliminado, no se modifica"`
  - NO se restaura por import (evita restauración accidental)

### RF-09: Dry-run (preview)
- Método `ArticuloImportExportService::importar($archivo, bool $dryRun)`
- Con `$dryRun = true`:
  - Valida y cuenta todo como si fuera real
  - **NO** ejecuta `save()` ni `create()`
  - **NO** escribe en `historial_precios`
  - **NO** llama a `CatalogoCache::clear()`
- Con `$dryRun = false`: aplica cambios + registra historial + limpia cache

### RF-10: Historial de precios — nuevo origen
- El campo `historial_precios.origen` es VARCHAR 50 libre → usamos valor `'importacion'`
- `detalle` = `"Importado desde {nombre_archivo} por {email_usuario}"`
- `porcentaje_cambio` = `((nuevo - anterior) / anterior) * 100` (si anterior > 0), o `0`

### RF-11: Sucursal activa
- La importación opera sobre `sucursal_activa()`
- Si no hay sucursal activa: error claro `"Debe seleccionar una sucursal para importar"` (en teoría `SucursalAware` lo garantiza, pero defendemos)
- Cuando se crea un artículo nuevo via import: se crea el registro en `articulos_sucursales` solo para la sucursal activa (con los flags del archivo + `puntos_canje` null). Otras sucursales quedan sin registro (se pueden dar de alta después)

### RF-12: Best-effort (no transaccional)
- Como en categorías: errores fila a fila no abortan el resto
- Pero sí envolvemos **cada fila individual** en `DB::connection('pymes_tenant')->transaction()` para que articulo + AS + historial sean atómicos por fila

---

## Modelo de Datos

### Tablas modificadas
**Ninguna**. Todo lo necesario ya existe:
- `articulos` — todos los campos requeridos ya están
- `articulos_sucursales` — todos los campos requeridos ya están
- `historial_precios` — `origen` es VARCHAR, aceptamos `'importacion'` sin migración

### Constantes nuevas
En `HistorialPrecio` (o constante del service):
```php
const ORIGEN_IMPORTACION = 'importacion';
```

---

## Pantallas UI

### `Gestionar Artículos` (`/articulos`)
**Componente**: `App\Livewire\Articulos\GestionarArticulos`
**Traits**: `SucursalAware`, `WithFileUploads` *(nuevo)*
**Cambios**:
- Header: agregar botones "Plantilla" e "Importar" (desktop) / menú `⋯` (móvil) — mismo patrón que `GestionarCategorias`
- Modal de selección de plantilla (vacía / con datos)
- Modal de importación con 3 estados (selección / preview / procesado)

---

## Servicios

### `ArticuloImportExportService` — `app/Services/ArticuloImportExportService.php`

```php
public function generarPlantilla(bool $conDatos = false, ?int $sucursalId = null): string
public function importar(UploadedFile $archivo, int $sucursalId, int $usuarioId, bool $dryRun = false): array
```

**`generarPlantilla`**:
- Si `conDatos`: requiere `$sucursalId` para traer `articulos_sucursales` de esa sucursal
- Crea hoja principal `Artículos` con headers + data (si aplica)
- Crea hoja oculta `_datos` con listas para dropdowns
- Aplica `DataValidation::LIST` a las columnas F, H, I, J, K, L, M, N
- Marca filas de soft-deleted con fill rojo claro y columna "Eliminado" = "Sí"

**`importar`**:
- Retorna `array{creadas: int, actualizadas: int, sin_cambios: int, errores: array}`
- Por cada fila:
  1. Validar campos
  2. Resolver categoría (por nombre) y tipo_iva (por nombre)
  3. Según haya ID o no, ejecutar el branch correspondiente
  4. En modo normal: DB::transaction por fila para atomicidad articulo+AS+historial
  5. En modo dry-run: ejecutar lo mismo pero revertir al final (o no persistir)

**Método privado `aplicarCambioPrecio`**:
- Implementa RF-06
- Firma: `aplicarCambioPrecio(Articulo $art, ?float $precioRecibido, int $sucursalId, int $usuarioId, string $nombreArchivo, bool $dryRun): bool` → devuelve `true` si hubo cambio real

---

## Migraciones Necesarias

**Ninguna** — todas las tablas existen y el campo `origen` admite el nuevo valor.

---

## Traducciones

Estimado: ~30 claves nuevas. Ejemplos:
| Clave (es) | en | pt |
|------------|----|----|
| Importar Artículos | Import Articles | Importar Artigos |
| Fila :fila: el código ":codigo" ya existe | Row :fila: code ":codigo" already exists | Linha :fila: o código ":codigo" já existe |
| Fila :fila: categoría ":categoria" no encontrada | Row :fila: category ":categoria" not found | Linha :fila: categoria ":categoria" não encontrada |
| Fila :fila: tipo de IVA ":tipo" no encontrado | Row :fila: VAT type ":tipo" not found | Linha :fila: tipo de IVA ":tipo" não encontrado |
| Fila :fila: el artículo ":nombre" está eliminado, no se modifica | Row :fila: article ":nombre" is deleted, not modified | Linha :fila: o artigo ":nombre" está excluído, não modificado |
| Modo stock | Stock mode | Modo estoque |
| Vendible | Saleable | Vendável |
| Eliminado | Deleted | Excluído |
| Importado desde :archivo por :usuario | Imported from :archivo by :usuario | Importado de :archivo por :usuario |
| ... | ... | ... |

(lista completa se define al implementar; flujo via skill `/traducir`)

---

## Criterios de Aceptación

### Funcionales
- [ ] Plantilla vacía descarga con 15 columnas, headers estilizados, ID en gris con tooltip, dropdowns funcionando en F, H, I, J, K, L, M, N
- [ ] Plantilla con datos incluye todos los artículos de la sucursal activa, con ID y valores reales
- [ ] Soft-deleted aparecen con fondo rojo y columna "Eliminado" = "Sí"
- [ ] Importar una fila nueva sin ID crea Articulo + ArticuloSucursal con defaults correctos
- [ ] Importar una fila sin código autogenera código usando el prefijo de la categoría
- [ ] Importar una fila con ID y mismos datos no crea historial ni cuenta como actualizada (`sin_cambios`)
- [ ] Importar una fila con precio distinto al efectivo actual crea una entrada en `historial_precios` con `origen='importacion'`, detalle correcto, usuario correcto
- [ ] Importar una fila con precio igual al base genérico → override queda null + sin historial
- [ ] Importar una fila con precio vacío → override queda null + historial si había override previo
- [ ] Cambio de categoría a una con prefijo distinto regenera el código
- [ ] Soft-deleted en import reporta error, no se modifica
- [ ] Preview (dry-run) no modifica BD ni crea historial
- [ ] Confirmar importación aplica los cambios correctamente
- [ ] Filas inválidas se reportan sin abortar el resto
- [ ] `CatalogoCache::clear()` se llama solo si hubo cambios reales

### No funcionales
- [ ] Lint (`pint --test`) pasa
- [ ] Tests unitarios del service (mínimo 15 casos) pasan
- [ ] Tests feature del componente Livewire (mínimo 5 casos) pasan
- [ ] Dropdowns en xlsx abren correctamente en Excel 2016+ y LibreOffice
- [ ] Traducciones completas en es/en/pt con orden alfabético

---

## Plan de Implementación

### Fase 1: Service básico — export [PENDIENTE]
1. Crear `ArticuloImportExportService` con `generarPlantilla(bool $conDatos, ?int $sucursalId)`
2. Plantilla vacía con 15 columnas, headers, ID gris, dropdowns (categorías + tipos_iva + Sí/No + modo_stock)
3. Plantilla con datos: traer articulos + AS de la sucursal + soft-deleted marcados
4. Hoja oculta `_datos` con listas para DataValidation
5. Tests: 2-3 casos de export

### Fase 2: Service — import con lógica de precio [PENDIENTE]
1. Método `importar($archivo, $sucursalId, $usuarioId, $dryRun)`
2. Parseo del xlsx, mapeo de headers
3. Validaciones por fila (nombre, categoría resuelta, tipo_iva resuelto, código único)
4. Branch con ID: update articulo + AS, manejo de rename, cambio de categoría con regeneración de código
5. Branch sin ID: create articulo (con código autogenerado o manual) + create AS para sucursal
6. Método privado `aplicarCambioPrecio()` con la lógica de RF-06
7. Integración con `HistorialPrecio` (`origen='importacion'`, detalle con archivo y email)
8. Manejo de soft-deleted (ignorar + error)
9. `isDirty()` para distinguir `actualizadas` vs `sin_cambios`
10. Tests: 10+ casos cubriendo todos los escenarios de RF-06

### Fase 3: Componente Livewire [PENDIENTE]
1. Agregar trait `WithFileUploads` si no está
2. Propiedades: `showImportModal`, `showPlantillaModal`, `archivoImportacion`, `importacionResultado`, `importacionProcesada`, `importacionPreview`
3. Métodos: `openImportModal`, `closeImportModal`, `openPlantillaModal`, `closePlantillaModal`, `descargarPlantilla`, `previsualizarImportacion`, `confirmarImportacion`, `volverASeleccion`
4. Inyección del service + paso de `sucursal_activa()->id` y `Auth::id()`
5. Tests Livewire: render, apertura/cierre modales, validaciones

### Fase 4: Vista [PENDIENTE]
1. Botones Plantilla/Importar en header desktop
2. Menú `⋯` en móvil (si no existe, agregarlo; si ya existe, agregar items)
3. Modal de selección de plantilla (2 opciones: vacía / con datos)
4. Modal de importación con 3 estados
5. Dark mode en todo

### Fase 5: Traducciones [PENDIENTE]
1. Listar strings nuevos
2. Aplicar via `/traducir` en los 3 archivos

### Fase 6: Verificación y docs [PENDIENTE]
1. Pint, tests, browser check
2. `@docs-sync` para actualizar `manual-usuario.md` y `ai-knowledge-base.md`
3. Push al PR #41 (o PR nuevo — decidir)

---

## Notas y Decisiones

- **2026-04-17**: Spec creado basado en conversación. Usuario aprobó: columna "Activo" apunta a sucursal (no a articulo base), Vendible + Modo stock también al pivot (sin decir "sucursal" al usuario). Stock y puntos_canje excluidos. Soft-deleted ignorados en import con mensaje claro.
- **2026-04-17**: Se decidió **no** agregar un botón "Exportar" separado — la opción "Con datos actuales" del modal de Plantilla cumple esa función (misma UX que categorías, consistente).
- **2026-04-17**: Comparación de precios se hace en **valor crudo** (el usuario todavía no define semántica estable del campo "Precio IVA incluido" por fila).
- **PR**: candidato a reutilizar PR #41 (si sigue abierto) para no fragmentar. Alternativa: PR nuevo `feat/import-export-articulos` basado en `feat/import-export-categorias` una vez mergeado.
