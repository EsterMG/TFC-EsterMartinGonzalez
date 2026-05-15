/* peticiones.js  */

/* PESTAÑAS */
function cambiarTab(id, btn) {
  document
    .querySelectorAll(".tab-panel")
    .forEach((p) => p.classList.remove("visible"));
  document
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.remove("activa"));
  document.getElementById("tab-" + id).classList.add("visible");
  btn.classList.add("activa");
}

/* EXPANDIBLES ACTIVAS */
function toggleVacRespuesta(id) {
  document.getElementById("vac-form-" + id).classList.toggle("visible");
}

function togglePetRespuesta(id) {
  document.getElementById("pet-form-" + id).classList.toggle("visible");
}

/* EXPANDIBLES FINALIZADAS */
function toggleFinDetalle(id) {
  document.getElementById("fin-detalle-" + id).classList.toggle("visible");
}

/* BUSCADOR + FILTRO FINALIZADAS */
function filtrarFinalizadas() {
  const texto = document.getElementById("fin-buscar").value.toLowerCase();
  const filtro =
    document.querySelector(".fin-filtro-btn.activo")?.dataset.filtro ?? "todos";

  document.querySelectorAll(".fin-row").forEach((row) => {
    const estado = row.dataset.estado;
    const hayTexto = row.dataset.buscar.includes(texto);
    const hayFiltro = filtro === "todos" || estado === filtro;
    row.style.display = hayTexto && hayFiltro ? "" : "none";
  });

  /* Mostrar mensaje "sin resultados" por columna */
  ["vac", "pet"].forEach((col) => {
    const rows = document.querySelectorAll(`.fin-row[data-col="${col}"]`);
    const visibles = [...rows].filter((r) => r.style.display !== "none");
    const vacio = document.getElementById("fin-vacio-" + col);
    if (vacio) vacio.style.display = visibles.length === 0 ? "" : "none";
  });
}

function setFiltro(btn, valor) {
  document
    .querySelectorAll(".fin-filtro-btn")
    .forEach((b) => b.classList.remove("activo"));
  btn.classList.add("activo");
  filtrarFinalizadas();
}
