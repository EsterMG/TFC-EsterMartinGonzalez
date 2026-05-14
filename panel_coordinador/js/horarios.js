/* horarios.js */

// Convierte "HH:MM" a minutos desde medianoche
function hhmm(s) {
  if (!s) return 0;
  const p = s.split(':');
  return parseInt(p[0]) * 60 + parseInt(p[1]);
}

// Convierte minutos a "HH:MM" (con soporte de más de 24h)
function minHH(m) {
  return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
}

// Fecha Date - string "YYYY-MM-DD"
function isoFecha(d) {
  return d.getFullYear() + '-'
    + String(d.getMonth() + 1).padStart(2, '0') + '-'
    + String(d.getDate()).padStart(2, '0');
}

// Normaliza texto para comparar puestos (quita tildes, mayúsculas)
function normalizarTexto(s) {
  return s.toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

// Comprueba si dos puestos son equivalentes (uno contiene al otro)
function mismoPuesto(a, b) {
  const na = normalizarTexto(a), nb = normalizarTexto(b);
  return na.includes(nb) || nb.includes(na);
}

// Crea y envía un formulario POST con los campos dados
function enviarPost(campos) {
  campos.ctrl_activo = getControlActivo();
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = 'horarios.php';
  Object.entries(campos).forEach(([k, v]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = k;
    input.value = v ?? '';
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

// NAVEGACIÓN
function irDia(fecha) {
  location.href = 'horarios.php?fecha=' + fecha + '&mes=' + HOR.mesOffset;
}

function cambiarMes(delta) {
  HOR.mesOffset += delta;
  fetch('horarios_cal.php?fecha=' + HOR.fechaSel + '&mes=' + HOR.mesOffset)
    .then(r => r.json())
    .then(data => {
      const titulo = document.getElementById('cal-titulo-mes');
      if (titulo) titulo.textContent = data.titulo;

      const grid = document.querySelector('#ovCalendario .cal-grid');
      if (grid) grid.innerHTML = data.grid;

      // Actualizar el mapa de días especiales
      Object.keys(diasEspeciales).forEach(k => delete diasEspeciales[k]);
      Object.assign(diasEspeciales, data.especiales);
    })
    .catch(e => console.error('Error al cambiar mes:', e));
}

// PESTAÑAS DE CONTROL DE REALIZACIÓN (C1–C10)
function getControlActivo() {
  const tab = document.querySelector('.ctrl-tab.activa');
  return tab ? tab.dataset.ctrl : '';
}

function activarTab(ctrl) {
  document.querySelectorAll('.ctrl-tab').forEach(t =>
    t.classList.toggle('activa', t.dataset.ctrl === ctrl)
  );
  document.querySelectorAll('.g-ctrl-bloque').forEach(b => {
    b.style.display = b.dataset.ctrl === ctrl ? '' : 'none';
  });
}

// COLORES POR EMPLEADO
const PALETA_COLORES = [
  { bg: '#DBEAFE', color: '#1e40af', border: '#93C5FD' },
  { bg: '#D1FAE5', color: '#065F46', border: '#6EE7B7' },
  { bg: '#FCE7F3', color: '#831843', border: '#F9A8D4' },
  { bg: '#EDE9FE', color: '#4C1D95', border: '#C4B5FD' },
  { bg: '#FFF7ED', color: '#9A3412', border: '#FDBA74' },
  { bg: '#F0F9FF', color: '#0C4A6E', border: '#7DD3FC' },
  { bg: '#FFF1F2', color: '#9F1239', border: '#FDA4AF' },
  { bg: '#ECFEFF', color: '#164E63', border: '#67E8F9' },
  { bg: '#F0FDF4', color: '#166534', border: '#4ADE80' },
  { bg: '#F5F3FF', color: '#3730A3', border: '#A5B4FC' },
];
const mapaColores = {};
let indiceColor = 0;

function colorEmpleado(empId) {
  if (!empId || empId === '0') return null;
  if (!mapaColores[empId]) {
    mapaColores[empId] = PALETA_COLORES[indiceColor % PALETA_COLORES.length];
    indiceColor++;
  }
  return mapaColores[empId];
}

function aplicarColores() {
  document.querySelectorAll('.barra.b-cub[data-emp-id]').forEach(barra => {
    const c = colorEmpleado(barra.dataset.empId);
    if (c) {
      barra.style.background = c.bg;
      barra.style.color = c.color;
      barra.style.borderColor = c.border;
    }
  });
}

// POPUPS
function abrirPopup(id) { document.getElementById(id).classList.add('open'); }
function cerrarPopup(id) { document.getElementById(id).classList.remove('open'); }

// Popup de confirmación genérico
function confirmar(texto, btnTexto, callback) {
  document.getElementById('ovConfirmar-txt').textContent = texto;
  const btn = document.getElementById('ovConfirmar-btn');
  btn.textContent = btnTexto;
  btn.onclick = () => { cerrarConfirmar(); callback(); };
  abrirPopup('ovConfirmar');
}

function cerrarConfirmar() {
  // Restaurar iconos por si se ocultaron para el popup de cambio de horario
  const ico = document.getElementById('ovConfirmar-ico');
  const tit = document.getElementById('ovConfirmar-tit');
  if (ico) ico.style.display = '';
  if (tit) tit.style.display = '';
  cerrarPopup('ovConfirmar');
}

// Alias usado desde el HTML inline de PHP
function _cerrarConfirmar() { cerrarConfirmar(); }

// POPUP CALENDARIO
function abrirCalendario() { abrirPopup('ovCalendario'); }
function cerrarCalendario() { cerrarPopup('ovCalendario'); }


// POPUP NUEVO TURNO — Carrusel de días compartido
// Lista de todos los días del rango (3 meses atrás – 3 meses adelante)
const todosDias = (() => {
  const dias = [];
  const inicio = new Date();
  inicio.setHours(0, 0, 0, 0);
  inicio.setMonth(inicio.getMonth() - 3);
  const fin = new Date();
  fin.setMonth(fin.getMonth() + 3);
  const actual = new Date(inicio);
  while (actual <= fin) {
    dias.push(new Date(actual));
    actual.setDate(actual.getDate() + 1);
  }
  return dias;
})();

const NOMBRES_DIA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const NOMBRES_MES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

// Renderiza un carrusel de días en el contenedor dado
function renderCarrusel(contId, diasSel, offset, onToggle) {
  const cont = document.getElementById(contId);
  if (!cont) return;
  cont.innerHTML = '';
  const N = 14;
  const max = Math.max(0, todosDias.length - N);
  const off = Math.max(0, Math.min(offset, max));

  for (let i = off; i < off + N && i < todosDias.length; i++) {
    const d = todosDias[i];
    const iso = isoFecha(d);
    const dow = (d.getDay() + 6) % 7;
    const seleccionado = diasSel.has(iso);
    const finde = dow >= 5;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.style.cssText = [
      'flex:1', 'min-width:0', 'height:46px',
      'border:1px solid var(--borde)', 'border-radius:6px',
      `background:${seleccionado ? 'var(--texto)' : (finde ? '#faf8f4' : 'var(--blanco)')}`,
      `color:${seleccionado ? '#fff' : 'inherit'}`,
      'cursor:pointer', 'display:flex', 'align-items:center',
      'justify-content:center', 'flex-direction:column', 'padding:0'
    ].join(';');
    btn.innerHTML = `
            <span style="font-size:8px;opacity:.6">${NOMBRES_DIA[dow]}</span>
            <span style="font-size:11px;font-weight:700">${d.getDate()}</span>
            <span style="font-size:7px;opacity:.4">${NOMBRES_MES[d.getMonth()]}</span>
        `;
    btn.addEventListener('click', () => onToggle(iso));
    cont.appendChild(btn);
  }
}

// Modo Normal
const diasNormal = new Set();
let offsetNormal = 0;

function abrirNuevo() {
  diasNormal.clear();

  // Preseleccionar día actual
  if (HOR.fechaSel) {
    diasNormal.add(HOR.fechaSel);
    const idx = todosDias.findIndex(d => isoFecha(d) === HOR.fechaSel);
    offsetNormal = Math.max(0, idx - 3);
  } else {
    offsetNormal = 0;
  }

  actualizarNormal();
  renderCarrusel('nt-carr-dias', diasNormal, offsetNormal, iso => {
    diasNormal.has(iso) ? diasNormal.delete(iso) : diasNormal.add(iso);
    actualizarNormal();
    renderCarrusel('nt-carr-dias', diasNormal, offsetNormal, arguments.callee);
  });

  // Preseleccionar control activo
  const ctrl = getControlActivo();
  const inputCtrl = document.getElementById('nt-ctrl-activo');
  if (inputCtrl) inputCtrl.value = ctrl;
  const selectCtrl = document.getElementById('nt-control');
  if (selectCtrl && ctrl) { selectCtrl.value = ctrl; autoPlato(); }

  // Volver al modo normal al abrir
  ntCambiarModo('normal');
  abrirPopup('ovNuevo');
}

function cerrarNuevo() { cerrarPopup('ovNuevo'); }

function ntCarrusel(delta) {
  offsetNormal = Math.max(0, offsetNormal + delta);
  renderCarrusel('nt-carr-dias', diasNormal, offsetNormal, iso => {
    diasNormal.has(iso) ? diasNormal.delete(iso) : diasNormal.add(iso);
    actualizarNormal();
    ntCarrusel(0);
  });
}

function actualizarNormal() {
  const input = document.getElementById('nt-fechas-hidden');
  if (input) input.value = [...diasNormal].join(',');
  const res = document.getElementById('nt-resumen-dias');
  const n = diasNormal.size;
  if (res) res.innerHTML = n === 0 ? 'Ningún día' : `<strong>${n} día${n > 1 ? 's' : ''}</strong>`;
}

function ntSelSemana() {
  const d0 = todosDias[offsetNormal];
  const dow = (d0.getDay() + 6) % 7;
  const lunes = new Date(d0);
  lunes.setDate(d0.getDate() - dow);
  for (let j = 0; j < 7; j++) {
    const d = new Date(lunes);
    d.setDate(lunes.getDate() + j);
    diasNormal.add(isoFecha(d));
  }
  actualizarNormal();
  ntCarrusel(0);
}

function ntLimpiarDias() { diasNormal.clear(); actualizarNormal(); ntCarrusel(0); }

function autoPlato() {
  const sel = document.getElementById('nt-control');
  if (!sel) return;
  const num = sel.value.replace(/\D/g, '');
  const selPlato = document.getElementById('nt-plato');
  if (num && selPlato) {
    for (const o of selPlato.options) {
      if (o.value === 'Plato ' + num) { selPlato.value = o.value; break; }
    }
  }
}

// Alias usado desde el HTML
function ntAutoPlato() { autoPlato(); }

// Modo Avanzado (Por programa)
const diasAvanzado = new Set();
let offsetAvanzado = 0;
const empleadosSelAvanzado = new Set();

function ntCambiarModo(modo) {
  const esAvanzado = modo === 'avanzado';
  document.getElementById('nt-panel-normal').style.display = esAvanzado ? 'none' : 'block';
  document.getElementById('nt-panel-avanzado').style.display = esAvanzado ? 'block' : 'none';
  document.getElementById('nt-tab-normal').style.borderBottomColor = esAvanzado ? 'transparent' : 'var(--texto)';
  document.getElementById('nt-tab-normal').style.color = esAvanzado ? 'var(--suave)' : 'var(--texto)';
  document.getElementById('nt-tab-avanzado').style.borderBottomColor = esAvanzado ? 'var(--texto)' : 'transparent';
  document.getElementById('nt-tab-avanzado').style.color = esAvanzado ? 'var(--texto)' : 'var(--suave)';

  if (esAvanzado) {
    offsetAvanzado = Math.max(0, todosDias.findIndex(d => isoFecha(d) === HOR.fechaSel) - 3);
    ntAvRenderDias();
    const ctrl = getControlActivo();
    const sel = document.getElementById('nt-av-control');
    if (sel && ctrl) sel.value = ctrl;
    const inputCtrl = document.getElementById('nt-av-ctrl-activo');
    if (inputCtrl) inputCtrl.value = ctrl;
  }
}

async function ntAvCargarInfo() {
  const sel = document.getElementById('nt-av-prog');
  if (!sel.value) return;
  const opt = sel.options[sel.selectedIndex];

  // Rellenar horario del programa
  const ini = opt.dataset.ini;
  const fin = opt.dataset.fin;
  if (ini && ini !== '00:00') document.getElementById('nt-av-ini').value = ini;
  if (fin && fin !== '00:00') document.getElementById('nt-av-fin').value = fin;

  // Marcar empleados del programa automáticamente
  try {
    const fd = new FormData();
    fd.append('accion', 'info_programa');
    fd.append('programa_id', sel.value);
    const r = await fetch('horarios_ajax.php', { method: 'POST', body: fd });
    const data = await r.json();
    ntAvDeselTodos();
    if (data.empleados?.length > 0) {
      data.empleados.forEach(emp => {
        const el = document.querySelector(`.nt-av-emp-opt[data-id="${emp.id}"]`);
        if (el && !el.classList.contains('nt-av-sel')) ntAvToggleEmp(el);
      });
    }
  } catch (e) {
    console.error('Error al cargar empleados del programa:', e);
  }
}

function ntAvToggleEmp(el) {
  const id = el.dataset.id;
  const check = el.querySelector('.nt-av-emp-check');
  if (empleadosSelAvanzado.has(id)) {
    empleadosSelAvanzado.delete(id);
    el.classList.remove('nt-av-sel');
    el.style.background = '';
    check.textContent = '';
    check.style.background = 'var(--blanco)';
    check.style.borderColor = 'var(--borde)';
  } else {
    empleadosSelAvanzado.add(id);
    el.classList.add('nt-av-sel');
    el.style.background = 'rgba(26,26,26,0.06)';
    check.textContent = '✓';
    check.style.background = 'var(--texto)';
    check.style.color = '#fff';
    check.style.borderColor = 'var(--texto)';
  }
  actualizarEmpsAvanzado();
}

function ntAvSelTodos() {
  document.querySelectorAll('.nt-av-emp-opt').forEach(el => {
    if (!el.classList.contains('nt-av-sel')) ntAvToggleEmp(el);
  });
}

function ntAvDeselTodos() {
  document.querySelectorAll('.nt-av-emp-opt.nt-av-sel').forEach(el => ntAvToggleEmp(el));
}

function actualizarEmpsAvanzado() {
  const input = document.getElementById('nt-av-empleados-hidden');
  if (input) input.value = [...empleadosSelAvanzado].join(',');
  const res = document.getElementById('nt-av-emp-resumen');
  const n = empleadosSelAvanzado.size;
  if (res) res.textContent = n === 0 ? '0 seleccionados' : `${n} seleccionado${n > 1 ? 's' : ''}`;
}

function ntAvRenderDias() {
  renderCarrusel('nt-av-carr-dias', diasAvanzado, offsetAvanzado, iso => {
    diasAvanzado.has(iso) ? diasAvanzado.delete(iso) : diasAvanzado.add(iso);
    actualizarAvanzado();
    ntAvRenderDias();
  });
}

function ntAvCarrusel(delta) { offsetAvanzado = Math.max(0, offsetAvanzado + delta); ntAvRenderDias(); }

function ntAvSelSemana() {
  const d0 = todosDias[offsetAvanzado];
  const dow = (d0.getDay() + 6) % 7;
  const lunes = new Date(d0);
  lunes.setDate(d0.getDate() - dow);
  for (let j = 0; j < 7; j++) {
    const d = new Date(lunes);
    d.setDate(lunes.getDate() + j);
    diasAvanzado.add(isoFecha(d));
  }
  actualizarAvanzado();
  ntAvRenderDias();
}

function ntAvLimpiarDias() { diasAvanzado.clear(); actualizarAvanzado(); ntAvRenderDias(); }

function actualizarAvanzado() {
  const input = document.getElementById('nt-av-fechas-hidden');
  if (input) input.value = [...diasAvanzado].join(',');
  const res = document.getElementById('nt-av-resumen-dias');
  const n = diasAvanzado.size;
  if (res) res.innerHTML = n === 0 ? 'Ningún día' : `<strong>${n} día${n > 1 ? 's' : ''}</strong>`;
}

// POPUP ASIGNAR / EDITAR TURNO
let ctxAsignar = {};
let empleadoSeleccionado = null;
let cambiosPendientes = {};

function abrirAsignar(puesto, ctrl, turnoId, solId, puestoSolId, ini, fin, cubierto, empNombre) {
  ctxAsignar = {
    puesto, ctrl,
    turnoId: +turnoId || 0,
    solId: +solId || 0,
    puestoSolId: +puestoSolId || 0,
    ini, fin,
    desdeDrag: false,
    empNombre: empNombre || ''
  };
  empleadoSeleccionado = null;
  cambiosPendientes = {};

  document.getElementById('as-tit').textContent = puesto;
  document.getElementById('as-sub').textContent = ctrl + (ini ? ' · ' + ini + '–' + fin : '');

  if (cubierto) mostrarModoEditar(empNombre, puesto, ini, fin);
  else mostrarModoAnadir(ini, fin, puesto);

  abrirPopup('ovAsignar');
}

function mostrarModoAnadir(ini, fin, puesto) {
  document.getElementById('as-add').style.display = 'block';
  document.getElementById('as-edit').style.display = 'none';
  document.getElementById('as-ini').value = ini || '';
  document.getElementById('as-fin').value = fin || '';
  construirListaEmpleados('as-lista', puesto);
  const btnBorrar = document.getElementById('as-add-borrar');
  if (btnBorrar) btnBorrar.style.display = ctxAsignar.turnoId > 0 ? 'block' : 'none';
}

function mostrarModoEditar(empNombre, puesto, ini, fin) {
  document.getElementById('as-add').style.display = 'none';
  document.getElementById('as-edit').style.display = 'block';
  document.getElementById('as-edit-trabajador').style.display = 'none';
  document.getElementById('as-edit-horario').style.display = 'none';
  document.getElementById('as-edit-nombre').textContent = empNombre || 'Trabajador';
  document.getElementById('as-edit-puesto').textContent = puesto;
  document.getElementById('as-edit-horas').textContent = ini + ' – ' + fin;
  document.getElementById('as-edit-ini').value = ini;
  document.getElementById('as-edit-fin').value = fin;
}

function construirListaEmpleados(listaId, puesto) {
  const empleadosFiltrados = HOR.empleados
    .filter(e => mismoPuesto(puesto, e.puesto))
    .sort((a, b) => {
      if (a.ocupado !== b.ocupado) return a.ocupado ? 1 : -1;
      return a.nombre.localeCompare(b.nombre);
    });

  const lista = document.getElementById(listaId);
  lista.innerHTML = '';
  empleadoSeleccionado = null;

  if (!empleadosFiltrados.length) {
    lista.innerHTML = '<div style="font-size:11px;color:var(--suave);padding:8px 0">Sin empleados para este puesto.</div>';
    return;
  }

  empleadosFiltrados.forEach(e => {
    const c = colorEmpleado(String(e.id));
    const div = document.createElement('div');
    div.className = 'wk-opt' + (e.ocupado ? ' wk-busy' : '');

    const dot = c
      ? `<span style="width:10px;height:10px;border-radius:50%;background:${c.border};display:inline-block;flex-shrink:0"></span>`
      : `<div class="wk-dot ${e.ocupado ? '' : 'libre'}"></div>`;

    div.innerHTML = dot
      + `<span>${e.nombre}</span>`
      + (e.ocupado ? '<span class="wk-badge">tiene turno</span>' : '');

    div.addEventListener('click', () => {
      lista.querySelectorAll('.wk-opt').forEach(o => o.classList.remove('wk-sel'));
      div.classList.add('wk-sel');
      empleadoSeleccionado = e.id;
    });
    lista.appendChild(div);
  });
}

// Alias usados desde el HTML
function buildLista(puesto) { construirListaEmpleados('as-lista', puesto); }

function confirmarAsignacion() {
  if (!empleadoSeleccionado) { alert('Selecciona un trabajador.'); return; }
  const ini = document.getElementById('as-ini').value;
  const fin = document.getElementById('as-fin').value;
  if (!ini || !fin) { alert('Indica el horario.'); return; }

  if (ctxAsignar.desdeDrag) {
    cerrarPopup('ovAsignar');

    // Buscar el bloque del control que contiene este horario
    const barrasBloque = document.querySelectorAll(
      `.g-ctrl-bloque[data-ctrl="${ctxAsignar.ctrl}"] .b-prog[data-bloque-id]`
    );

    let bloqueId = null;
    const iniMin = hhmm(ini);
    let finMin = hhmm(fin);
    if (finMin <= iniMin) finMin += 1440;

    barrasBloque.forEach(b => {
      const bIni = hhmm(b.dataset.ini);
      let bFin = hhmm(b.dataset.fin);
      if (bFin <= bIni) bFin += 1440;
      if (iniMin >= bIni && finMin <= bFin) bloqueId = b.dataset.bloqueId;
    });

    // Si no hay coincidencia exacta, usar el primer bloque disponible
    if (!bloqueId && barrasBloque.length > 0) {
      bloqueId = barrasBloque[0].dataset.bloqueId;
    }

    if (bloqueId) {
      enviarPost({
        accion: 'crear_y_asignar_bloque',
        bloque_id: bloqueId,
        empleado_id: empleadoSeleccionado,
        puesto_solicitado: ctxAsignar.puesto,
        hora_inicio: ini,
        hora_fin: fin,
        fecha_ctx: HOR.fechaSel,
        mes_ctx: HOR.mesOffset
      });
    } else {
      // Sin bloque: no debería ocurrir, pero como fallback abre contexto
      dragPendiente = { puesto: ctxAsignar.puesto, ctrl: ctxAsignar.ctrl, ini, fin, empId: empleadoSeleccionado };
      abrirContextoDrag();
    }
    return;
  }

  // Asignación normal (no drag)
  const campos = ctxAsignar.solId > 0
    ? { accion: 'asignar_sol', solicitud_id: ctxAsignar.solId, puesto_id: ctxAsignar.puestoSolId, empleado_id: empleadoSeleccionado, fecha_ctx: HOR.fechaSel, mes_ctx: HOR.mesOffset }
    : { accion: 'asignar_empleado', turno_id: ctxAsignar.turnoId, empleado_id: empleadoSeleccionado, fecha_ctx: HOR.fechaSel, mes_ctx: HOR.mesOffset };
  enviarPost(campos);
}

function cerrarAsignar() { cerrarPopup('ovAsignar'); }

function editarCampo(cual) {
  const elTrabajador = document.getElementById('as-edit-trabajador');
  const elHorario = document.getElementById('as-edit-horario');

  if (cual === 'nombre') {
    const abierto = elTrabajador.style.display === 'block';
    elTrabajador.style.display = abierto ? 'none' : 'block';
    elHorario.style.display = 'none';
    if (!abierto) {
      construirListaEmpleados('as-lista-edit', ctxAsignar.puesto);
      // Sobreescribir el click para también actualizar el nombre visible
      document.querySelectorAll('#as-lista-edit .wk-opt').forEach(div => {
        div.addEventListener('click', () => {
          document.getElementById('as-edit-nombre').textContent =
            div.querySelector('span:nth-child(2)')?.textContent || '';
          cambiosPendientes.empleado_id = empleadoSeleccionado;
        });
      });
    }
  } else {
    const abierto = elHorario.style.display === 'block';
    elHorario.style.display = abierto ? 'none' : 'block';
    elTrabajador.style.display = 'none';
  }
}

function guardarCambios() {
  const ini = document.getElementById('as-edit-ini').value;
  const fin = document.getElementById('as-edit-fin').value;
  if (ini && fin) { cambiosPendientes.hora_inicio = ini; cambiosPendientes.hora_fin = fin; }

  document.getElementById('as-edit-trabajador').style.display = 'none';
  document.getElementById('as-edit-horario').style.display = 'none';

  if (!Object.keys(cambiosPendientes).length) { cerrarAsignar(); return; }

  enviarPost({
    accion: 'editar_turno',
    turno_id: ctxAsignar.turnoId,
    hora_inicio: ini || ctxAsignar.ini,
    hora_fin: fin || ctxAsignar.fin,
    fecha_ctx: HOR.fechaSel,
    mes_ctx: HOR.mesOffset,
    ...cambiosPendientes
  });
  cambiosPendientes = {};
}

function limpiarControl(ctrl) {
  confirmar(
    '¿Borrar todos los turnos y bloques de ' + ctrl + ' en este día?',
    'Sí, limpiar todo',
    () => enviarPost({ accion: 'limpiar_control', ctrl_nombre: ctrl, fecha_ctx: HOR.fechaSel, mes_ctx: HOR.mesOffset })
  );
}

function borrarTurnoCtx() {
  const txt = ctxAsignar.empNombre
    ? 'Turno de ' + ctxAsignar.empNombre + ' · ' + ctxAsignar.ini + '–' + ctxAsignar.fin
    : 'Turno sin asignar · ' + (ctxAsignar.ini || '') + '–' + (ctxAsignar.fin || '');
  confirmar(txt, 'Sí, borrar', () => enviarPost({
    accion: 'borrar_turno',
    turno_id: ctxAsignar.turnoId,
    fecha_ctx: HOR.fechaSel,
    mes_ctx: HOR.mesOffset
  }));
}

// POPUP EDITAR BLOQUE
function abrirEditBloque(bloqueId, ctrl, ini, fin, progId) {
  document.getElementById('eb-titulo').textContent = ctrl;
  document.getElementById('eb-bloque-id').value = bloqueId;
  document.getElementById('eb-ctrl-activo').value = ctrl;
  document.getElementById('cb-bloque-id').value = bloqueId;
  document.getElementById('cb-ctrl-activo').value = ctrl;
  document.getElementById('eb-prog').value = progId > 0 ? String(progId) : '0';
  document.getElementById('eb-ini').value = ini;
  document.getElementById('eb-fin').value = fin;
  abrirPopup('ovEditBloque');
}

function borrarBloqueCtx() {
  cerrarPopup('ovEditBloque');
  abrirPopup('ovConfirmarBloque');
}

// Abre el modal de nuevo turno preseleccionando el control clicado
function abrirNuevoConCtrl(ctrl) {
  abrirNuevo();
  setTimeout(() => {
    const sel = document.getElementById('nt-control');
    if (sel) { sel.value = ctrl; autoPlato(); }
  }, 50);
}

// DRAG - CREAR TURNO arrastrando celda vacia
const SNAP_MIN = 15; // snap a 15 minutos

(function iniciaDragCeldaVacia() {
  let arrastrando = false, celda = null, xInicio = null, preview = null;

  function pxAMin(cel, px) {
    const rect = cel.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (px - rect.left) / rect.width));
    return Math.round((parseInt(cel.dataset.slotIni) + ratio * parseInt(cel.dataset.ventana)) / SNAP_MIN) * SNAP_MIN;
  }

  function pct(cel, m) {
    return Math.max(0, Math.min(100, (m - parseInt(cel.dataset.slotIni)) / parseInt(cel.dataset.ventana) * 100));
  }

  function mostrarPreview(cel, m0, m1) {
    if (!preview) { preview = document.createElement('div'); preview.className = 'g-drag-preview'; }
    if (preview.parentElement !== cel) { preview.remove(); cel.appendChild(preview); }
    preview.style.left = pct(cel, Math.min(m0, m1)) + '%';
    preview.style.width = (pct(cel, Math.max(m0, m1)) - pct(cel, Math.min(m0, m1))) + '%';
  }

  function ocultarPreview() {
    if (preview) { preview.remove(); preview = null; }
  }

  document.addEventListener('mousedown', e => {
    if (e.target.closest('.barra-handle')) return;
    const cel = e.target.closest('.g-fila-p .gcel');
    if (!cel || e.target.closest('.barra')) return;
    arrastrando = true; celda = cel; xInicio = e.clientX;
    mostrarPreview(cel, pxAMin(cel, xInicio), pxAMin(cel, xInicio));
    e.preventDefault();
  });

  document.addEventListener('mousemove', e => {
    if (!arrastrando || !celda) return;
    mostrarPreview(celda, pxAMin(celda, xInicio), pxAMin(celda, e.clientX));
  });

  document.addEventListener('mouseup', e => {
    if (!arrastrando || !celda) return;
    arrastrando = false;
    const m0 = pxAMin(celda, xInicio), m1 = pxAMin(celda, e.clientX);
    ocultarPreview();
    if (Math.abs(m1 - m0) < SNAP_MIN) { celda = null; return; }

    const ini = minHH(Math.min(m0, m1)), fin = minHH(Math.max(m0, m1));
    const puesto = celda.dataset.puesto, ctrl = celda.dataset.ctrl;
    celda = null;

    ctxAsignar = { puesto, ctrl, turnoId: 0, solId: 0, puestoSolId: 0, ini, fin, desdeDrag: true };
    empleadoSeleccionado = null; cambiosPendientes = {};
    document.getElementById('as-tit').textContent = puesto;
    document.getElementById('as-sub').textContent = ctrl + ' · ' + ini + '–' + fin;
    mostrarModoAnadir(ini, fin, puesto);
    abrirPopup('ovAsignar');
  });
})();

// RESIZE - ARRASTRAR EXTREMOS DE UNA BARRA PARA CAMBIAR HORARIO
(function iniciaResizeBarra() {
  let activo = false, lado = null, barraEl = null, celdaEl = null, minAnclado = 0;

  function pxAMin(cel, px) {
    const rect = cel.getBoundingClientRect();
    const ratio = Math.max(0, Math.min(1, (px - rect.left) / rect.width));
    return Math.round((parseInt(cel.dataset.slotIni) + ratio * parseInt(cel.dataset.ventana)) / SNAP_MIN) * SNAP_MIN;
  }

  function pct(cel, m) {
    return Math.max(0, Math.min(100, (m - parseInt(cel.dataset.slotIni)) / parseInt(cel.dataset.ventana) * 100));
  }

  document.addEventListener('mousedown', e => {
    const handle = e.target.closest('.barra-handle');
    if (!handle) return;
    e.stopPropagation(); e.preventDefault();

    activo = true;
    lado = handle.dataset.lado;
    barraEl = handle.closest('.barra');
    celdaEl = barraEl.closest('.gcel');

    const slotIni = parseInt(celdaEl.dataset.slotIni);
    let iniM = hhmm(barraEl.dataset.ini || '00:00');
    let finM = hhmm(barraEl.dataset.fin || '00:00');
    if (finM <= iniM) finM += 1440;
    if (iniM < slotIni - 30) iniM += 1440;
    if (finM < slotIni - 30) finM += 1440;

    minAnclado = lado === 'ini' ? finM : iniM;
    barraEl._iniOrig = minHH(iniM % 1440);
    barraEl._finOrig = minHH(finM % 1440);
    barraEl.style.opacity = '0.6';
  });

  document.addEventListener('mousemove', e => {
    if (!activo || !celdaEl) return;
    const cur = pxAMin(celdaEl, e.clientX);
    const newIni = lado === 'ini' ? cur : minAnclado;
    const newFin = lado === 'fin' ? cur : minAnclado;
    if (newFin <= newIni) return;
    barraEl.style.left = pct(celdaEl, newIni) + '%';
    barraEl.style.width = (pct(celdaEl, newFin) - pct(celdaEl, newIni)) + '%';
    const txt = barraEl.querySelector('.barra-txt');
    if (txt) txt.textContent = minHH(newIni % 1440) + '–' + minHH(newFin % 1440);
  });

  document.addEventListener('mouseup', e => {
    if (!activo || !celdaEl) return;
    activo = false;
    barraEl.style.opacity = '';

    const cur = pxAMin(celdaEl, e.clientX);
    const newIni = lado === 'ini' ? cur : minAnclado;
    const newFin = lado === 'fin' ? cur : minAnclado;
    const turnoId = barraEl.dataset.turnoId;
    const iniOrig = barraEl._iniOrig || '';
    const finOrig = barraEl._finOrig || '';
    celdaEl = null; barraEl = null;

    if (newFin <= newIni || !turnoId || turnoId === '0') return;

    const iniStr = minHH(newIni % 1440);
    const finStr = minHH(newFin % 1440);

    // Mostrar popup de confirmación con el cambio de horario
    setTimeout(() => {
      document.getElementById('ovConfirmar-ico').style.display = 'none';
      document.getElementById('ovConfirmar-tit').style.display = 'none';
      document.getElementById('ovConfirmar-txt').innerHTML = `
                <div style="margin-bottom:14px">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#999;margin-bottom:4px">Horario anterior</div>
                    <div style="font-size:16px;font-weight:700;color:var(--texto)">${iniOrig} – ${finOrig}</div>
                </div>
                <div>
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#999;margin-bottom:4px">Nuevo horario</div>
                    <div style="font-size:20px;font-weight:700;color:#1a5fa8">${iniStr} – ${finStr}</div>
                </div>`;
      const btn = document.getElementById('ovConfirmar-btn');
      btn.textContent = 'Confirmar cambio';
      btn.onclick = () => {
        cerrarConfirmar();
        enviarPost({ accion: 'editar_turno', turno_id: turnoId, hora_inicio: iniStr, hora_fin: finStr, fecha_ctx: HOR.fechaSel, mes_ctx: HOR.mesOffset });
      };
      abrirPopup('ovConfirmar');
    }, 10);
  });
})();

// POPUP CONTEXTO DRAG (fallback sin bloque)
let dragPendiente = null;

function abrirContextoDrag() {
  const num = dragPendiente?.ctrl?.replace(/\D/g, '') || '';
  const sel = document.getElementById('ctx-plato');
  for (const o of sel.options) {
    if (o.value === 'Plato ' + num) { sel.value = o.value; break; }
  }
  abrirPopup('ovContexto');
}

function cerrarContexto() { cerrarPopup('ovContexto'); }

function confirmarContexto() {
  const prog = document.getElementById('ctx-prog').value;
  const plato = document.getElementById('ctx-plato').value;
  if (!prog) { alert('Selecciona un programa.'); return; }
  if (!plato) { alert('Selecciona un plato.'); return; }
  const d = dragPendiente;
  enviarPost({
    accion: 'crear_y_asignar',
    empleado_id: d.empId,
    programa_id: prog,
    control_nombre: d.ctrl,
    plato,
    puesto_solicitado: d.puesto,
    hora_inicio: d.ini,
    hora_fin: d.fin,
    fecha_ctx: HOR.fechaSel,
    mes_ctx: HOR.mesOffset
  });
}

// INICIALIZACIÓN
document.addEventListener('DOMContentLoaded', () => {
  // Cerrar popups al hacer clic en el fondo oscuro
  ['ovNuevo', 'ovAsignar', 'ovContexto', 'ovConfirmar', 'ovEditCtrl', 'ovCalendario'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.remove('open'); });
  });

  // Activar el control indicado en la URL, o el primero disponible
  const ctrlEnUrl = new URLSearchParams(location.search).get('ctrl');
  const primerTab = document.querySelector('.ctrl-tab');
  if (ctrlEnUrl) activarTab(ctrlEnUrl);
  else if (primerTab) activarTab(primerTab.dataset.ctrl);

  // Aplicar colores a las barras de empleados
  aplicarColores();
});