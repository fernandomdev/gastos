(() => {
    const API_URL = 'prestamos_api.php';

    const els = {
        loanModal: document.getElementById('loanModal'),
        loanForm: document.getElementById('loanForm'),
        loanFeedback: document.getElementById('loanFeedback'),
        loanSubmit: document.getElementById('loanSubmit'),
        openLoanModal: document.getElementById('openLoanModal'),
        loanModalTitle: document.getElementById('loanModalTitle'),
        loanId: document.getElementById('loanId'),
        loanPlatform: document.getElementById('loanPlatform'),
        loanPlatformId: document.getElementById('loanPlatformId'),
        loanTotal: document.getElementById('loanTotal'),
        loanPaid: document.getElementById('loanPaid'),
        loanMonthly: document.getElementById('loanMonthly'),
        loansBody: document.getElementById('loansBody'),
        loanTableMeta: document.getElementById('loanTableMeta'),
        statPrestamosActivos: document.getElementById('statPrestamosActivos'),
        statPrestamosTotal: document.getElementById('statPrestamosTotal'),
        statAbonadosMes: document.getElementById('statAbonadosMes'),
        statPeriodo: document.getElementById('statPeriodo'),
        statMensualTotal: document.getElementById('statMensualTotal'),
        statProgreso: document.getElementById('statProgreso'),
        statCuotas: document.getElementById('statCuotas'),
        periodBannerTitle: document.getElementById('periodBannerTitle'),
        periodBannerText: document.getElementById('periodBannerText'),
        periodBadge: document.getElementById('periodBadge'),
        loanPeriodBanner: document.getElementById('loanPeriodBanner'),
    };

    let loans = [];

    const moneyFormatter = new Intl.NumberFormat('es-PY', {
        style: 'currency',
        currency: 'PYG',
        maximumFractionDigits: 0,
    });

    const numberFormatter = new Intl.NumberFormat('es-PY');

    function money(value) {
        const num = Number(value || 0);
        return moneyFormatter.format(Number.isFinite(num) ? num : 0);
    }

    function number(value) {
        const num = Number(value || 0);
        return numberFormatter.format(Number.isFinite(num) ? num : 0);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function setFeedback(message, type = '') {
        els.loanFeedback.textContent = message || '';
        els.loanFeedback.className = `feedback ${type}`.trim();
    }

    function resetLoanForm() {
        els.loanForm.reset();
        els.loanId.value = '';
        els.loanPaid.value = '0';
        els.loanModalTitle.textContent = 'Nuevo préstamo';
        els.loanSubmit.textContent = 'Guardar préstamo';
        setFeedback('');
    }

    function openLoanModal(loan = null) {
        resetLoanForm();

        if (loan) {
            els.loanModalTitle.textContent = 'Editar préstamo';
            els.loanSubmit.textContent = 'Guardar cambios';
            els.loanId.value = loan.id;
            els.loanPlatform.value = loan.plataforma;
            els.loanPlatformId.value = loan.id_plataforma;
            els.loanTotal.value = loan.cuotas_totales;
            els.loanPaid.value = loan.cuotas_cumplidas;
            els.loanMonthly.value = Math.round(Number(loan.monto_mensual || 0));
        }

        els.loanModal.classList.add('is-open');
        els.loanModal.setAttribute('aria-hidden', 'false');
        els.loanPlatform.focus();
    }

    function closeLoanModal() {
        els.loanModal.classList.remove('is-open');
        els.loanModal.setAttribute('aria-hidden', 'true');
        resetLoanForm();
    }

    function statusClass(loan) {
        if (loan.accion_mes === 'done') return 'loan-status-done';
        if (loan.accion_mes === 'paid') return 'loan-status-paid';
        if (loan.accion_mes === 'closed') return 'loan-status-warning';
        return 'loan-status-pending';
    }

    function actionButton(loan) {
        if (loan.accion_mes === 'done') {
            return '<span class="loan-action-muted">Completado</span>';
        }

        if (loan.accion_mes === 'paid') {
            return `<button class="btn btn-small btn-paid" type="button" data-unmark="${loan.id}">✓ Abonada</button>`;
        }

        if (loan.accion_mes === 'mark') {
            return `<button class="btn btn-small btn-primary" type="button" data-mark="${loan.id}">Marcar abonada</button>`;
        }

        return '<span class="loan-action-muted">Fuera de plazo</span>';
    }

    function renderRows() {
        els.loanTableMeta.textContent = `${number(loans.length)} ${loans.length === 1 ? 'préstamo' : 'préstamos'}`;

        if (!loans.length) {
            els.loansBody.innerHTML = `
                <tr>
                    <td colspan="9" class="empty-state">
                        Todavía no tienes préstamos cargados. Usa <strong>Nuevo préstamo</strong> para agregar el primero.
                    </td>
                </tr>
            `;
            return;
        }

        els.loansBody.innerHTML = loans.map((loan) => {
            const progress = Math.max(0, Math.min(100, Number(loan.progreso || 0)));
            const completed = Number(loan.cuotas_cumplidas || 0);
            const total = Number(loan.cuotas_totales || 0);

            return `
                <tr>
                    <td>
                        <div class="platform-cell">
                            <span class="platform-dot platform-${platformClass(loan.plataforma)}"></span>
                            <strong>${escapeHtml(loan.plataforma)}</strong>
                        </div>
                    </td>
                    <td><span class="loan-id-badge">${escapeHtml(loan.id_plataforma)}</span></td>
                    <td class="progress-cell">
                        <div class="progress-label">
                            <strong>${progress.toLocaleString('es-PY', { maximumFractionDigits: 1 })}%</strong>
                            <span>${number(completed)} de ${number(total)}</span>
                        </div>
                        <div class="progress-track">
                            <span class="progress-value" style="width: ${progress}%"></span>
                        </div>
                    </td>
                    <td class="col-center">
                        <strong>${number(completed)}</strong>
                        <span class="cell-muted">/ ${number(total)}</span>
                    </td>
                    <td class="col-right"><strong>${money(loan.monto_mensual)}</strong></td>
                    <td class="col-right">
                        <strong>${number(loan.cuotas_restantes)}</strong>
                        <span class="cell-muted">cuotas</span>
                    </td>
                    <td><span class="loan-status ${statusClass(loan)}">${escapeHtml(loan.estado)}</span></td>
                    <td class="th-action">${actionButton(loan)}</td>
                    <td class="actions-cell">
                        <button class="icon-btn icon-btn-small" type="button" title="Editar préstamo" aria-label="Editar préstamo" data-edit="${loan.id}">✎</button>
                        <button class="icon-btn icon-btn-small danger" type="button" title="Eliminar préstamo" aria-label="Eliminar préstamo" data-delete="${loan.id}">×</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function platformClass(platform) {
        const value = String(platform || '').toLowerCase();
        if (value.includes('ueno')) return 'ueno';
        if (value.includes('ita')) return 'itau';
        return 'credito';
    }

    function renderSummary(data) {
        els.statPrestamosActivos.textContent = number(data.prestamos_activos);
        els.statPrestamosTotal.textContent = `${number(data.total_prestamos)} en total`;
        els.statAbonadosMes.textContent = `${number(data.abonados_mes)} / ${number(data.prestamos_activos)}`;
        els.statPeriodo.textContent = `Período ${escapeHtml(data.periodo_actual || '—')}`;
        els.statMensualTotal.textContent = money(data.monto_mensual_total);
        els.statProgreso.textContent = `${Number(data.progreso_general || 0).toLocaleString('es-PY', { maximumFractionDigits: 1 })}%`;
        els.statCuotas.textContent = `${number(data.cuotas_cumplidas)} / ${number(data.cuotas_totales)} cuotas`;

        const day = Number(data.dia_actual || 0);
        const open = Boolean(data.ventana_pago_abierta);
        els.loanPeriodBanner.classList.toggle('is-open-period', open);
        els.loanPeriodBanner.classList.toggle('is-closed-period', !open);

        if (open) {
            els.periodBannerTitle.textContent = `Período de pago ${data.periodo_actual}`;
            els.periodBannerText.textContent = `Hoy es día ${day}. Puedes marcar la cuota de cada préstamo hasta el día 10.`;
            els.periodBadge.textContent = `Día ${day} · abierto`;
        } else if (day > 10) {
            els.periodBannerTitle.textContent = `Período ${data.periodo_actual} cerrado`;
            els.periodBannerText.textContent = `Hoy es día ${day}. El sistema ya no permite registrar la cuota de este mes.`;
            els.periodBadge.textContent = `Día ${day} · cerrado`;
        } else {
            els.periodBannerTitle.textContent = `El período ${data.periodo_actual} todavía no comenzó`;
            els.periodBannerText.textContent = `Podrás marcar las cuotas a partir del día 1.`;
            els.periodBadge.textContent = `Día ${day}`;
        }
    }

    async function fetchDashboard() {
        const response = await fetch(`${API_URL}?action=dashboard`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (!data.ok) {
            throw new Error(data.mensaje || 'No se pudo cargar la información de préstamos.');
        }

        loans = Array.isArray(data.prestamos) ? data.prestamos : [];
        renderSummary(data);
        renderRows();
    }

    async function request(action, payload = {}) {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action, ...payload }),
        });

        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.mensaje || 'No se pudo completar la operación.');
        }
        return data;
    }

    function getLoan(id) {
        return loans.find((loan) => Number(loan.id) === Number(id));
    }

    async function submitLoan(event) {
        event.preventDefault();
        setFeedback('Guardando...', '');
        els.loanSubmit.disabled = true;

        const payload = {
            id: els.loanId.value || undefined,
            plataforma: els.loanPlatform.value,
            id_plataforma: els.loanPlatformId.value.trim(),
            cuotas_totales: Number(els.loanTotal.value),
            cuotas_cumplidas: Number(els.loanPaid.value),
            monto_mensual: Number(els.loanMonthly.value),
        };

        const action = els.loanId.value ? 'update' : 'create';

        try {
            const result = await request(action, payload);
            setFeedback(result.mensaje || 'Guardado correctamente.', 'success');
            await fetchDashboard();
            setTimeout(closeLoanModal, 450);
        } catch (error) {
            setFeedback(error.message || 'Ocurrió un error.', 'error');
        } finally {
            els.loanSubmit.disabled = false;
        }
    }

    async function markPaid(id) {
        const loan = getLoan(id);
        if (!loan) return;

        const ok = window.confirm(`¿Confirmas que ya abonaste la cuota de ${loan.plataforma} ${loan.id_plataforma} del período ${loan.periodo_actual}?`);
        if (!ok) return;

        try {
            await request('mark_paid', { id: Number(id) });
            await fetchDashboard();
        } catch (error) {
            window.alert(error.message || 'No se pudo marcar la cuota.');
        }
    }

    async function unmarkPaid(id) {
        const loan = getLoan(id);
        if (!loan) return;

        const ok = window.confirm(`¿Quieres revertir la cuota de ${loan.plataforma} ${loan.id_plataforma} del período ${loan.periodo_actual}?`);
        if (!ok) return;

        try {
            await request('unmark_paid', { id: Number(id) });
            await fetchDashboard();
        } catch (error) {
            window.alert(error.message || 'No se pudo revertir la cuota.');
        }
    }

    async function deleteLoan(id) {
        const loan = getLoan(id);
        if (!loan) return;

        const ok = window.confirm(`¿Eliminar el préstamo ${loan.plataforma} ${loan.id_plataforma}? También se eliminará su historial de cuotas registradas.`);
        if (!ok) return;

        try {
            await request('delete', { id: Number(id) });
            await fetchDashboard();
        } catch (error) {
            window.alert(error.message || 'No se pudo eliminar el préstamo.');
        }
    }

    els.loanForm.addEventListener('submit', submitLoan);
    els.openLoanModal.addEventListener('click', () => openLoanModal());

    document.querySelectorAll('[data-close-loan-modal]').forEach((button) => {
        button.addEventListener('click', closeLoanModal);
    });

    els.loanModal.addEventListener('click', (event) => {
        if (event.target === els.loanModal) {
            closeLoanModal();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && els.loanModal.classList.contains('is-open')) {
            closeLoanModal();
        }
    });

    els.loanPlatformId.addEventListener('input', () => {
        els.loanPlatformId.value = els.loanPlatformId.value.replace(/\D/g, '').slice(0, 3);
    });

    els.loanTotal.addEventListener('input', () => {
        els.loanPaid.max = els.loanTotal.value || 1;
        if (Number(els.loanPaid.value) > Number(els.loanTotal.value)) {
            els.loanPaid.value = els.loanTotal.value;
        }
    });

    els.loansBody.addEventListener('click', (event) => {
        const markButton = event.target.closest('[data-mark]');
        if (markButton) {
            markPaid(markButton.dataset.mark);
            return;
        }

        const unmarkButton = event.target.closest('[data-unmark]');
        if (unmarkButton) {
            unmarkPaid(unmarkButton.dataset.unmark);
            return;
        }

        const editButton = event.target.closest('[data-edit]');
        if (editButton) {
            const loan = getLoan(editButton.dataset.edit);
            if (loan) openLoanModal(loan);
            return;
        }

        const deleteButton = event.target.closest('[data-delete]');
        if (deleteButton) {
            deleteLoan(deleteButton.dataset.delete);
        }
    });

    fetchDashboard().catch((error) => {
        console.error(error);
        els.loansBody.innerHTML = `
            <tr>
                <td colspan="9" class="empty-state">${escapeHtml(error.message || 'Error al cargar los préstamos.')}</td>
            </tr>
        `;
        els.periodBannerTitle.textContent = 'No se pudo cargar la información';
        els.periodBannerText.textContent = error.message || 'Error inesperado.';
    });
})();
