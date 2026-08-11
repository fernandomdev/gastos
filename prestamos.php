<?php
declare(strict_types=1);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Préstamos</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <header class="topbar">
        <div>
            <p class="eyebrow">Control financiero</p>
            <h1>Préstamos</h1>
            <p class="subtitle">Seguimiento de cuotas, progreso y pagos mensuales. Entre el día 1 y el 10 puedes marcar cada cuota como abonada.</p>
        </div>

        <div class="topbar-actions topbar-actions-stack">
            <nav class="main-nav" aria-label="Secciones">
                <a href="index.php" class="nav-link">Gastos</a>
                <a href="prestamos.php" class="nav-link is-active">Préstamos</a>
            </nav>
            <button class="btn btn-primary" id="openLoanModal">Nuevo préstamo</button>
        </div>
    </header>

    <section class="cards" id="loanSummaryCards">
        <article class="card stat-card">
            <span class="stat-label">Préstamos activos</span>
            <strong class="stat-value" id="statPrestamosActivos">0</strong>
            <small class="stat-note" id="statPrestamosTotal">0 en total</small>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Cuotas abonadas</span>
            <strong class="stat-value" id="statAbonadosMes">0</strong>
            <small class="stat-note" id="statPeriodo">—</small>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Pago mensual</span>
            <strong class="stat-value" id="statMensualTotal">Gs. 0</strong>
            <small class="stat-note">Sumando préstamos activos</small>
        </article>
        <article class="card stat-card">
            <span class="stat-label">Progreso general</span>
            <strong class="stat-value" id="statProgreso">0%</strong>
            <small class="stat-note" id="statCuotas">0 / 0 cuotas</small>
        </article>
    </section>

    <section class="loan-period-banner" id="loanPeriodBanner">
        <div>
            <strong id="periodBannerTitle">Cargando período...</strong>
            <p id="periodBannerText">Cargando información de pagos.</p>
        </div>
        <span class="period-badge" id="periodBadge">—</span>
    </section>

    <section class="card table-card">
        <div class="section-head">
            <div>
                <h2>Mis préstamos</h2>
                <p>El progreso se actualiza automáticamente cuando marcas la cuota del mes.</p>
            </div>
            <span class="chip" id="loanTableMeta">0 préstamos</span>
        </div>

        <div class="table-wrap">
            <table class="loan-table">
                <thead>
                <tr>
                    <th>Plataforma</th>
                    <th>ID</th>
                    <th>Progreso</th>
                    <th>Cuotas</th>
                    <th>Mensual</th>
                    <th>Restante</th>
                    <th>Estado</th>
                    <th class="th-action">Cuota del mes</th>
                    <th class="th-action">Acciones</th>
                </tr>
                </thead>
                <tbody id="loansBody">
                <tr>
                    <td colspan="9" class="empty-state">Cargando préstamos...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="loan-notes">
        <div class="loan-note">
            <span class="loan-note-icon">01</span>
            <div>
                <strong>Identificación segura</strong>
                <p>El ID de cada préstamo se guarda como texto de 3 dígitos, así que valores como 007 mantienen sus ceros iniciales.</p>
            </div>
        </div>
        <div class="loan-note">
            <span class="loan-note-icon">10</span>
            <div>
                <strong>Ventana de pago</strong>
                <p>El sistema permite registrar la cuota del período únicamente del día 1 al 10 y bloquea duplicados del mismo mes.</p>
            </div>
        </div>
    </section>
</div>

<div class="modal" id="loanModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-loan-modal></div>
    <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="loanModalTitle">
        <div class="modal-header">
            <div>
                <h3 id="loanModalTitle">Nuevo préstamo</h3>
                <p>Completa los datos del préstamo. Puedes indicar cuántas cuotas ya llevas pagadas.</p>
            </div>
            <button class="icon-btn" type="button" data-close-loan-modal>×</button>
        </div>

        <form id="loanForm" class="loan-form">
            <input type="hidden" id="loanId" name="id" value="">

            <div class="form-grid two-cols">
                <label class="form-field">
                    <span>Plataforma</span>
                    <select id="loanPlatform" name="plataforma" required>
                        <option value="">Seleccionar...</option>
                        <option value="Crédito Amigo">Crédito Amigo</option>
                        <option value="Ueno">Ueno</option>
                        <option value="Itaú">Itaú</option>
                    </select>
                </label>

                <label class="form-field">
                    <span>ID de la plataforma</span>
                    <input type="text" id="loanPlatformId" name="id_plataforma" inputmode="numeric" maxlength="3" minlength="3" placeholder="007" required>
                    <small>Exactamente 3 números.</small>
                </label>
            </div>

            <div class="form-grid three-cols">
                <label class="form-field">
                    <span>Cuotas totales</span>
                    <input type="number" id="loanTotal" name="cuotas_totales" min="1" max="999" step="1" required>
                </label>

                <label class="form-field">
                    <span>Cuotas cumplidas</span>
                    <input type="number" id="loanPaid" name="cuotas_cumplidas" min="0" max="999" step="1" value="0" required>
                </label>

                <label class="form-field">
                    <span>Monto mensual</span>
                    <input type="number" id="loanMonthly" name="monto_mensual" min="1" step="1" placeholder="350000" required>
                </label>
            </div>

            <div class="feedback" id="loanFeedback"></div>

            <div class="modal-actions">
                <button type="button" class="btn btn-ghost" data-close-loan-modal>Cancelar</button>
                <button type="submit" class="btn btn-primary" id="loanSubmit">Guardar préstamo</button>
            </div>
        </form>
    </div>
</div>

<script src="prestamos.js"></script>
</body>
</html>
