# TurnosTV

Aplicación web para la gestión de turnos de trabajo en empresas de producción televisiva. Desarrollada como Trabajo de Fin de Grado (2025–2026).

---

## Descripción

TurnosTV centraliza la gestión de turnos rotativos de técnicos de televisión en un único sistema web. Permite que coordinadores planifiquen horarios, directores soliciten equipo técnico y empleados consulten sus turnos y gestionen sus ausencias, todo desde el navegador sin instalación en el cliente.

---

## Roles del sistema

- **Coordinador** — gestiona horarios, aprueba vacaciones y atiende peticiones de directores y empleados.
- **Director** — solicita equipo técnico para sus programas y envía peticiones de cambio al coordinador.
- **Empleado** — consulta sus turnos y gestiona sus solicitudes de vacaciones y ausencias.
- **Administrador** — superusuario con acceso a todos los paneles *(en construcción)*.

---

## Tecnologías utilizadas

- PHP 8.x — backend y lógica de servidor
- MySQL / MariaDB 10.4 — base de datos relacional
- HTML5, CSS3, JavaScript vanilla — capa de presentación
- Flatpickr — selector de rangos de fechas (CDN)
- XAMPP — entorno de desarrollo local (Apache + PHP + MySQL)
- draw.io — diseño de diagramas ER y de base de datos
- Git — control de versiones

---

## Requisitos previos

- PHP 8.x o superior
- MySQL 8.x o MariaDB 10.4+
- Apache (se recomienda instalar todo junto con XAMPP)

---

## Instalación

**1. Clonar o descargar el repositorio**

```bash
git clone https://github.com/EsterMG/TFG-EsterMartinGonzalez
```

**2. Copiar la carpeta al directorio de XAMPP**

```
C:\xampp\htdocs\turnostv\
```

**3. Importar la base de datos**

Opción A — desde phpMyAdmin:
1. Crear una base de datos llamada `turnostv`
2. Importar el archivo `sql/turnostv.sql`

Opción B — desde línea de comandos:
```bash
mysql -u root -p turnostv < sql/turnostv.sql
```

**4. Configuración de la conexión**

La cadena de conexión está definida en cada archivo PHP con estos valores por defecto:

```
host:     localhost
usuario:  root
password: (vacía)
bbdd:     turnostv
```

**5. Arrancar XAMPP y acceder desde el navegador**

Iniciar los módulos Apache y MySQL desde el panel de XAMPP y abrir:

```
http://localhost/turnostv/login.php
```

---

## Estructura del proyecto

```
turnostv/
├── login.php
├── login.css
├── logout.php
├── fragmentos/               # Header, sidebar y perfil compartidos
│   ├── header.php
│   ├── sidebar.php
│   ├── perfil.php
│   ├── js/
│   │   ├── perfil.js
│   │   └── sidebar.js
│   └── styles/
│       ├── base.css
│       └── perfil.css
├── panel_coordinador/        # Horarios, empleados, peticiones, programas
│   ├── panel_coordinador.php
│   ├── horarios.php
│   ├── horarios_actions.php
│   ├── horarios_ajax.php
│   ├── horarios_cal.php
│   ├── horarios_data.php
│   ├── horarios_helpers.php
│   ├── horarios_panel_solicitud.php
│   ├── empleados.php
│   ├── peticiones.php
│   ├── programas.php
│   ├── js/
│   └── styles/
├── panel_director/           # Solicitudes de equipo y peticiones de cambio
│   ├── panel_director.php
│   ├── turnos_programa.php
│   ├── mis_peticiones.php
│   ├── js/
│   └── styles/
├── panel_empleado/           # Turnos, vacaciones y solicitudes de ausencia
│   ├── panel_empleado.php
│   ├── mis_turnos.php
│   ├── mis_solicitudes.php
│   ├── js/
│   └── styles/
├── img/
│   ├── default.png
│   ├── favicon.png
│   └── favicon2.png
├── sql/
│   ├── turnostv.sql
│   └── turnostv_sin_transaction.sql
└── uploads/
```

---

## Referencias

- The PHP Group. *PHP Manual*. https://www.php.net/docs.php
- The PHP Group. *PHP: password_hash*. https://www.php.net/manual/es/function.password-hash.php
- The PHP Group. *PHP: Extensión MySQLi*. https://www.php.net/manual/es/book.mysqli.php
- The PHP Group. *PHP: $_SESSION*. https://www.php.net/manual/es/reserved.variables.session.php
- Oracle Corporation. *MySQL 8.0 Reference Manual*. https://dev.mysql.com/doc/refman/8.0/en/
- Apache Friends. *XAMPP*. https://www.apachefriends.org/
- Chmln & Viorelsfetea. *flatpickr*. https://flatpickr.js.org/
- JTangney et al. *diagrams.net (draw.io)*. https://www.diagrams.net/
- MDN Web Docs. *CSS y HTML5*. https://developer.mozilla.org/es/
- MDN Web Docs. *Fetch API*. https://developer.mozilla.org/es/docs/Web/API/Fetch_API
- Factorial HR. *Software de gestión de turnos*. https://factorial.es/gestion-turnos
- ClickUp. *Los 10 mejores programas de gestión de turnos*. https://clickup.com/es-ES/blog/178575/programa-de-gestion-de-turnos

---

## Autora

Ester Martín González — Trabajo de Fin de Grado, 2025–2026

Tutora: Luz María Álvarez Moreno
