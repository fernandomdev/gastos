<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gastos</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>
    <div class="app-shell">
        <header class="topbar">
            <div>
                <p class="eyebrow">Control de gastos</p>
                <h1>Gastos</h1>
                <p class="subtitle">Tabla general de movimientos y comparativa mensual del saldo acumulado al día de hoy.</p>
            </div>

            <div class="topbar-actions">
                <button class="btn btn-primary" id="openImportModal">Importar archivo</button>
            </div>
        </header>

        <section class="cards" id="summaryCards">
            <article class="card stat-card">
                <span class="stat-label">Movimientos</span>
                <strong class="stat-value" id="statMovimientos">0</strong>
            </article>
            <article class="card stat-card">
                <span class="stat-label">Gastos</span>
                <strong class="stat-value" id="statGastos">Gs. 0</strong>
            </article>
            <article class="card stat-card">
                <span class="stat-label">Ingresos</span>
                <strong class="stat-value" id="statIngresos">Gs. 0</strong>
            </article>
            <article class="card stat-card">
                <span class="stat-label">Saldo neto</span>
                <strong class="stat-value" id="statSaldo">Gs. 0</strong>
            </article>
        </section>

        <section class="card chart-card">
            <div class="section-head">
                <div>
                    <h2>Saldo acumulado por mes</h2>
                    <p>Comparación de cuánto dinero tenías cada mes, tomando el día del mes actual como referencia.</p>
                </div>
                <span class="chip" id="chartMeta">—</span>
            </div>
            <div class="chart-wrap">
                <canvas id="balanceChart"></canvas>
            </div>
        </section>

        <section class="card table-card">
            <div class="section-head">
                <div>
                    <h2>Todos los registros</h2>
                    <p>Datos guardados en MySQL, ordenados del más reciente al más antiguo.</p>
                </div>
                <span class="chip" id="tableMeta">0 registros</span>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Comprobante</th>
                            <th>Descripción</th>
                            <th>Categoría</th>
                            <th>Débito</th>
                            <th>Crédito</th>
                            <th>Neto</th>
                        </tr>
                    </thead>
                    <tbody id="movimientosBody">
                        <tr>
                            <td colspan="7" class="empty-state">Cargando datos...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="modal" id="importModal" aria-hidden="true">
        <div class="modal-backdrop" data-close-modal></div>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="importModalTitle">
            <div class="modal-header">
                <div>
                    <h3 id="importModalTitle">Importar CSV</h3>
                    <p>Sube uno o varios archivos CSV para guardarlos en la base de datos.</p>
                </div>
                <button class="icon-btn" type="button" data-close-modal>×</button>
            </div>

            <form id="importForm" class="import-form">
                <label class="file-drop">
                    <input type="file" id="files" name="archivos[]" accept=".csv,text/csv" multiple required>
                    <span class="file-drop-text">
                        <strong>Haz clic para elegir archivos</strong>
                        <small>Formato esperado: FECHA;NRO_COMPROBANTE;DESCRIPCION;MONEDA;DEBITO;CREDITO</small>
                    </span>
                </label>

                <div class="modal-actions">
                    <button type="button" class="btn btn-ghost" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="importSubmit">Subir e importar</button>
                </div>

                <div class="feedback" id="importFeedback"></div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>
