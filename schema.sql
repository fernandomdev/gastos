CREATE DATABASE IF NOT EXISTS gastos_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gastos_app;

DROP TABLE IF EXISTS movimientos;
DROP TABLE IF EXISTS categoria_reglas;
DROP TABLE IF EXISTS categorias;

CREATE TABLE categorias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    tipo ENUM('gasto','ingreso','transferencia','impuesto','otro') NOT NULL DEFAULT 'otro',
    color VARCHAR(7) NOT NULL DEFAULT '#64748b',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_categorias_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categoria_reglas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(150) NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_reglas_keyword (keyword),
    KEY idx_reglas_activo (activo),
    KEY idx_reglas_categoria (categoria_id),
    CONSTRAINT fk_reglas_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE movimientos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_file VARCHAR(255) NOT NULL,
    row_hash CHAR(32) NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    raw_fecha VARCHAR(50) NULL,
    nro_comprobante VARCHAR(30) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    moneda CHAR(3) NOT NULL DEFAULT 'PYG',
    debito DECIMAL(14,2) NOT NULL DEFAULT 0,
    credito DECIMAL(14,2) NOT NULL DEFAULT 0,
    monto_neto DECIMAL(14,2) NOT NULL DEFAULT 0,
    sentido ENUM('DEBITO','CREDITO') NOT NULL DEFAULT 'DEBITO',
    categoria_id INT UNSIGNED NULL,
    imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_movimientos_row_hash (row_hash),
    KEY idx_movimientos_fecha (fecha_movimiento),
    KEY idx_movimientos_categoria (categoria_id),
    KEY idx_movimientos_moneda (moneda),
    KEY idx_movimientos_sentido (sentido),
    CONSTRAINT fk_movimientos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categorias (nombre, tipo, color) VALUES
('Ingresos / acreditaciones', 'ingreso', '#22c55e'),
('Transferencias', 'transferencia', '#38bdf8'),
('Impuestos', 'impuesto', '#f59e0b'),
('Préstamos', 'gasto', '#ef4444'),
('Movilidad', 'gasto', '#a855f7'),
('Delivery', 'gasto', '#fb7185'),
('Comida', 'gasto', '#f97316'),
('Supermercado', 'gasto', '#14b8a6'),
('Farmacia / salud', 'gasto', '#10b981'),
('Digital / suscripciones', 'gasto', '#6366f1'),
('Servicios / telefonía', 'gasto', '#eab308'),
('Educación', 'gasto', '#8b5cf6'),
('Belleza', 'gasto', '#ec4899'),
('Otros / sin clasificar', 'otro', '#94a3b8');

INSERT INTO categoria_reglas (categoria_id, keyword, priority) VALUES
((SELECT id FROM categorias WHERE nombre='Ingresos / acreditaciones'), 'ACREDITACION', 200),
((SELECT id FROM categorias WHERE nombre='Ingresos / acreditaciones'), 'Transferenc. Recibida SPI', 195),
((SELECT id FROM categorias WHERE nombre='Transferencias'), 'Transferencia Enviada SPI', 190),
((SELECT id FROM categorias WHERE nombre='Transferencias'), 'Transfer.Internet Banking', 185),
((SELECT id FROM categorias WHERE nombre='Impuestos'), 'IVA LEY 6380', 180),
((SELECT id FROM categorias WHERE nombre='Impuestos'), '#IVALey6380', 180),
((SELECT id FROM categorias WHERE nombre='Préstamos'), 'PAGO PRESTAMO - DIGITAL', 175),
((SELECT id FROM categorias WHERE nombre='Movilidad'), 'Mas tarjeta Billetaje Mas', 170),
((SELECT id FROM categorias WHERE nombre='Movilidad'), 'BILLETaje Mas', 170),
((SELECT id FROM categorias WHERE nombre='Movilidad'), 'UBER', 168),
((SELECT id FROM categorias WHERE nombre='Movilidad'), 'BOLT', 166),
((SELECT id FROM categorias WHERE nombre='Delivery'), 'PEDIDOS YA', 160),
((SELECT id FROM categorias WHERE nombre='Comida'), 'MC DONALDS', 155),
((SELECT id FROM categorias WHERE nombre='Comida'), 'FOGATA', 154),
((SELECT id FROM categorias WHERE nombre='Comida'), 'CITADINO', 153),
((SELECT id FROM categorias WHERE nombre='Comida'), 'DON VITO', 152),
((SELECT id FROM categorias WHERE nombre='Comida'), 'LOMISUR', 151),
((SELECT id FROM categorias WHERE nombre='Comida'), 'NA EUSTAQUIA', 150),
((SELECT id FROM categorias WHERE nombre='Comida'), 'VERDAD CONSECUENCIA', 149),
((SELECT id FROM categorias WHERE nombre='Comida'), 'EL DRAMA', 148),
((SELECT id FROM categorias WHERE nombre='Comida'), 'RUTA 66', 147),
((SELECT id FROM categorias WHERE nombre='Comida'), 'BILLY SMASH', 146),
((SELECT id FROM categorias WHERE nombre='Comida'), 'CRIOLLO', 145),
((SELECT id FROM categorias WHERE nombre='Comida'), 'PORTOBELO', 144),
((SELECT id FROM categorias WHERE nombre='Comida'), 'CEDRO LIBANO', 143),
((SELECT id FROM categorias WHERE nombre='Comida'), 'MULTI HELADOS', 142),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'SUP.S6-V.MORRA', 140),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'SUP.REAL-V.MORRA', 139),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'SUP.STOCK-IPS', 138),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'S6- V.MORRA SCO', 137),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'ARETE', 136),
((SELECT id FROM categorias WHERE nombre='Farmacia / salud'), 'FARMACENTER', 130),
((SELECT id FROM categorias WHERE nombre='Farmacia / salud'), 'ULTRACAI', 129),
((SELECT id FROM categorias WHERE nombre='Digital / suscripciones'), 'PAYPAL', 125),
((SELECT id FROM categorias WHERE nombre='Digital / suscripciones'), 'STEAM', 124),
((SELECT id FROM categorias WHERE nombre='Digital / suscripciones'), 'DLOCAL *HELP.HBOMAX.COM', 123),
((SELECT id FROM categorias WHERE nombre='Digital / suscripciones'), 'PAGOPAR-QR', 122),
((SELECT id FROM categorias WHERE nombre='Servicios / telefonía'), 'Tigo Compra de saldo', 120),
((SELECT id FROM categorias WHERE nombre='Educación'), 'Universidad Americana', 115),
((SELECT id FROM categorias WHERE nombre='Belleza'), 'CHARME COIFFURE', 110),
((SELECT id FROM categorias WHERE nombre='Otros / sin clasificar'), 'Compra hecha en POS', 1),
((SELECT id FROM categorias WHERE nombre='Supermercado'), 'S6', 100);
