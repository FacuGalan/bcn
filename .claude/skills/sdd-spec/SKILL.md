---
name: sdd-spec
description: Crear especificación completa para un feature. Genera documento en .claude/specs/ siguiendo el template del proyecto.
user-invocable: true
argument-hint: "[nombre-feature]"
---

# SDD Spec — Crear Especificación

Tu trabajo es crear un documento de especificación completo para un feature de BCN Pymes, dialogando con el usuario para definir cada sección.

## Al ejecutar este skill:

### 1. Preparación
- Leer el template: `.claude/docs/spec-template.md`
- Si existe un spec previo en `.claude/specs/`, leerlo como base
- Si se corrió `/sdd-explore` antes, usar los hallazgos como contexto
- Leer `.claude/docs/servicios-referencia.md` para conocer lo que ya existe

### 2. Dialogar con el usuario para cada sección

Recorrer las secciones del template en orden, preguntando al usuario:

**Contexto y Motivación**: ¿Qué problema resuelve? ¿Por qué ahora?

**Principios de Diseño**: Proponer principios basados en los patrones del proyecto (multi-tenant, sucursal-aware, services pattern, etc.) y validar con el usuario.

**Requisitos Funcionales**: Listar los RF numerados. Preguntar qué debe hacer el feature específicamente.

**Modelo de Datos**: Proponer tablas nuevas/modificadas basándose en los patrones existentes. Validar:
- ¿Tabla tenant o compartida?
- ¿Necesita `sucursal_id`?
- ¿Foreign keys a tablas existentes?
- Tipos de datos y defaults

**Pantallas UI**: Definir componentes Livewire necesarios:
- ¿Sucursal-aware o global?
- ¿Qué modales necesita?
- ¿Paginación?

**Servicios**: Definir métodos necesarios en services nuevos o existentes.

**Migraciones**: Listar las migraciones necesarias en orden.

**Traducciones**: Identificar claves de texto nuevas (es/en/pt).

**Criterios de Aceptación**: Definir criterios verificables.

**Plan de Implementación**: Dividir en fases ordenadas lógicamente (BD → Models → Services → UI).

### 3. Escribir el spec
- Crear archivo: `.claude/specs/{nombre-feature-kebab-case}.md`
- Seguir formato exacto del template
- Marcar estado: `EN REVISIÓN`

### 4. Pedir aprobación
- Mostrar resumen al usuario
- Indicar que revise el spec y apruebe antes de pasar a `/sdd-apply`

## Reglas
- NO escribir código de implementación
- NO asumir — preguntar todo lo que no esté claro
- Ser específico en el modelo de datos (tipos, defaults, constraints)
- Respetar patrones existentes del proyecto (multi-tenant, ledger, services)
- Ejemplo real de spec: ver `~/.claude/projects/.../memory/recetas-opcionales-design.md`
