# Próximos pasos de traducción

## Contexto
El sistema ya tiene TODOS los textos envueltos en __() (1,883 claves).
El archivo lang/es.json ya existe con todas las claves mapeadas a español.
El archivo lang/es/pagination.php también está listo.

## Tarea pendiente
Crear traducciones completas para:

### 1. Inglés (en)
- Crear `lang/en.json` traduciendo las 1,883 claves de es.json al inglés
- Crear `lang/en/pagination.php`

### 2. Portugués (pt)
- Crear `lang/pt.json` traduciendo las 1,883 claves de es.json al portugués
- Crear `lang/pt/pagination.php`

### 3. Selector de idioma en la UI
- Agregar selector de idioma en el dropdown del usuario o en configuración
- Guardar preferencia de idioma por usuario (campo `locale` en tabla users)
- Aplicar el locale en cada request (middleware)

## Archivos de referencia
- `lang/es.json` - archivo base con todas las claves
- `lang/es/pagination.php` - paginación en español
- `TRANSLATION_PROGRESS.md` - lista completa de archivos procesados
