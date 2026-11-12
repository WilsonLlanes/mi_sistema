-- =====================================================================
-- Mini-sistema de Gestión con Compras y Stock
-- Script de creación de la base de datos (PostgreSQL 12 o superior)
-- Uso:  1) CREATE DATABASE mi_sistema ENCODING 'UTF8';
--       2) psql -U postgres -d mi_sistema -f mi_sistema.sql
-- =====================================================================

SET client_encoding = 'UTF8';

DROP TABLE IF EXISTS compra_detalle, compras, proveedores, productos,
                     permisos, menu, usuarios, roles CASCADE;
DROP FUNCTION IF EXISTS fn_producto_guardar(INTEGER, VARCHAR, INTEGER, NUMERIC);

-- ---------------------------------------------------------------------
-- Seguridad: roles, usuarios, menú y permisos
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(30) NOT NULL UNIQUE
);

CREATE TABLE usuarios (
    id                 SERIAL PRIMARY KEY,
    usuario            VARCHAR(30)  NOT NULL UNIQUE,
    nombre             VARCHAR(80)  NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    rol_id             INTEGER      NOT NULL REFERENCES roles(id),
    intentos_fallidos  SMALLINT     NOT NULL DEFAULT 0,
    bloqueado          BOOLEAN      NOT NULL DEFAULT FALSE,
    ultimo_acceso      TIMESTAMP    NULL
);

CREATE TABLE menu (
    id      SERIAL PRIMARY KEY,
    titulo  VARCHAR(50)  NOT NULL,
    url     VARCHAR(100) NOT NULL UNIQUE,
    orden   SMALLINT     NOT NULL DEFAULT 0
);

CREATE TABLE permisos (
    rol_id   INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    menu_id  INTEGER NOT NULL REFERENCES menu(id)  ON DELETE CASCADE,
    PRIMARY KEY (rol_id, menu_id)
);

-- ---------------------------------------------------------------------
-- Módulo referencial y movimiento de compras
-- ---------------------------------------------------------------------
CREATE TABLE productos (
    id             SERIAL PRIMARY KEY,
    descripcion    VARCHAR(100)  NOT NULL UNIQUE,
    stock          INTEGER       NOT NULL DEFAULT 0 CHECK (stock >= 0),
    precio_compra  NUMERIC(12,0) NOT NULL CHECK (precio_compra >= 0)
);

CREATE TABLE proveedores (
    id      SERIAL PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL,
    ruc     VARCHAR(20)  NOT NULL UNIQUE
);

CREATE TABLE compras (
    id            SERIAL PRIMARY KEY,
    fecha         DATE          NOT NULL DEFAULT CURRENT_DATE,
    proveedor_id  INTEGER       NOT NULL REFERENCES proveedores(id),
    usuario_id    INTEGER       NOT NULL REFERENCES usuarios(id),
    nro_factura   VARCHAR(20)   NOT NULL,
    total         NUMERIC(14,0) NOT NULL DEFAULT 0,
    creado_en     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_factura_proveedor UNIQUE (proveedor_id, nro_factura)
);

CREATE TABLE compra_detalle (
    id               SERIAL PRIMARY KEY,
    compra_id        INTEGER       NOT NULL REFERENCES compras(id) ON DELETE CASCADE,
    producto_id      INTEGER       NOT NULL REFERENCES productos(id),
    cantidad         INTEGER       NOT NULL CHECK (cantidad > 0),
    precio_unitario  NUMERIC(12,0) NOT NULL CHECK (precio_unitario >= 0),
    subtotal         NUMERIC(14,0) NOT NULL
);

CREATE INDEX idx_compras_fecha ON compras(fecha);
CREATE INDEX idx_compras_proveedor ON compras(proveedor_id);

-- ---------------------------------------------------------------------
-- Función PL/pgSQL: alta o modificación de productos
--   p_id NULL  -> inserta y devuelve el id nuevo
--   p_id > 0   -> actualiza y devuelve el mismo id
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_producto_guardar(
    p_id           INTEGER,
    p_descripcion  VARCHAR,
    p_stock        INTEGER,
    p_precio       NUMERIC
) RETURNS INTEGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_id INTEGER;
BEGIN
    IF p_descripcion IS NULL OR length(trim(p_descripcion)) < 3 THEN
        RAISE EXCEPTION 'La descripción debe tener al menos 3 caracteres';
    END IF;
    IF p_stock < 0 OR p_precio < 0 THEN
        RAISE EXCEPTION 'El stock y el precio no pueden ser negativos';
    END IF;

    IF p_id IS NULL THEN
        INSERT INTO productos (descripcion, stock, precio_compra)
        VALUES (trim(p_descripcion), p_stock, p_precio)
        RETURNING id INTO v_id;
    ELSE
        UPDATE productos
           SET descripcion = trim(p_descripcion),
               stock = p_stock,
               precio_compra = p_precio
         WHERE id = p_id
        RETURNING id INTO v_id;
        IF v_id IS NULL THEN
            RAISE EXCEPTION 'No existe el producto %', p_id;
        END IF;
    END IF;
    RETURN v_id;
END;
$$;

-- ---------------------------------------------------------------------
-- Datos semilla
-- ---------------------------------------------------------------------
INSERT INTO roles (id, nombre) VALUES (1, 'Admin'), (2, 'Operador');

-- Contraseña de ambos usuarios: 123 (hash generado con password_hash de PHP)
INSERT INTO usuarios (usuario, nombre, password_hash, rol_id) VALUES
    ('admin',    'Administrador del sistema', '$2y$12$dCEWPc1lspPfwcmfy/4y/.rs10mGnR1FaJkQXwhPcjmp2y4s3uxe2', 1),
    ('operador', 'Operador de compras',       '$2y$12$/BxyE0HaBODcRJSp3uivXuP.qhcMqilwQpGnzcYQWyt0w9T7xMYVG', 2);

INSERT INTO menu (id, titulo, url, orden) VALUES
    (1, 'Inicio',             'dashboard.php',        1),
    (2, 'Productos',          'productos.php',        2),
    (3, 'Compras',            'compras.php',          3),
    (4, 'Reporte de compras', 'reporte_compras.php',  4),
    (5, 'Usuarios',           'usuarios.php',         5);

-- Admin ve todo; Operador no ve la administración de usuarios
INSERT INTO permisos (rol_id, menu_id) VALUES
    (1, 1), (1, 2), (1, 3), (1, 4), (1, 5),
    (2, 1), (2, 2), (2, 3), (2, 4);

INSERT INTO productos (descripcion, stock, precio_compra) VALUES
    ('Resma de papel A4 500 hojas', 20, 32000),
    ('Tóner para impresora láser',   5, 185000),
    ('Bolígrafo azul (caja x 50)',  10, 45000),
    ('Carpeta archivadora oficio',  30, 12000),
    ('Pendrive 32 GB',               8, 55000);

INSERT INTO proveedores (nombre, ruc) VALUES
    ('Librería Central S.A.',        '80012345-6'),
    ('Distribuidora Informática SRL','80065432-1'),
    ('Papelera del Este',            '80098765-4');

-- Ajusta las secuencias porque roles y menu se cargaron con id explícito
SELECT setval('roles_id_seq', (SELECT MAX(id) FROM roles));
SELECT setval('menu_id_seq',  (SELECT MAX(id) FROM menu));
