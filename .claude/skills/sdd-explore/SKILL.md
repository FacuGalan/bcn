---
name: sdd-explore
description: Explorar el codebase antes de especificar un feature. Identifica código relacionado, tablas, modelos y services afectados.
user-invocable: true
argument-hint: "[nombre-feature]"
context: fork
agent: Explore
---

# SDD Explore — Exploración del Codebase

Sos un agente explorador. Tu trabajo es analizar el codebase de BCN Pymes para entender el contexto antes de especificar un feature.

## Al ejecutar este skill:

### 1. Entender el feature
- Leer el argumento `$ARGUMENTS` como nombre/descripción del feature
- Si existe un spec en `.claude/specs/`, leer su sección "Contexto y Motivación"

### 2. Explorar el codebase
Buscar código relacionado en estas áreas:

**Base de datos:**
- Tablas existentes que se relacionen (buscar en `database/sql/tenant_tables.sql`)
- Migraciones recientes que toquen tablas similares
- Modelos existentes en `app/Models/`

**Lógica de negocio:**
- Services en `app/Services/` que se relacionen
- Patrones ya implementados que se puedan reutilizar

**UI:**
- Componentes Livewire existentes similares en `app/Livewire/`
- Vistas Blade en `resources/views/livewire/`

**Rutas y menú:**
- Rutas existentes en `routes/web.php`
- Estructura de menú actual (buscar en `pymes.menu_items`)

**Traducciones:**
- Claves existentes en `lang/es.json` relacionadas

### 3. Generar reporte de hallazgos

Formato del reporte:
```
## Exploración: {nombre-feature}

### Código relacionado encontrado
- Modelos: {lista}
- Services: {lista}
- Componentes: {lista}
- Tablas: {lista}

### Patrones reutilizables
- {patrón 1}: cómo se implementó en {componente existente}
- {patrón 2}: ...

### Dependencias identificadas
- {feature} depende de {otro componente/tabla/service}

### Enfoque sugerido
- Opción A: {descripción}
- Opción B: {descripción}
- Recomendación: {cuál y por qué}

### Archivos que se modificarían
- {lista de archivos probables}
```

## Reglas
- NO modificar ningún archivo
- NO escribir código
- Ser exhaustivo en la exploración
- Leer `.claude/docs/servicios-referencia.md` para entender la estructura actual
