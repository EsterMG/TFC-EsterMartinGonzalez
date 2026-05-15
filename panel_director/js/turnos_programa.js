// turnos_programa.js

const {
  horaIniProg,
  horaFinProg,
  mesOffset,
  reabrirForm,
  errorMsg,
  errorFields,
} = window.APP_CONFIG;

// SELECTOR DE HORARIO
function crearSelector({
  gridId,
  inpIniId,
  inpFinId,
  durId,
  hiddenIniId,
  hiddenFinId,
  def_ini,
  def_fin,
}) {
  const SLOTS = 49;
  let sIni = -1,
    sFin = -1,
    drag = false;

  const grid = document.getElementById(gridId);
  const inpI = document.getElementById(inpIniId);
  const inpF = document.getElementById(inpFinId);
  const durEl = document.getElementById(durId);

  function s2l(s) {
    const m = s * 30;
    const h = Math.floor(m / 60) % 24;
    const mn = m % 60;
    return String(h).padStart(2, "0") + ":" + String(mn).padStart(2, "0");
  }

  function s2d(s) {
    const m = s * 30;
    const mn = m % 60;
    return mn === 0 ? String(Math.floor(m / 60) % 24).padStart(2, "0") : "30";
  }

  function l2s(str) {
    const p = str.split(":");
    if (p.length !== 2) return -1;
    const h = parseInt(p[0]),
      m = parseInt(p[1]);
    if (isNaN(h) || isNaN(m)) return -1;
    const sl = (h * 60 + m) / 30;
    return Number.isInteger(sl) ? sl : -1;
  }

  function sync() {
    inpI.value = sIni >= 0 ? s2l(sIni) : "";
    inpF.value = sFin >= 0 ? s2l(sFin) : "";

    const vi = sIni >= 0 ? s2l(sIni) : def_ini;
    const vf = sFin >= 0 ? s2l(sFin) : def_fin;

    if (hiddenIniId) document.getElementById(hiddenIniId).value = vi;
    if (hiddenFinId) document.getElementById(hiddenFinId).value = vf;
  }

  function renderG() {
    grid.querySelectorAll(".celda-h").forEach((c, s) => {
      c.classList.remove("sel-h");
      if (sIni < 0) return;
      if (sFin < 0) {
        if (s === sIni) c.classList.add("sel-h");
        return;
      }
      if (s >= sIni && s <= sFin) c.classList.add("sel-h");
    });
  }

  function renderD() {
    if (!durEl) return;
    if (sIni < 0 || sFin < 0) {
      durEl.textContent = "";
      return;
    }

    const t = (sFin - sIni) * 30;
    const h = Math.floor(t / 60);
    const m = t % 60;

    durEl.innerHTML =
      "<strong>" + h + "h" + (m > 0 ? " 30min" : "") + "</strong> de turno";
  }

  function extender(s) {
    if (s < sIni) sIni = s;
    else if (s > sFin) sFin = s;
    else {
      const dI = Math.abs(s - sIni);
      const dF = Math.abs(s - sFin);
      if (dI <= dF) sIni = s;
      else sFin = s;
    }
  }

  function click(s) {
    if (sIni < 0) {
      sIni = s;
      sFin = -1;
    } else if (sFin < 0) {
      if (s === sIni) sIni = -1;
      else if (s > sIni) sFin = s;
      else sIni = s;
    } else {
      extender(s);
    }
    sync();
    renderG();
    renderD();
  }

  for (let s = 0; s < SLOTS; s++) {
    const esM = s % 2 === 1;
    const c = document.createElement("div");

    c.className = "celda-h" + (esM ? " media-h" : "");
    c.textContent = s2d(s);

    c.addEventListener("mousedown", (e) => {
      drag = true;
      click(s);
      e.preventDefault();
    });

    c.addEventListener("mouseenter", () => {
      if (!drag) return;

      if (sIni >= 0 && sFin >= 0) {
        extender(s);
        sync();
        renderG();
        renderD();
      } else if (sIni >= 0 && sFin < 0 && s > sIni) {
        sFin = s;
        sync();
        renderG();
        renderD();
      }
    });

    grid.appendChild(c);
  }

  document.addEventListener("mouseup", () => {
    drag = false;
  });

  function af(inp, hidden) {
    let v = inp.value.replace(/[^0-9]/g, "");
    if (v.length > 2) v = v.slice(0, 2) + ":" + v.slice(2, 4);

    inp.value = v;

    const sl = l2s(v);
    if (inp === inpI) sIni = sl;
    else sFin = sl;

    sync();
    renderG();
    renderD();

    if (hidden) document.getElementById(hidden).value = v;
  }

  inpI.addEventListener("input", () => af(inpI, hiddenIniId));
  inpF.addEventListener("input", () => af(inpF, hiddenFinId));

  if (inpI.value) sIni = l2s(inpI.value);
  if (inpF.value) sFin = l2s(inpF.value);

  sync();
  renderG();
  renderD();

  return {
    aplicar(ini, fin) {
      sIni = l2s(ini);
      sFin = l2s(fin);
      inpI.value = ini;
      inpF.value = fin;
      sync();
      renderG();
      renderD();
    },
    limpiar() {
      sIni = -1;
      sFin = -1;
      inpI.value = "";
      inpF.value = "";

      if (hiddenIniId) document.getElementById(hiddenIniId).value = def_ini;
      if (hiddenFinId) document.getElementById(hiddenFinId).value = def_fin;

      renderG();
      renderD();
    },
    getIni() {
      return sIni >= 0 ? s2l(sIni) : def_ini;
    },
    getFin() {
      return sFin >= 0 ? s2l(sFin) : def_fin;
    },
  };
}

// INSTANCIA CONTROL

const selCtrl = crearSelector({
  gridId: "grid-ctrl",
  inpIniId: "inp-ini",
  inpFinId: "inp-fin",
  durId: "dur-ctrl",
  hiddenIniId: "ctrl-ini",
  hiddenFinId: "ctrl-fin",
  def_ini: horaIniProg,
  def_fin: horaFinProg,
});

function aplicarPillCtrl(btn) {
  document
    .querySelectorAll(".hora-pill-ctrl")
    .forEach((b) => b.classList.remove("activo-pill"));
  btn.classList.add("activo-pill");
  selCtrl.aplicar(btn.dataset.ini, btn.dataset.fin);
  limpiarErrCampo("wrap-horario-ctrl");
}

function limpiarCtrl() {
  document
    .querySelectorAll(".hora-pill-ctrl")
    .forEach((b) => b.classList.remove("activo-pill"));
  selCtrl.limpiar();
}

// TABS
function cambiarTab(tab, btn) {
  document
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.remove("activa"));
  btn.classList.add("activa");

  document
    .querySelectorAll(".tab-panel")
    .forEach((p) => p.classList.remove("visible"));
  document.getElementById("tab-" + tab).classList.add("visible");
}

// PROGRAMAS
function cambiarPrograma(sel) {
  const opt = sel.options[sel.selectedIndex];
  window.APP_CONFIG.horaIniProg = opt.dataset.ini || "08:00";
  window.APP_CONFIG.horaFinProg = opt.dataset.fin || "22:00";
  limpiarCtrl();
  limpiarErrCampo("wrap-programa");
}

// FORMULARIO
function mostrarFormulario() {
  document.getElementById("reserva-card").style.display = "block";
  renderDias();
  document
    .getElementById("reserva-card")
    .scrollIntoView({ behavior: "smooth", block: "start" });
}

function ocultarFormulario() {
  document.getElementById("reserva-card").style.display = "none";
  limpiarErrores();
}

// ERRORES
function mostrarErrorInline(msg) {
  const el = document.getElementById("error-inline");
  el.textContent = msg;
  el.style.display = "flex";
  el.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function ocultarErrorInline() {
  document.getElementById("error-inline").style.display = "none";
}

function marcarErrCampo(id) {
  document.getElementById(id)?.classList.add("campo-error");
}

function limpiarErrCampo(id) {
  document.getElementById(id)?.classList.remove("campo-error");
}

function limpiarErrores() {
  ocultarErrorInline();
  [
    "wrap-programa",
    "wrap-control",
    "wrap-plato",
    "wrap-horario-ctrl",
    "wrap-fechas",
    "wrap-puestos",
  ].forEach(limpiarErrCampo);
}

// VALIDACIÓN
function validarYEnviar() {
  limpiarErrores();

  const programa = document.getElementById("sel-programa").value;
  const control = document.querySelector('[name="control_nombre"]').value;
  const plato = document.querySelector('[name="plato"]').value;
  const ctrlIni = document.getElementById("ctrl-ini").value;
  const ctrlFin = document.getElementById("ctrl-fin").value;
  const fechas = document.getElementById("fechas-hidden").value.trim();
  const nPuestos = document.querySelectorAll("#tabla-body tr").length;

  let err = null,
    campo = null;

  if (!programa) {
    err = "Debes seleccionar un programa.";
    campo = "wrap-programa";
  } else if (!control) {
    err = "Debes seleccionar un control.";
    campo = "wrap-control";
  } else if (!plato) {
    err = "Debes seleccionar un plato.";
    campo = "wrap-plato";
  } else if (!ctrlIni || !ctrlFin) {
    err = "Indica el horario del control.";
    campo = "wrap-horario-ctrl";
  } else if (!/^\d{2}:\d{2}$/.test(ctrlIni) || !/^\d{2}:\d{2}$/.test(ctrlFin)) {
    err = "Formato de hora inválido (HH:MM).";
    campo = "wrap-horario-ctrl";
  } else if (!fechas) {
    err = "Selecciona al menos un día.";
    campo = "wrap-fechas";
  } else if (nPuestos === 0) {
    err = "Añade al menos un puesto.";
    campo = "wrap-puestos";
  }

  if (err) {
    mostrarErrorInline(err);
    if (campo) marcarErrCampo(campo);
    return;
  }

  document.getElementById("form-solicitud").submit();
}

// CARRUSEL DÍAS
const diasSel = new Set();
const NOM_DIA = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];
const NOM_MES = [
  "ene",
  "feb",
  "mar",
  "abr",
  "may",
  "jun",
  "jul",
  "ago",
  "sep",
  "oct",
  "nov",
  "dic",
];
const N_DIAS = 14;
let carOffset = 0;

const todosLosDias = (() => {
  const dias = [];
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  const fin = new Date(hoy);
  fin.setMonth(fin.getMonth() + 3);

  const cur = new Date(hoy);
  while (cur <= fin) {
    dias.push(new Date(cur));
    cur.setDate(cur.getDate() + 1);
  }
  return dias;
})();

function toISO(d) {
  return (
    d.getFullYear() +
    "-" +
    String(d.getMonth() + 1).padStart(2, "0") +
    "-" +
    String(d.getDate()).padStart(2, "0")
  );
}

function renderDias() {
  const cont = document.getElementById("carr-dias");
  if (!cont) return;

  cont.innerHTML = "";

  const max = Math.max(0, todosLosDias.length - N_DIAS);
  carOffset = Math.max(0, Math.min(carOffset, max));

  for (
    let i = carOffset;
    i < carOffset + N_DIAS && i < todosLosDias.length;
    i++
  ) {
    const d = todosLosDias[i];
    const iso = toISO(d);

    const dowES = (d.getDay() + 6) % 7;
    const finde = dowES >= 5;
    const sel = diasSel.has(iso);

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className =
      "dia-btn" +
      (finde && !sel ? " finde" : "") +
      (sel ? " seleccionado" : "");

    btn.innerHTML = `<span class="dia-nom">${NOM_DIA[dowES]}</span>
       <span class="dia-num">${d.getDate()}</span>
       <span class="dia-mes">${NOM_MES[d.getMonth()]}</span>`;

    btn.addEventListener("click", () => {
      diasSel.has(iso) ? diasSel.delete(iso) : diasSel.add(iso);
      limpiarErrCampo("wrap-fechas");
      actualizarResumen();
      renderDias();
    });

    cont.appendChild(btn);
  }

  document.getElementById("carr-ant").disabled = carOffset <= 0;
  document.getElementById("carr-sig").disabled = carOffset >= max;
}

function carruselMover(delta) {
  carOffset = Math.max(
    0,
    Math.min(carOffset + delta, todosLosDias.length - N_DIAS),
  );
  renderDias();
}

function seleccionarSemana() {
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);

  const d0 = todosLosDias[carOffset];
  const dow = (d0.getDay() + 6) % 7;

  const lunes = new Date(d0);
  lunes.setDate(d0.getDate() - dow);

  for (let j = 0; j < 7; j++) {
    const d = new Date(lunes);
    d.setDate(lunes.getDate() + j);
    if (d >= hoy) diasSel.add(toISO(d));
  }

  actualizarResumen();
  renderDias();
}

function limpiarDias() {
  diasSel.clear();
  actualizarResumen();
  renderDias();
}

function actualizarResumen() {
  document.getElementById("fechas-hidden").value = [...diasSel].join(",");

  const n = diasSel.size;
  const txt =
    n === 0
      ? "Ningún día seleccionado"
      : `<strong>${n} día${n > 1 ? "s" : ""} seleccionado${n > 1 ? "s" : ""}</strong>`;

  document.getElementById("dias-resumen").innerHTML = txt;
  document.getElementById("dias-resumen2").innerHTML = txt;
}

// PUESTOS
let filasCount = 0;
const filasMap = {};

function toggleSeleccion(btn) {
  btn.classList.toggle("activo");
}

function marcarTodos() {
  document
    .querySelectorAll("#puestos-grid .puesto-item")
    .forEach((b) => b.classList.add("activo"));
}

function desmarcarTodos() {
  document
    .querySelectorAll("#puestos-grid .puesto-item")
    .forEach((b) => b.classList.remove("activo"));
}

function añadirSeleccionados() {
  document
    .querySelectorAll("#puestos-grid .puesto-item.activo")
    .forEach((btn) => añadirPuesto(btn.dataset.puesto));

  desmarcarTodos();
  limpiarErrCampo("wrap-puestos");
}

function añadirPuesto(puesto) {
  const id = "fila-" + filasCount++;

  filasMap[id] = puesto;

  const tr = document.createElement("tr");
  tr.id = id;

  tr.innerHTML = `
    <input type="hidden" name="puestos[]" value="${puesto}">
    <td style="padding:6px 8px;border-bottom:1px solid var(--borde);font-weight:500;font-size:13px">${puesto}</td>
    <td style="padding:6px 8px;border-bottom:1px solid var(--borde);text-align:right">
      <button type="button" onclick="quitarFila('${id}')"
        style="background:none;border:none;color:var(--peligro);cursor:pointer;font-size:18px;padding:0;line-height:1">×</button>
    </td>
  `;

  document.getElementById("tabla-body").appendChild(tr);
  document.getElementById("tabla-wrap").style.display = "block";
}

function quitarFila(id) {
  delete filasMap[id];
  document.getElementById(id)?.remove();

  if (!Object.keys(filasMap).length) {
    document.getElementById("tabla-wrap").style.display = "none";
  }
}

function limpiarPuestos() {
  document.getElementById("tabla-body").innerHTML = "";
  Object.keys(filasMap).forEach((k) => delete filasMap[k]);
  document.getElementById("tabla-wrap").style.display = "none";
}

// CALENDARIO
function toggleDia(fecha) {
  const actual = new URLSearchParams(window.location.search).get("dia");

  const url =
    actual === fecha
      ? "turnos_programa.php?mes=" + mesOffset
      : "turnos_programa.php?mes=" + mesOffset + "&dia=" + fecha;

  window.location.href = url;
}

// MODAL CONFIRMACIÓN
let _confirmFormId = null;

function abrirConfirm(msg, formId) {
  _confirmFormId = formId;
  document.getElementById("modal-confirm-msg").textContent = msg;
  document.getElementById("modal-confirm").style.display = "flex";
}

function cerrarConfirm() {
  document.getElementById("modal-confirm").style.display = "none";
  _confirmFormId = null;
}

document.getElementById("modal-confirm-ok").addEventListener("click", () => {
  if (_confirmFormId) document.getElementById(_confirmFormId).submit();
});

document.getElementById("modal-confirm").addEventListener("click", (e) => {
  if (e.target === e.currentTarget) cerrarConfirm();
});

// INIT
window.addEventListener("DOMContentLoaded", () => {
  if (reabrirForm) {
    mostrarFormulario();

    const fechasVal = document.getElementById("fechas-hidden").value;
    if (fechasVal) {
      fechasVal.split(",").forEach((f) => f.trim() && diasSel.add(f.trim()));
      actualizarResumen();
    }

    renderDias();

    if (errorMsg) mostrarErrorInline(errorMsg);

    if (errorFields?.length) {
      const mapa = {
        programa_id: "wrap-programa",
        control_nombre: "wrap-control",
        plato: "wrap-plato",
        horario_ctrl: "wrap-horario-ctrl",
        fechas: "wrap-fechas",
        puestos: "wrap-puestos",
      };

      errorFields.forEach((f) => mapa[f] && marcarErrCampo(mapa[f]));
    }
  } else {
    renderDias();

    if (new URLSearchParams(window.location.search).get("dia")) {
      setTimeout(() => {
        document
          .getElementById("calendario")
          ?.scrollIntoView({ behavior: "smooth", block: "start" });
      }, 80);
    }
  }
});
