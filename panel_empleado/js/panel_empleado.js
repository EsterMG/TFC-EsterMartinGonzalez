// panel_empleado.js

function formatSQL(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}

document.addEventListener('DOMContentLoaded', function () {

    flatpickr('#campo-rango-panel', {
        mode: 'range',
        locale: 'es',
        minDate: 'today',
        dateFormat: 'd/m/Y',

        onChange: function (selectedDates) {
            const inicio = document.getElementById('panel-inicio');
            const fin = document.getElementById('panel-fin');
            const badge = document.getElementById('dias-badge-panel');

            if (selectedDates.length >= 1) {
                inicio.value = formatSQL(selectedDates[0]);
            }

            if (selectedDates.length === 2) {
                fin.value = formatSQL(selectedDates[1]);
            } else {
                fin.value = selectedDates.length === 1
                    ? formatSQL(selectedDates[0])
                    : '';
            }

            if (selectedDates.length >= 1) {
                const dias = selectedDates.length === 2
                    ? Math.round((selectedDates[1] - selectedDates[0]) / 86400000) + 1
                    : 1;

                badge.textContent = dias + (dias === 1 ? ' día' : ' días');
            } else {
                badge.textContent = '';
            }
        }
    });

});