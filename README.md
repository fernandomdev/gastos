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
