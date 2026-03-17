# {Nombre del Feature} - Especificación

## Estado: PENDIENTE

> Resumen breve del estado actual

---

## Contexto y Motivación

¿Por qué se necesita este feature? ¿Qué problema resuelve?

---

## Principios de Diseño

1. {Principio 1}
2. {Principio 2}

---

## Requisitos Funcionales

### RF-01: {Nombre}
- Descripción
- Condiciones

### RF-02: {Nombre}
- ...

---

## Modelo de Datos

### Tablas nuevas

#### `{nombre_tabla}`
| Campo | Tipo | Default | Descripción |
|-------|------|---------|-------------|
| `id` | bigint PK | auto | |
| `sucursal_id` | bigint unsigned | — | FK a sucursales |
| ... | ... | ... | ... |

### Tablas modificadas

#### `{nombre_tabla}` — Cambios
- Agregar: `{campo}` ({tipo}, {default}) AFTER `{campo_existente}`

---

## Pantallas UI

### Pantalla 1: {Nombre} (`/{ruta}`)
**Componente**: `App\Livewire\Modulo\Componente`
**Traits**: SucursalAware | CajaAware | ninguno
- Funcionalidad 1
- Funcionalidad 2

---

## Servicios

### `{NombreService}` — `app/Services/{NombreService}.php`
- `metodo1($params)`: descripción
- `metodo2($params)`: descripción

---

## Migraciones Necesarias

1. `add_campo_to_tabla` — Agregar columna X a tabla Y
2. `create_nueva_tabla` — Crear tabla Z

---

## Traducciones

Claves nuevas necesarias:
| Clave (es) | en | pt |
|------------|----|----|
| ... | ... | ... |

---

## Criterios de Aceptación

- [ ] Criterio 1
- [ ] Criterio 2
- [ ] Criterio 3

---

## Plan de Implementación

### Fase 1: {Nombre} [PENDIENTE]
1. Paso 1
2. Paso 2

### Fase 2: {Nombre} [PENDIENTE]
1. ...

---

## Notas y Decisiones

- {Fecha}: {Decisión tomada y justificación}
