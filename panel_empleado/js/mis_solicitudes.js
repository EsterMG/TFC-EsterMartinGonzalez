let overlay;
let fp = null;

document.addEventListener('DOMContentLoaded', () => {
    overlay = document.getElementById('modal-overlay');
});

function iniciarFlatpickr(inicioVal, finVal) {
    if (fp) fp.destroy();

    fp = flatpickr('#campo-rango', {
        mode: 'range',
        locale: 'es',
        minDate: 'today',
        dateFormat: 'd/m/Y',
        defaultDate: inicioVal && finVal ? [inicioVal, finVal] : [],
        onChange: function (selectedDates) {
            const inicio = document.getElementById('modal-inicio');
            const fin = document.getElementById('modal-fin');

            if (selectedDates.length >= 1) {
                inicio.value = formatoSQL(selectedDates[0]);
            }

            if (selectedDates.length === 2) {
                fin.value = formatoSQL(selectedDates[1]);
            } else if (selectedDates.length === 1) {
                fin.value = formatoSQL(selectedDates[0]);
            }

            actualizarBadge(selectedDates);
        }
    });
}

function formatoSQL(date) {
    return date.toISOString().split('T')[0];
}

function actualizarBadge(dates) {
    const badge = document.getElementById('dias-badge');

    if (dates.length === 2) {
        const diff = Math.round((dates[1] - dates[0]) / 86400000) + 1;
        badge.textContent = diff + " días";
    } else if (dates.length === 1) {
        badge.textContent = "1 día";
    } else {
        badge.textContent = "";
    }
}

function abrirModal() {
    document.getElementById('modal-titulo').textContent = 'Nueva solicitud';
    document.getElementById('form-accion').value = 'nueva';
    document.getElementById('form-id').value = '';

    overlay.style.display = 'flex';
    iniciarFlatpickr('', '');
}

function abrirModalEditar(id, tipo, inicio, fin, motivo) {
    document.getElementById('modal-titulo').textContent = 'Editar solicitud';
    document.getElementById('form-accion').value = 'guardar_edicion';
    document.getElementById('form-id').value = id;

    document.getElementById('modal-tipo').value = tipo;
    document.getElementById('modal-motivo').value = motivo;

    overlay.style.display = 'flex';
    iniciarFlatpickr(inicio, fin);
}

function cerrarModal() {
    overlay.style.display = 'none';
    if (fp) fp.destroy();
}

overlay?.addEventListener('click', (e) => {
    if (e.target === overlay) cerrarModal();
});