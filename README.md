# Gastos

Mini app en PHP + MySQL para:
- importar CSV de movimientos
- ver todos los registros en una tabla
- comparar el saldo acumulado por mes en un gráfico lineal
- subir archivos desde un popup

## Archivos
- `index.php`: vista principal
- `style.css`: estilos
- `script.js`: lógica frontend
- `importar.php`: API + importador
- `schema.sql`: base de datos

## Uso
1. Crear la base de datos ejecutando `schema.sql`
2. Ajustar credenciales en `importar.php`
3. Abrir `index.php`
4. Importar los CSV desde el botón

## Módulo de préstamos
- `prestamos.php`: página de seguimiento de préstamos.
- `prestamos.js`: listado, progreso, alta/edición y registro de cuotas.
- `prestamos_api.php`: API de préstamos.
- `prestamos.sql`: migración SQL para la base existente.

Los IDs de plataforma se guardan como `CHAR(3)` para conservar ceros iniciales (por ejemplo `007`). El registro de la cuota del período está protegido con una restricción única para evitar duplicados y solo se permite del día 1 al 10.
