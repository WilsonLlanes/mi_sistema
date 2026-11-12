# Mini-sistema de Gestión con Compras y Stock

Trabajo práctico integrador de **Lenguaje de Programación III** (Unidad VIII – Construcción de Software).
Sistema web en **PHP nativo con PDO** y **PostgreSQL**, sin frameworks, con login seguro, menú por rol,
CRUD de productos, compras maestro-detalle con actualización de stock y reporte con exportación a CSV.

## Requisitos

- WAMP, XAMPP o LAMP con **PHP 8.0 o superior** (probado con PHP 8.5).
- Extensiones de PHP habilitadas: `pdo_pgsql` y `pgsql` (en XAMPP/WAMP se activan en `php.ini`
  quitando el `;` de `extension=pdo_pgsql` y `extension=pgsql`, y luego se reinicia Apache).
- **PostgreSQL 12 o superior** (probado con PostgreSQL 18) y pgAdmin o `psql`.
- Navegador web actualizado (Chrome, Firefox o Edge).

## Instalación

1. Copiar la carpeta `mi_sistema/` dentro de `htdocs` (XAMPP) o `www` (WAMP).
2. Crear la base de datos y cargar el script con las tablas, la función PL/pgSQL y los datos semilla:
   ```sql
   CREATE DATABASE mi_sistema ENCODING 'UTF8';
   ```
   ```bash
   psql -U postgres -d mi_sistema -f database/mi_sistema.sql
   ```
   (En pgAdmin: clic derecho sobre la base `mi_sistema` → *Query Tool* → abrir `database/mi_sistema.sql` → ejecutar.)
3. Configurar la conexión: copiar `app/config/config.example.php` como `app/config/config.php`
   (si no existe) y completar host, puerto, nombre de la base, usuario y contraseña de PostgreSQL.
4. Abrir en el navegador: `http://localhost/mi_sistema/public/`
   Lo ideal en producción es que el *DocumentRoot* (o un *VirtualHost*) apunte directamente a `public/`,
   así `app/` y `database/` quedan fuera del alcance web. Si se usa la carpeta completa dentro de `htdocs`,
   los archivos `.htaccess` de `app/` y `database/` bloquean el acceso directo (Apache 2.4).

## Credenciales de prueba

| Usuario    | Contraseña | Rol      | Menú visible                                              |
|------------|------------|----------|-----------------------------------------------------------|
| `admin`    | `123`      | Admin    | Inicio, Productos, Compras, Reporte de compras, Usuarios  |
| `operador` | `123`      | Operador | Inicio, Productos, Compras, Reporte de compras            |

- Después de **3 intentos fallidos** el usuario queda bloqueado (contador en la columna `intentos_fallidos`).
  El administrador lo desbloquea desde **Usuarios → Desbloquear**.
- Las contraseñas se guardan con `password_hash()` y se verifican con `password_verify()`.

## Estructura del proyecto

```
mi_sistema/
├── app/                      (fuera del alcance público)
│   ├── bootstrap.php         carga común, manejo de errores, zona horaria
│   ├── config/
│   │   ├── config.php        credenciales de la BD (no se publica)
│   │   └── config.example.php
│   ├── lib/
│   │   ├── db.php            conexión PDO a PostgreSQL
│   │   ├── auth.php          sesiones seguras, login, bloqueo, menú y permisos por rol
│   │   └── helpers.php       escape de salida, CSRF, mensajes, validación de fechas
│   └── views/
│       ├── header.php        cabecera y menú dinámico
│       └── footer.php
├── database/
│   └── mi_sistema.sql        tablas, función PL/pgSQL y datos semilla
├── public/                   (raíz web)
│   ├── index.php             redirige a login o inicio
│   ├── login.php / logout.php
│   ├── dashboard.php         resumen
│   ├── productos.php         listado, búsqueda y baja
│   ├── producto_form.php     alta y modificación (usa fn_producto_guardar)
│   ├── compras.php           listado de compras
│   ├── compra_nueva.php      compra maestro-detalle en transacción
│   ├── compra_ver.php        detalle de una compra
│   ├── reporte_compras.php   filtros y exportación CSV
│   ├── usuarios.php          desbloqueo de usuarios (solo Admin)
│   └── assets/ (css/estilos.css, js/compra.js)
└── README.md
```

## Estructura de tablas

| Tabla            | Campos principales                                                                                  |
|------------------|-----------------------------------------------------------------------------------------------------|
| `roles`          | `id`, `nombre` (Admin, Operador)                                                                    |
| `usuarios`       | `id`, `usuario`, `nombre`, `password_hash`, `rol_id` → roles, `intentos_fallidos`, `bloqueado`, `ultimo_acceso` |
| `menu`           | `id`, `titulo`, `url`, `orden`                                                                      |
| `permisos`       | `rol_id` → roles, `menu_id` → menu (PK compuesta)                                                   |
| `productos`      | `id`, `descripcion` (única), `stock` (≥ 0), `precio_compra` (≥ 0)                                   |
| `proveedores`    | `id`, `nombre`, `ruc` (único)                                                                       |
| `compras`        | `id`, `fecha`, `proveedor_id` → proveedores, `usuario_id` → usuarios, `nro_factura`, `total`, `creado_en` |
| `compra_detalle` | `id`, `compra_id` → compras, `producto_id` → productos, `cantidad` (> 0), `precio_unitario`, `subtotal` |

Función PL/pgSQL: `fn_producto_guardar(p_id, p_descripcion, p_stock, p_precio)` inserta (si `p_id` es NULL)
o actualiza un producto, valida los datos y devuelve el `id`.

## Funcionamiento y notas

- **Sesiones:** cookie `httponly` y `SameSite=Lax` (y `secure` cuando hay HTTPS), `use_strict_mode`,
  regeneración del ID con `session_regenerate_id(true)` después del login, cierre por inactividad (30 min)
  y cierre de sesión que destruye la sesión y la cookie.
- **Menú por rol:** se arma desde las tablas `menu` y `permisos`. Cada página llama a `requerir_permiso()`,
  así que aunque un Operador escriba la URL `usuarios.php` a mano recibe un error 403.
- **CRUD de productos:** validación del lado servidor (obligatorios, longitud 3–100, enteros no negativos),
  sentencias preparadas, `strip_tags()` en la entrada y `htmlspecialchars()` en toda la salida.
  No se permite borrar un producto que ya figura en compras (se informa al usuario).
- **Compras:** cabecera (`compras`) y detalle (`compra_detalle`) se graban en una transacción
  (`beginTransaction` / `commit` / `rollBack`). Al confirmar se suma la cantidad al stock de cada producto
  y el total se recalcula en la base a partir del detalle. Si algo falla, no queda nada grabado a medias.
- **Reporte:** filtros por fecha desde/hasta y proveedor opcional; exportación a CSV (separador `;`,
  UTF-8 con BOM para abrir bien en Excel).
- **Seguridad general:** PDO con `ERRMODE_EXCEPTION` y sin emulación de sentencias preparadas, tokens CSRF
  en todos los formularios POST, mensajes de error genéricos (el detalle va al log) y credenciales
  fuera de la carpeta pública.
- Los montos se manejan en guaraníes, sin decimales.
