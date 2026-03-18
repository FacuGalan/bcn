---
name: sdd-init
description: Iniciar workflow Spec-Driven Development para un nuevo feature. Usar cuando el usuario quiere planificar e implementar algo mediano/grande.
user-invocable: true
argument-hint: "[nombre-feature]"
---

# SDD Init — Iniciar Spec-Driven Development

Sos el orquestador del workflow SDD para BCN Pymes. Tu trabajo es iniciar el proceso de especificación antes de escribir código.

## Flujo SDD completo
```
/sdd-init → /sdd-explore → /sdd-spec → (aprobación) → /sdd-apply → /sdd-verify
```

## Al ejecutar este skill:

### 1. Obtener nombre del feature
- Si se pasó como argumento (`$ARGUMENTS`), usarlo
- Si no, preguntar al usuario: nombre corto y descripción breve

### 2. Verificar si ya existe un spec
- Buscar en `.claude/specs/` si existe un archivo para este feature
- Si existe, preguntar si quiere continuar desde donde quedó o empezar de nuevo

### 3. Mostrar al usuario el flujo SDD
Explicar brevemente:
1. **Explorar** (`/sdd-explore`): Analizar el codebase para entender el contexto
2. **Especificar** (`/sdd-spec`): Crear documento de especificación completo
3. **Aprobación**: El usuario revisa y aprueba el spec
4. **Implementar** (`/sdd-apply`): Implementar fase por fase según el spec
5. **Verificar** (`/sdd-verify`): Validar que se cumplan los criterios de aceptación

### 4. Preguntar cómo proceder
- Opción A: Arrancar con exploración automática (`/sdd-explore`)
- Opción B: Ir directo a especificación (`/sdd-spec`) si el usuario ya sabe lo que quiere
- Opción C: Solo crear la estructura del spec vacío para completar manualmente

### 5. Crear directorio si no existe
Verificar que `.claude/specs/` exista.

## Importante
- NO escribir código en esta fase
- NO asumir requisitos — preguntar al usuario
- El objetivo es tener un plan claro ANTES de implementar
