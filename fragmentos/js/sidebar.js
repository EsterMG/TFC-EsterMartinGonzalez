document.addEventListener('DOMContentLoaded', function () {

    // ── LOGOUT ──
    var btn = document.getElementById('btn-logout');
    var overlay = document.getElementById('modal-logout');
    var btnCancelar = document.querySelector('.btn-modal-cancelar');
    var btnConfirmar = document.querySelector('.btn-modal-confirmar');

    if (btn && overlay) {
        btn.addEventListener('click', function () {
            overlay.classList.add('activo');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.classList.remove('activo');
        });
    }

    if (btnCancelar) {
        btnCancelar.addEventListener('click', function () {
            overlay.classList.remove('activo');
        });
    }

    if (btnConfirmar) {
        btnConfirmar.addEventListener('click', function (e) {
            e.preventDefault();
            var nombre = btn.dataset.nombre;
            var box = overlay.querySelector('.modal-box');
            box.innerHTML = `
        <div class="modal-icono">👋</div>
        <h3 class="modal-titulo">¡Hasta pronto, ${nombre}!</h3>
        <p class="modal-desc">Cerrando sesión…</p>
      `;
            setTimeout(function () {
                window.location.href = btnConfirmar.getAttribute('href');
            }, 1500);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') overlay.classList.remove('activo');
    });

    // ── HAMBURGUESA ──
    var btnMenu = document.getElementById('btn-menu');
    var sidebarOverlay = document.getElementById('sidebar-overlay');
    var sidebar = document.querySelector('.sidebar');

    if (btnMenu) {
        btnMenu.addEventListener('click', function () {
            sidebar.classList.toggle('abierto');
            sidebarOverlay.classList.toggle('visible');
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            sidebar.classList.remove('abierto');
            sidebarOverlay.classList.remove('visible');
        });
    }

});