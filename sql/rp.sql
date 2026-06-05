-- ============================================================
--  RP TRAVELS — SCRIPT SQL
--  Orden de ejecución:
--    1. Configuración inicial
--    2. Tablas y datos (schema)
--    3. Tabla de auditoría + triggers
--    4. Usuarios de base de datos y privilegios
--
--  Cómo importar:
--    A) phpMyAdmin → BD "rp_travels" → Importar → este archivo
--    B) Docker: docker exec -i rp_db mysql -uroot -prootpassword < sql/rp.sql
--
--  BORRA Y RECREA TODAS LAS TABLAS. Haz backup si tienes reservas.
-- ============================================================


-- ============================================================
--  1. CONFIGURACIÓN INICIAL
-- ============================================================
SET NAMES 'utf8mb4';
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

USE rp_travels;


-- ============================================================
--  2. TABLAS Y DATOS
-- ============================================================

-- ── Limpiar tablas (orden inverso a FKs) ────────────────────
DROP TABLE IF EXISTS reservas_auditoria;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS pagos;
DROP TABLE IF EXISTS contactos_reserva;
DROP TABLE IF EXISTS viajeros;
DROP TABLE IF EXISTS reservas;
DROP TABLE IF EXISTS paquete_servicios;
DROP TABLE IF EXISTS servicios;
DROP TABLE IF EXISTS paquetes;
DROP TABLE IF EXISTS destinos;
DROP TABLE IF EXISTS ciudades_origen;
DROP TABLE IF EXISTS coches;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS admins;  -- tabla eliminada, el DROP limpia instalaciones antiguas


-- ── USUARIOS ─────────────────────────────────────────────────
CREATE TABLE usuarios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    apellidos     VARCHAR(150) NOT NULL DEFAULT '',
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    telefono      VARCHAR(30)  NOT NULL DEFAULT '',
    rol           TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = admin/root, 1 = usuario normal
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── USUARIO ADMINISTRADOR POR DEFECTO ────────────────────────
INSERT INTO usuarios (nombre, apellidos, email, password_hash, telefono, rol) VALUES
('Admin', 'RP Travels', 'admin@rp.es', '$2y$10$lfzN6a7C6HneSi0Ko7ImtuRcBbC38KjnMj.kuJA/MskCt92r.W/q.', '', 0);


-- ── RECUPERACIÓN DE CONTRASEÑA ───────────────────────────────
CREATE TABLE password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT          NOT NULL,
    token      VARCHAR(20)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used       TINYINT(1)   DEFAULT 0,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── RATE LIMITING ────────────────────────────────────────────
CREATE TABLE login_attempts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,
    action     VARCHAR(50)  NOT NULL,
    created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── COCHES DE ALQUILER ───────────────────────────────────────
CREATE TABLE coches (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100)  NOT NULL,
    categoria   VARCHAR(50)   NOT NULL,
    precio_dia  DECIMAL(8,2)  NOT NULL,
    imagen      VARCHAR(100)  NOT NULL,
    activo      TINYINT(1)    DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO coches (id, nombre, categoria, precio_dia, imagen) VALUES
(1, 'BMW Serie 3',        'Berlina Premium', 65.00,  'bmwserie3.jpg'),
(2, 'Mercedes Clase C',   'Lujo',            85.00,  'mercedesclasec.jpg'),
(3, 'Range Rover Evoque', 'SUV',            110.00,  'rangeroverevoque.png');


-- ── CIUDADES DE ORIGEN ───────────────────────────────────────
CREATE TABLE ciudades_origen (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL,
    codigo  VARCHAR(10)  NOT NULL,
    pais    VARCHAR(80)  NOT NULL,
    activo  TINYINT(1)   DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ciudades_origen (nombre, codigo, pais) VALUES
    ('Madrid',            'MAD', 'España'),
    ('Barcelona',         'BCN', 'España'),
    ('Málaga',            'AGP', 'España'),
    ('Valencia',          'VLC', 'España'),
    ('Sevilla',           'SVQ', 'España'),
    ('Bilbao',            'BIO', 'España'),
    ('Alicante',          'ALC', 'España'),
    ('Gran Canaria',      'LPA', 'España'),
    ('Palma de Mallorca', 'PMI', 'España'),
    ('Zaragoza',          'ZAZ', 'España');


-- ── DESTINOS (20 destinos, IDs 1–20) ────────────────────────
CREATE TABLE destinos (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    pais        VARCHAR(100) NOT NULL,
    continente  VARCHAR(60)  NOT NULL,
    descripcion TEXT,
    activo      TINYINT(1)   DEFAULT 1,
    INDEX idx_nombre (nombre),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO destinos (id, nombre, pais, continente, descripcion) VALUES
    (1,  'Maldivas',        'Maldivas',          'Asia',    'Paraíso de aguas cristalinas y bungalós sobre el agua'),
    (2,  'Cancún',          'México',             'América', 'Playas de arena blanca y cultura maya'),
    (3,  'París',           'Francia',            'Europa',  'La ciudad del amor y la Torre Eiffel'),
    (4,  'Tokio',           'Japón',              'Asia',    'Modernidad y tradición milenaria en perfecta armonía'),
    (5,  'Santorini',       'Grecia',             'Europa',  'Casas blancas y puestas de sol incomparables'),
    (6,  'Dubái',           'Emiratos Árabes',    'Asia',    'Lujo y arquitectura futurista en el desierto'),
    (7,  'Nueva York',      'Estados Unidos',     'América', 'La ciudad que nunca duerme'),
    (8,  'Bali',            'Indonesia',          'Asia',    'Isla de los dioses, templos y arrozales'),
    (9,  'Roma',            'Italia',             'Europa',  'La ciudad eterna, cuna de la civilización occidental'),
    (10, 'Bangkok',         'Tailandia',          'Asia',    'Templos dorados, mercados flotantes y gastronomía única'),
    (11, 'Marrakech',       'Marruecos',          'África',  'Zoco, palacios y el encanto del norte africano'),
    (12, 'Phuket',          'Tailandia',          'Asia',    'Playas paradisíacas y vida nocturna vibrante'),
    (13, 'Ámsterdam',       'Países Bajos',       'Europa',  'Canales, museos y una cultura única'),
    (14, 'Río de Janeiro',  'Brasil',             'América', 'Carnaval, samba y las playas de Copacabana'),
    (15, 'Capadocia',       'Turquía',            'Asia',    'Globos aerostáticos sobre formaciones rocosas mágicas'),
    (16, 'Lisboa',          'Portugal',           'Europa',  'Fado, pastéis de nata y miradores con vistas al Tajo'),
    (17, 'Praga',           'República Checa',    'Europa',  'Ciudad de las cien torres y arquitectura gótica medieval'),
    (18, 'Bergen',          'Noruega',            'Europa',  'Puerta de entrada a los fiordos noruegos y naturaleza ártica'),
    (19, 'Cusco',           'Perú',               'América', 'Capital del Imperio Inca y entrada a Machu Picchu'),
    (20, 'Islas Canarias',  'España',             'Europa',  'Clima eterno, volcanes y playas únicas en el Atlántico');

ALTER TABLE destinos AUTO_INCREMENT = 21;


-- ── PAQUETES (32 paquetes, IDs 1–32) ────────────────────────
CREATE TABLE paquetes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    destino_id      INT           NOT NULL,
    nombre          VARCHAR(200)  NOT NULL,
    descripcion     TEXT,
    imagen_url      VARCHAR(255),
    noches          INT           NOT NULL,
    regimen         ENUM('Todo incluido','Media pensión','Solo alojamiento','Vuelo + hotel','Vuelo + traslados') NOT NULL,
    tipo            ENUM('vuelo','hotel','paquete','crucero','circuito','finde') NOT NULL DEFAULT 'paquete',
    precio_persona  DECIMAL(10,2) NOT NULL,
    precio_original DECIMAL(10,2),
    estrellas       INT           DEFAULT 4,
    aerolinea       VARCHAR(100),
    badge           VARCHAR(50),
    badge_tipo          ENUM('info','oferta','popular','urgente') DEFAULT 'info',
    activo              TINYINT(1)    DEFAULT 1,
    plazas_disponibles  INT           DEFAULT 20,
    atributos          JSON          DEFAULT NULL,
    created_at          DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_destino      (destino_id),
    INDEX idx_activo_precio (activo, precio_persona),
    INDEX idx_badge_tipo   (badge_tipo),
    INDEX idx_tipo         (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO paquetes (id, destino_id, nombre, noches, regimen, tipo, precio_persona, precio_original, estrellas, aerolinea, badge, badge_tipo) VALUES
-- ── PAQUETES ──────────────────────────────────────────────────
(1,  1,  'Maldivas Lujo Todo Incluido',      7,  'Todo incluido',     'paquete',  1899.00, 2199.00, 5, 'Emirates',         'Más vendido',    'popular'),
(2,  1,  'Maldivas Premium 10 Noches',       10, 'Todo incluido',     'paquete',  2399.00,    NULL, 5, 'Qatar Airways',    NULL,             'info'   ),
(3,  1,  'Maldivas Escapada Express',        5,  'Media pensión',     'paquete',  1299.00, 1499.00, 4, 'Iberia',           'Precio bajo',    'oferta' ),
(4,  2,  'Cancún Todo Incluido 10 Días',     10, 'Todo incluido',     'paquete',  1299.00,    NULL, 4, 'Air Europa',       NULL,             'info'   ),
(5,  2,  'Cancún y Riviera Maya',            12, 'Todo incluido',     'paquete',  1649.00, 1899.00, 5, 'Iberia',           'Oferta',         'oferta' ),
-- ── VUELOS ────────────────────────────────────────────────────
(6,  3,  'París Romántico',                  4,  'Vuelo + hotel',     'vuelo',     499.00,  599.00, 4, 'Vueling',          'Oferta',         'oferta' ),
(7,  3,  'París Clásico 6 Noches',           6,  'Vuelo + hotel',     'vuelo',     749.00,    NULL, 4, 'Air France',       NULL,             'info'   ),
-- ── CIRCUITOS ─────────────────────────────────────────────────
(8,  4,  'Tokio y Kioto',                    9,  'Vuelo + traslados', 'circuito', 1650.00,    NULL, 4, 'Japan Airlines',   NULL,             'info'   ),
-- ── HOTELES ───────────────────────────────────────────────────
(9,  5,  'Santorini y Mykonos',              6,  'Solo alojamiento',  'hotel',     799.00,    NULL, 5, 'Aegean',           'Nuevo',          'info'   ),
(10, 6,  'Dubái Lujo 7 Noches',              7,  'Media pensión',     'hotel',    1149.00, 1299.00, 5, 'Emirates',         NULL,             'info'   ),
-- ── VUELOS (cont.) ────────────────────────────────────────────
(11, 7,  'Nueva York Clásico',               5,  'Vuelo + hotel',     'vuelo',     899.00,    NULL, 4, 'Iberia',           NULL,             'info'   ),
-- ── PAQUETES (cont.) ─────────────────────────────────────────
(12, 8,  'Bali Espiritual 10 Días',          10, 'Todo incluido',     'paquete',  1399.00, 1599.00, 4, 'Singapore Air.',   'Últimas plazas', 'urgente'),
-- ── FIN DE SEMANA ─────────────────────────────────────────────
(13, 9,  'Roma Eterna',                      5,  'Vuelo + hotel',     'finde',     549.00,    NULL, 4, 'Vueling',          NULL,             'info'   ),
-- ── CIRCUITOS (cont.) ────────────────────────────────────────
(14, 10, 'Bangkok y Phuket Combinado',       12, 'Vuelo + traslados', 'circuito',  999.00, 1199.00, 4, 'Thai Airways',     'Oferta',         'oferta' ),
-- ── FIN DE SEMANA (cont.) ────────────────────────────────────
(15, 11, 'Marrakech Express',                4,  'Vuelo + hotel',     'finde',     399.00,    NULL, 4, 'Ryanair',          NULL,             'info'   ),
-- ── PAQUETES (cont.) ─────────────────────────────────────────
(16, 15, 'Capadocia Mágica',                 6,  'Vuelo + hotel',     'paquete',   699.00,  849.00, 4, 'Turkish Airlines', 'Nuevo',          'info'   ),
-- ── VUELOS (cont.) ────────────────────────────────────────────
(17, 16, 'Lisboa Vuelo + Hotel 4 Noches',    4,  'Vuelo + hotel',     'vuelo',     299.00,  349.00, 4, 'TAP Air Portugal', 'Precio bajo',    'oferta' ),
(18, 17, 'Praga Vuelo + Hotel 4 Noches',     4,  'Vuelo + hotel',     'vuelo',     319.00,    NULL, 4, 'Wizz Air',         NULL,             'info'   ),
-- ── HOTELES (cont.) ───────────────────────────────────────────
(19, 16, 'Hotel Boutique Lisboa',            4,  'Solo alojamiento',  'hotel',     289.00,    NULL, 4, 'Pestana Hotels',   NULL,             'info'   ),
(20, 13, 'Hotel Canales Ámsterdam',          3,  'Solo alojamiento',  'hotel',     379.00,  429.00, 4, 'NH Hotels',        'Oferta',         'oferta' ),
(21, 12, 'Resort Phuket Playa',              7,  'Solo alojamiento',  'hotel',     649.00,    NULL, 5, 'Six Senses',       'Nuevo',          'info'   ),
-- ── CRUCEROS ──────────────────────────────────────────────────
(22, 5,  'Crucero Mediterráneo 7 Noches',    7,  'Todo incluido',     'crucero',   899.00, 1099.00, 4, 'MSC Cruceros',     'Más vendido',    'popular'),
(23, 2,  'Crucero Caribe 10 Noches',         10, 'Todo incluido',     'crucero',  1299.00,    NULL, 5, 'Costa Cruceros',   NULL,             'info'   ),
(24, 18, 'Crucero Norte de Europa',          10, 'Todo incluido',     'crucero',  1799.00,    NULL, 5, 'Norwegian Cruise', 'Nuevo',          'info'   ),
(25, 20, 'Crucero Islas Canarias',           5,  'Todo incluido',     'crucero',   599.00,  699.00, 4, 'Pullmantur',       'Oferta',         'oferta' ),
(26, 10, 'Gran Crucero por Asia',            14, 'Todo incluido',     'crucero',  2199.00,    NULL, 5, 'Royal Caribbean',  NULL,             'info'   ),
-- ── CIRCUITOS (cont.) ────────────────────────────────────────
(27, 9,  'Circuito Italia Completa',         9,  'Vuelo + traslados', 'circuito', 1199.00, 1399.00, 4, 'Iberia',           'Más vendido',    'popular'),
(28, 19, 'Circuito Perú y Machu Picchu',     12, 'Vuelo + traslados', 'circuito', 2499.00,    NULL, 4, 'Iberia',           'Nuevo',          'info'   ),
(29, 17, 'Circuito Ruta de los Balcanes',    10, 'Vuelo + traslados', 'circuito', 1399.00, 1599.00, 4, 'Air Serbia',       'Oferta',         'oferta' ),
-- ── FIN DE SEMANA (cont.) ────────────────────────────────────
(30, 16, 'Lisboa en 3 Días',                 3,  'Vuelo + hotel',     'finde',     259.00,  299.00, 4, 'TAP Air Portugal', 'Precio bajo',    'oferta' ),
(31, 17, 'Praga Fin de Semana',              3,  'Vuelo + hotel',     'finde',     229.00,    NULL, 4, 'Wizz Air',         NULL,             'info'   ),
(32, 13, 'Ámsterdam Escapada Express',       3,  'Vuelo + hotel',     'finde',     299.00,  349.00, 4, 'KLM',              NULL,             'info'   );

ALTER TABLE paquetes AUTO_INCREMENT = 33;

-- Atributos de hoteles
UPDATE paquetes SET atributos = '["piscina","wifi","spa","terraza","primera_linea_playa","aire_acondicionado"]'           WHERE id = 9;
UPDATE paquetes SET atributos = '["piscina","wifi","spa","gimnasio","restaurante","bar","desayuno_incluido"]'             WHERE id = 10;
UPDATE paquetes SET atributos = '["wifi","desayuno_incluido","aire_acondicionado","centro_ciudad","cancelacion_gratuita"]' WHERE id = 19;
UPDATE paquetes SET atributos = '["wifi","desayuno_incluido","aire_acondicionado","centro_ciudad","parking"]'             WHERE id = 20;
UPDATE paquetes SET atributos = '["piscina","wifi","spa","gimnasio","restaurante","primera_linea_playa","acceso_playa"]'  WHERE id = 21;


-- ── RESERVAS ─────────────────────────────────────────────────
CREATE TABLE reservas (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    referencia          VARCHAR(20)   NOT NULL UNIQUE,
    usuario_id          INT           NULL,
    paquete_id          INT           NOT NULL,
    origen_id           INT           NOT NULL,
    fecha_salida        DATE          NOT NULL,
    fecha_regreso       DATE          NOT NULL,
    num_adultos         INT           DEFAULT 2,
    num_ninos           INT           DEFAULT 0,
    precio_total        DECIMAL(10,2) NOT NULL,
    seguro_cancelacion  TINYINT(1)    DEFAULT 0,
    coche_id            INT           NULL,
    precio_coche        DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    estado              ENUM('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
    created_at          DATETIME      DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_paquete (paquete_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── VIAJEROS ─────────────────────────────────────────────────
CREATE TABLE viajeros (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id       INT          NOT NULL,
    nombre           VARCHAR(100) NOT NULL,
    apellidos        VARCHAR(150) NOT NULL,
    documento        VARCHAR(30)  NOT NULL,
    fecha_nacimiento DATE,
    nacionalidad     VARCHAR(80)  DEFAULT 'Española',
    tipo             ENUM('adulto','niño') DEFAULT 'adulto',
    INDEX idx_reserva (reserva_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── CONTACTOS DE RESERVA ─────────────────────────────────────
CREATE TABLE contactos_reserva (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id  INT          NOT NULL UNIQUE,
    telefono    VARCHAR(30),
    email       VARCHAR(150),
    comentarios TEXT,
    INDEX idx_reserva (reserva_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── PAGOS (simulado) ────────────────────────────────
CREATE TABLE pagos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id      INT           NOT NULL,
    transaccion_id  VARCHAR(30)   NOT NULL UNIQUE,
    metodo          VARCHAR(30)   NOT NULL DEFAULT 'tarjeta_simulada',
    titular         VARCHAR(200)  NOT NULL,
    ultimos_digitos CHAR(4)       NOT NULL,
    importe         DECIMAL(10,2) NOT NULL,
    estado          ENUM('aprobado','rechazado','pendiente') DEFAULT 'aprobado',
    created_at      DATETIME      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reserva (reserva_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ── SERVICIOS ────────────────────────────────────────────────
CREATE TABLE servicios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(100) NOT NULL,
    icono       VARCHAR(50)  NOT NULL DEFAULT 'check',
    descripcion VARCHAR(200),
    activo      TINYINT(1)   DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO servicios (id, nombre, icono, descripcion) VALUES
(1, 'Transfer aeropuerto', 'transfer',  'Traslado privado aeropuerto–hotel ida y vuelta'),
(2, 'Wi-Fi gratuito',      'wifi',      'Conexión Wi-Fi de alta velocidad incluida'),
(3, 'Desayuno incluido',   'breakfast', 'Desayuno buffet diario en el hotel'),
(4, 'Seguro de viaje',     'shield',    'Cobertura médica y de cancelación básica'),
(5, 'Guía turístico',      'guide',     'Guía local certificado durante el recorrido'),
(6, 'Excursión incluida',  'map',       'Una excursión organizada sin coste adicional'),
(7, 'Traslados locales',   'bus',       'Desplazamientos en destino incluidos'),
(8, 'Asistencia 24h',      'support',   'Servicio de atención al cliente 24/7 en destino');


-- ── PAQUETE_SERVICIOS — tabla intermedia N:M ─────────────────
CREATE TABLE paquete_servicios (
    paquete_id  INT NOT NULL,
    servicio_id INT NOT NULL,
    PRIMARY KEY (paquete_id, servicio_id),
    INDEX idx_servicio (servicio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO paquete_servicios (paquete_id, servicio_id) VALUES
-- Todo incluido (1,2,4,5,12,22,23,24,25,26) → todos los servicios
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),
(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,7),(2,8),
(4,1),(4,2),(4,3),(4,4),(4,5),(4,6),(4,7),(4,8),
(5,1),(5,2),(5,3),(5,4),(5,5),(5,6),(5,7),(5,8),
(12,1),(12,2),(12,3),(12,4),(12,5),(12,6),(12,7),(12,8),
(22,1),(22,2),(22,3),(22,4),(22,5),(22,6),(22,7),(22,8),
(23,1),(23,2),(23,3),(23,4),(23,5),(23,6),(23,7),(23,8),
(24,1),(24,2),(24,3),(24,4),(24,5),(24,6),(24,7),(24,8),
(25,1),(25,2),(25,3),(25,4),(25,5),(25,6),(25,7),(25,8),
(26,1),(26,2),(26,3),(26,4),(26,5),(26,6),(26,7),(26,8),
-- Media pensión (3,10)
(3,1),(3,2),(3,3),(3,4),(3,7),(3,8),
(10,1),(10,2),(10,3),(10,4),(10,7),(10,8),
-- Solo alojamiento (9,19,20,21)
(9,2),(9,3),(9,8),
(19,2),(19,3),(19,8),
(20,2),(20,3),(20,8),
(21,2),(21,3),(21,8),
-- Vuelo + hotel / finde (6,7,11,13,15,16,17,18,30,31,32)
(6,1),(6,2),(6,4),(6,8),
(7,1),(7,2),(7,4),(7,8),
(11,1),(11,2),(11,4),(11,8),
(13,1),(13,2),(13,4),(13,8),
(15,1),(15,2),(15,4),(15,8),
(16,1),(16,2),(16,4),(16,8),
(17,1),(17,2),(17,4),(17,8),
(18,1),(18,2),(18,4),(18,8),
(30,1),(30,2),(30,4),(30,8),
(31,1),(31,2),(31,4),(31,8),
(32,1),(32,2),(32,4),(32,8),
-- Circuitos / Vuelo + traslados (8,14,27,28,29)
(8,1),(8,4),(8,5),(8,6),(8,7),(8,8),
(14,1),(14,4),(14,5),(14,6),(14,7),(14,8),
(27,1),(27,4),(27,5),(27,6),(27,7),(27,8),
(28,1),(28,4),(28,5),(28,6),(28,7),(28,8),
(29,1),(29,4),(29,5),(29,6),(29,7),(29,8);


-- ── CLAVES FORÁNEAS ──────────────────────────────────────────
ALTER TABLE paquetes          ADD FOREIGN KEY (destino_id)  REFERENCES destinos(id);
ALTER TABLE reservas          ADD FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)          ON DELETE SET NULL;
ALTER TABLE reservas          ADD FOREIGN KEY (paquete_id)  REFERENCES paquetes(id);
ALTER TABLE reservas          ADD FOREIGN KEY (origen_id)   REFERENCES ciudades_origen(id);
ALTER TABLE reservas          ADD FOREIGN KEY (coche_id)    REFERENCES coches(id)            ON DELETE SET NULL;
ALTER TABLE viajeros          ADD FOREIGN KEY (reserva_id)  REFERENCES reservas(id);
ALTER TABLE contactos_reserva ADD FOREIGN KEY (reserva_id)  REFERENCES reservas(id);
ALTER TABLE password_resets   ADD FOREIGN KEY (usuario_id)  REFERENCES usuarios(id) ON DELETE CASCADE;
ALTER TABLE pagos             ADD FOREIGN KEY (reserva_id)  REFERENCES reservas(id) ON DELETE CASCADE;
ALTER TABLE paquete_servicios ADD FOREIGN KEY (paquete_id)  REFERENCES paquetes(id)  ON DELETE CASCADE;
ALTER TABLE paquete_servicios ADD FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
--  3. TABLA DE AUDITORÍA Y TRIGGERS
-- ============================================================

-- Tabla donde los triggers registran el histórico de cambios
CREATE TABLE IF NOT EXISTS reservas_auditoria (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id      INT          NOT NULL,
    usuario_id      INT          NULL,
    accion          ENUM('creada','cambio_estado','cancelada') NOT NULL,
    estado_anterior VARCHAR(20)  NULL,
    estado_nuevo    VARCHAR(20)  NULL,
    db_user         VARCHAR(100) NOT NULL DEFAULT (CURRENT_USER()),
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reserva (reserva_id),
    INDEX idx_fecha   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpiar versiones previas (permite re-ejecutar el script)
DROP TRIGGER IF EXISTS trg_reservas_after_insert;
DROP TRIGGER IF EXISTS trg_reservas_after_update;

DELIMITER //

-- ── TRIGGER 1 — AFTER INSERT ─────────────────────────────────
-- Al crear una reserva:
--   · descuenta las plazas del paquete (adultos + niños)
--   · deja constancia en la tabla de auditoría
CREATE TRIGGER trg_reservas_after_insert
AFTER INSERT ON reservas
FOR EACH ROW
BEGIN
    UPDATE paquetes
       SET plazas_disponibles = GREATEST(0, plazas_disponibles - (NEW.num_adultos + NEW.num_ninos))
     WHERE id = NEW.paquete_id
       AND plazas_disponibles IS NOT NULL;

    INSERT INTO reservas_auditoria (reserva_id, usuario_id, accion, estado_anterior, estado_nuevo)
    VALUES (NEW.id, NEW.usuario_id, 'creada', NULL, NEW.estado);
END//

-- ── TRIGGER 2 — AFTER UPDATE ─────────────────────────────────
-- Al cambiar el estado de una reserva:
--   · si pasa a 'cancelada', devuelve las plazas al paquete
--   · registra el cambio de estado en la auditoría
CREATE TRIGGER trg_reservas_after_update
AFTER UPDATE ON reservas
FOR EACH ROW
BEGIN
    IF NEW.estado <> OLD.estado THEN

        IF NEW.estado = 'cancelada' AND OLD.estado <> 'cancelada' THEN
            UPDATE paquetes
               SET plazas_disponibles = plazas_disponibles + (OLD.num_adultos + OLD.num_ninos)
             WHERE id = OLD.paquete_id
               AND plazas_disponibles IS NOT NULL;
        END IF;

        INSERT INTO reservas_auditoria (reserva_id, usuario_id, accion, estado_anterior, estado_nuevo)
        VALUES (NEW.id, NEW.usuario_id,
                IF(NEW.estado = 'cancelada', 'cancelada', 'cambio_estado'),
                OLD.estado, NEW.estado);
    END IF;
END//

DELIMITER ;


-- ============================================================
--  4. USUARIOS DE BASE DE DATOS Y PRIVILEGIOS
--  Módulo: Administración de Sistemas Gestores de Bases de Datos
--  Criterio: "Creación de usuarios con distintos privilegios"
--
--  Ejecutar como root:
--    Docker: docker exec -i rp_db mysql -uroot -prootpassword < sql/rp.sql
--
--  NOTA: en Docker se usa '%' como host porque los contenedores
--  se comunican entre sí por red interna, no por localhost.
-- ============================================================

-- Limpieza para poder re-ejecutar sin errores
DROP USER IF EXISTS 'rp_admin'@'%';
DROP USER IF EXISTS 'rp_app'@'%';
DROP USER IF EXISTS 'rp_readonly'@'%';
DROP USER IF EXISTS 'rp_backup'@'%';

-- ── 1) ADMINISTRADOR (DBA del proyecto) ──────────────────────
--    Control total sobre rp_travels
CREATE USER 'rp_admin'@'%' IDENTIFIED BY 'Admin_RP_2026!';
GRANT ALL PRIVILEGES ON rp_travels.* TO 'rp_admin'@'%' WITH GRANT OPTION;

-- ── 2) APLICACIÓN (usuario que usa la web en producción) ─────
--    Solo CRUD, sin permisos DDL — mínimo privilegio
CREATE USER 'rp_app'@'%' IDENTIFIED BY 'App_RP_2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON rp_travels.* TO 'rp_app'@'%';

-- ── 3) SOLO LECTURA (informes, KPIs, consultas) ──────────────
CREATE USER 'rp_readonly'@'%' IDENTIFIED BY 'Read_RP_2026!';
GRANT SELECT ON rp_travels.* TO 'rp_readonly'@'%';

-- ── 4) COPIAS DE SEGURIDAD (usuario dedicado para mysqldump) ─
--    Privilegios mínimos para un volcado consistente
CREATE USER 'rp_backup'@'%' IDENTIFIED BY 'Backup_RP_2026!';
GRANT SELECT, SHOW VIEW, LOCK TABLES, TRIGGER, EVENT
      ON rp_travels.* TO 'rp_backup'@'%';

FLUSH PRIVILEGES;

-- ── Comprobaciones rápidas tras instalar ─────────────────────
--   SHOW TRIGGERS;
--   SELECT * FROM reservas_auditoria ORDER BY created_at DESC;
--   SELECT user, host FROM mysql.user WHERE user LIKE 'rp_%';
--   SHOW GRANTS FOR 'rp_app'@'%';