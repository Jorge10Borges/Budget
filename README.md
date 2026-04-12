# Budget — Control de Presupuestos y Gestión de Obras

Sistema web para la gestión de presupuestos y ejecución de obras. Incluye gestión de proyectos, valuaciones, partidas y control de gastos. Diseñado para equipos que necesitan llevar el control detallado de partidas presupuestarias, registrar valuaciones periódicas y auditar gastos asociados a cada obra.

Descripción corta (para el repositorio): Control de presupuestos y seguimiento de obras — proyectos, valuaciones, partidas y gastos.

## Características principales

- Gestión de `Proyectos`: creación, edición, estado y presupuesto.
- `Valuaciones`: registrar avances económicos y porcentuales por obra.
- `Partidas`: estructura de partidas presupuestarias por proyecto.
- `Gastos`: registrar comprobantes, montos y asignarlos a partidas/valuaciones.
- API REST básica en PHP (MySQL) con endpoints CRUD para `projects` (ejemplo), extensible a `valuations`, `items`, `expenses`.
- UI en Astro con variables de diseño (CSS tokens) y componentes reutilizables (navbars, tarjetas de proyecto).
- Sistema pensado para despliegue local con XAMPP / MySQL y preparación para despliegue en servidor LAMP.

## Tecnologías

- Frontend: Astro, HTML, CSS (variables de diseño en `src/styles/tokens.css`), componentes `.astro`.
- Backend: PHP (mysqli) para API REST localizada en `api/`.
- Base de datos: MySQL (esquemas en `docs/sql/schema.sql`).
- Utilidades: Tailwind (configurado para leer variables CSS), Material Symbols para íconos.

## Rápido arranque (desarrollo)

1. Instalar dependencias frontend:

```bash
npm install
```

2. Levantar servidor de desarrollo Astro:

```bash
npm run dev
```

3. Configurar la base de datos MySQL (XAMPP):

- Crear una base de datos (p. ej. `budget_db`).
- Ejecutar el esquema SQL en `docs/sql/schema.sql` o importar el archivo proporcionado.
- Copiar `.env.example` a `.env` y configurar las credenciales de DB.

4. API local (ejemplo): los endpoints se exponen bajo `/api/`. Ejemplos:

- `GET /api/projects.php` — lista de proyectos.
- `GET /api/projects.php?id=1` — obtener proyecto por id.
- `POST /api/projects.php` — crear proyecto (JSON).
- `PUT /api/projects.php?id=1` — actualizar proyecto (JSON).
- `DELETE /api/projects.php?id=1` — eliminar proyecto.

> Nota: Actualmente los endpoints usan mysqli sin autenticación. Para producción, añadir autenticación, validación y saneamiento adicional.

## Estructura relevante

- `src/` — código del frontend (páginas y componentes Astro).
- `src/styles/tokens.css` — variables de color y utilidades (`.bg-...`, `.text-...`).
- `src/components/` — `NavBarDesktop.astro`, `NavBarMobil.astro`, etc.
- `api/` — endpoints PHP (`config.php`, `db.php`, `projects.php`).
- `docs/sql/schema.sql` — esquema de tablas para `projects` (y ejemplos para `valuations`, `items`, `expenses`).

## Documentación adicional y siguientes pasos

- Añadir endpoints y esquema para `valuations` (valuaciones), `items` (partidas) y `expenses` (gastos).
- Conectar el frontend para listar/editar entidades reales desde la API.
- Añadir autenticación y control de permisos para usuarios.

Si quieres, puedo:

- Generar las tablas SQL para `valuations`, `items` y `expenses` según un modelo propuesto.
- Conectar la `projects.astro` a la API existente para cargar datos reales.
- Preparar un script `db/seed` para poblar ejemplo de datos.

## Contribuir

Fork, crea una rama con tu cambio y abre un Pull Request. Para dudas, contacta a info@devjorge.com.

## Licencia

Por defecto este repositorio no incluye una licencia; añade una (p. ej. MIT) si deseas permitir contribuciones públicas.

