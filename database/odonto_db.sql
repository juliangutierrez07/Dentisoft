-- ============================================================
-- BASE DE DATOS: odonto_db
-- Sistema de Gestión Odontológica — DentiSoft 1.0
-- Neiva, Huila — Colombia
-- ============================================================

CREATE DATABASE IF NOT EXISTS odonto_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE odonto_db;

-- ------------------------------------------------------------
-- TABLA: roles
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(200),
    permisos JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rol_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(20),
    estado ENUM('activo','inactivo','suspendido') DEFAULT 'activo',
    ultimo_acceso DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: pacientes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pacientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_documento VARCHAR(20) NOT NULL UNIQUE,
    tipo_documento ENUM('CC','TI','CE','PAS','RC') DEFAULT 'CC',
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE,
    genero ENUM('M','F','Otro'),
    telefono VARCHAR(20),
    email VARCHAR(150),
    direccion TEXT,
    ciudad VARCHAR(100) DEFAULT 'Neiva',
    eps VARCHAR(100),
    tipo_afiliacion ENUM('contributivo','subsidiado','particular','otro') DEFAULT 'particular',
    grupo_sanguineo VARCHAR(5),
    estado ENUM('activo','inactivo') DEFAULT 'activo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: paciente_accesos
-- Credenciales separadas para el Portal del Paciente
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS paciente_accesos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    usuario_documento VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
    estado ENUM('activo','inactivo','suspendido') NOT NULL DEFAULT 'activo',
    ultimo_acceso DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_paciente_accesos_paciente (paciente_id),
    UNIQUE KEY uq_paciente_accesos_documento (usuario_documento),
    KEY idx_paciente_accesos_estado (estado),
    KEY idx_paciente_accesos_ultimo_acceso (ultimo_acceso),
    CONSTRAINT fk_paciente_accesos_paciente
        FOREIGN KEY (paciente_id) REFERENCES pacientes(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: historias_clinicas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS historias_clinicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    numero_historia VARCHAR(20) NOT NULL UNIQUE,
    fecha_apertura DATE NOT NULL,
    motivo_consulta TEXT,
    enfermedad_actual TEXT,
    antecedentes_medicos TEXT,
    antecedentes_odontologicos TEXT,
    medicamentos_actuales TEXT,
    alergias TEXT,
    habito_tabaco BOOLEAN DEFAULT FALSE,
    habito_alcohol BOOLEAN DEFAULT FALSE,
    habito_bruxismo BOOLEAN DEFAULT FALSE,
    otros_habitos TEXT,
    presion_arterial VARCHAR(10),
    frecuencia_cardiaca INT,
    temperatura DECIMAL(4,1),
    examen_extraoral TEXT,
    examen_intraoral TEXT,
    diagnostico TEXT,
    plan_tratamiento_inicial TEXT,
    observaciones TEXT,
    estado ENUM('activa','archivada') DEFAULT 'activa',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (odontologo_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: odontograma
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS odontograma (
    id INT AUTO_INCREMENT PRIMARY KEY,
    historia_id INT NOT NULL,
    pieza_dental VARCHAR(5) NOT NULL,
    estado ENUM(
        'sano','caries','obturado','extraccion_indicada','ausente',
        'corona','protesis','implante','fractura','tratamiento_conductos','otro'
    ) DEFAULT 'sano',
    caras_afectadas JSON,
    color_estado VARCHAR(7) DEFAULT '#28a745',
    notas TEXT,
    usuario_id INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (historia_id) REFERENCES historias_clinicas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY unique_pieza (historia_id, pieza_dental)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: imagenes_clinicas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS imagenes_clinicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    historia_id INT NOT NULL,
    pieza_dental VARCHAR(5),
    tipo ENUM('radiografia','foto_clinica','otro') DEFAULT 'foto_clinica',
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta_archivo VARCHAR(500) NOT NULL,
    tamanio_bytes INT,
    descripcion TEXT,
    usuario_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (historia_id) REFERENCES historias_clinicas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: procedimientos_catalogo
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS procedimientos_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    descripcion TEXT,
    precio_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duracion_minutos INT DEFAULT 30,
    categoria ENUM(
        'diagnostico','preventivo','restaurador','endodoncia',
        'cirugia','ortodoncia','protesis','periodoncia','otro'
    ) DEFAULT 'otro',
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: planes_tratamiento
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS planes_tratamiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    historia_id INT NOT NULL,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    nombre_plan VARCHAR(200),
    descripcion TEXT,
    fecha_inicio DATE,
    fecha_fin_estimada DATE,
    costo_total DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('pendiente','en_curso','completado','cancelado') DEFAULT 'pendiente',
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (historia_id) REFERENCES historias_clinicas(id),
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (odontologo_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: sesiones_tratamiento
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sesiones_tratamiento (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    numero_sesion INT NOT NULL,
    procedimiento_id INT,
    pieza_dental VARCHAR(5),
    descripcion TEXT,
    observaciones_sesion TEXT,
    costo_sesion DECIMAL(10,2) DEFAULT 0.00,
    fecha_programada DATE,
    fecha_realizada DATE,
    estado ENUM('pendiente','realizada','cancelada') DEFAULT 'pendiente',
    progreso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notas TEXT,
    fecha_ultimo_avance DATETIME,
    odontologo_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plan_id) REFERENCES planes_tratamiento(id),
    FOREIGN KEY (procedimiento_id) REFERENCES procedimientos_catalogo(id),
    FOREIGN KEY (odontologo_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: citas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS citas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    plan_id INT,
    sesion_id INT,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo TEXT,
    estado ENUM('pendiente','confirmada','atendida','cancelada','no_asistio') DEFAULT 'pendiente',
    recordatorio_enviado BOOLEAN DEFAULT FALSE,
    notas TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (odontologo_id) REFERENCES usuarios(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: facturas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_factura VARCHAR(20) NOT NULL UNIQUE,
    paciente_id INT NOT NULL,
    odontologo_id INT NOT NULL,
    plan_id INT,
    fecha_emision DATE NOT NULL,
    fecha_vencimiento DATE,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    descuento DECIMAL(10,2) DEFAULT 0.00,
    iva DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_pagado DECIMAL(10,2) DEFAULT 0.00,
    saldo_pendiente DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('pendiente','pagada','parcial','vencida','anulada') DEFAULT 'pendiente',
    notas TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
    FOREIGN KEY (odontologo_id) REFERENCES usuarios(id),
    FOREIGN KEY (created_by) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: detalle_facturas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS detalle_facturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    procedimiento_id INT,
    descripcion VARCHAR(300) NOT NULL,
    pieza_dental VARCHAR(5),
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento_item DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (factura_id) REFERENCES facturas(id),
    FOREIGN KEY (procedimiento_id) REFERENCES procedimientos_catalogo(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: pagos
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    factura_id INT NOT NULL,
    fecha_pago DATE NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo','transferencia','tarjeta_credito','tarjeta_debito','cheque','otro') DEFAULT 'efectivo',
    referencia_pago VARCHAR(100),
    observaciones TEXT,
    registrado_por INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (factura_id) REFERENCES facturas(id),
    FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: notificaciones
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    tipo ENUM('cita','pago','sistema','alerta') DEFAULT 'sistema',
    titulo VARCHAR(200) NOT NULL,
    mensaje TEXT NOT NULL,
    leida BOOLEAN DEFAULT FALSE,
    url_accion VARCHAR(300),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- TABLA: audit_log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,
    tabla_afectada VARCHAR(100),
    registro_id INT,
    datos_anteriores JSON,
    datos_nuevos JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATOS INICIALES
-- ============================================================

-- Roles
INSERT INTO roles (nombre, descripcion, permisos) VALUES
('administrador', 'Acceso total al sistema', '{"pacientes":"crud","historias":"crud","citas":"crud","tratamientos":"crud","facturacion":"crud","reportes":"crud","usuarios":"crud"}'),
('odontologo', 'Acceso a historias, citas, tratamientos y facturación', '{"pacientes":"cr","historias":"crud","citas":"crud","tratamientos":"crud","facturacion":"cr","reportes":"r","usuarios":""}'),
('asistente', 'Acceso a citas, pacientes y notificaciones', '{"pacientes":"cr","historias":"r","citas":"crud","tratamientos":"r","facturacion":"r","reportes":"","usuarios":""}');

-- Usuario administrador (Password: Admin123*)
INSERT INTO usuarios (rol_id, nombre, apellido, email, password, telefono) VALUES
(1, 'Administrador', 'Sistema', 'admin@dentisoft.com',
 '$2y$10$gJtKMjtvI5ET9BzafeHIbOgZp.Ku3MIoJkiFLB3KB8TaETGTXj272', '3100000000');

-- Odontólogo de ejemplo
INSERT INTO usuarios (rol_id, nombre, apellido, email, password, telefono) VALUES
(2, 'Carlos', 'Martínez López', 'carlos.martinez@dentisoft.com',
 '$2y$10$gJtKMjtvI5ET9BzafeHIbOgZp.Ku3MIoJkiFLB3KB8TaETGTXj272', '3151234567');

-- Asistente de ejemplo
INSERT INTO usuarios (rol_id, nombre, apellido, email, password, telefono) VALUES
(3, 'María', 'García Rojas', 'maria.garcia@dentisoft.com',
 '$2y$10$gJtKMjtvI5ET9BzafeHIbOgZp.Ku3MIoJkiFLB3KB8TaETGTXj272', '3209876543');

-- Procedimientos base (CUPS odontología Colombia)
INSERT INTO procedimientos_catalogo (codigo, nombre, categoria, precio_base, duracion_minutos) VALUES
('890201', 'Consulta de primera vez odontología general', 'diagnostico', 35000, 30),
('890202', 'Consulta de control o seguimiento', 'diagnostico', 25000, 20),
('890301', 'Limpieza y profilaxis dental', 'preventivo', 60000, 45),
('890401', 'Restauración en resina compuesta 1 cara', 'restaurador', 80000, 45),
('890402', 'Restauración en resina compuesta 2 caras', 'restaurador', 110000, 60),
('890403', 'Restauración en resina compuesta 3 caras', 'restaurador', 140000, 75),
('890501', 'Extracción dental simple', 'cirugia', 70000, 30),
('890502', 'Extracción dental compleja / cordal', 'cirugia', 180000, 60),
('890601', 'Tratamiento de conductos unirradicular', 'endodoncia', 350000, 90),
('890602', 'Tratamiento de conductos birradicular', 'endodoncia', 420000, 90),
('890701', 'Corona metalcerámica', 'protesis', 650000, 60),
('890801', 'Aplicación de sellantes', 'preventivo', 40000, 30),
('890901', 'Blanqueamiento dental profesional', 'otro', 280000, 60);

-- ============================================================
-- DATOS DE PRUEBA
-- ============================================================

-- Pacientes de ejemplo
INSERT INTO pacientes (numero_documento, tipo_documento, nombre, apellido, fecha_nacimiento, genero, telefono, email, direccion, ciudad, eps, tipo_afiliacion, grupo_sanguineo) VALUES
('1075312456', 'CC', 'Ana María', 'Rodríguez Pérez', '1990-03-15', 'F', '3112345678', 'ana.rodriguez@email.com', 'Calle 10 #5-23 Barrio Centro', 'Neiva', 'Nueva EPS', 'contributivo', 'O+'),
('1075456789', 'CC', 'Juan Carlos', 'López Silva', '1985-07-22', 'M', '3156789012', 'juan.lopez@email.com', 'Carrera 15 #8-45 Barrio Altico', 'Neiva', 'Sanitas', 'contributivo', 'A+'),
('1075234567', 'CC', 'Laura Patricia', 'Martínez Gómez', '1995-11-08', 'F', '3201234567', 'laura.martinez@email.com', 'Avenida Circunvalar Cra 1 #21-50', 'Neiva', 'Medimás', 'subsidiado', 'B+'),
('1075567890', 'CC', 'Pedro Antonio', 'Hernández Ruiz', '1978-01-30', 'M', '3178901234', 'pedro.hernandez@email.com', 'Calle 21 #3-15 Barrio Calixto', 'Neiva', NULL, 'particular', 'AB+'),
('1030987654', 'CC', 'Sofía Valentina', 'Torres Castillo', '2000-05-20', 'F', '3145678901', 'sofia.torres@email.com', 'Carrera 5 #12-30 Barrio Las Granjas', 'Neiva', 'Sura', 'contributivo', 'O-'),
('36123456', 'CC', 'Roberto', 'Díaz Vargas', '1970-09-12', 'M', '3167890123', 'roberto.diaz@email.com', 'Calle 5 #2-10 Barrio Cándido', 'Neiva', 'Compensar', 'contributivo', 'A-'),
('1075678901', 'CC', 'Camila Andrea', 'Suárez Moreno', '1998-12-03', 'F', '3123456789', 'camila.suarez@email.com', 'Avenida 26 #7-80 Barrio Santa Isabel', 'Neiva', NULL, 'particular', 'O+'),
('1020345678', 'CC', 'Diego Fernando', 'Ramírez Ortiz', '1988-04-17', 'M', '3190123456', 'diego.ramirez@email.com', 'Carrera 8 #15-42 Barrio El Jardín', 'Neiva', 'Famisanar', 'contributivo', 'B-');

-- Historias clínicas de ejemplo
INSERT INTO historias_clinicas (paciente_id, odontologo_id, numero_historia, fecha_apertura, motivo_consulta, enfermedad_actual, antecedentes_medicos, alergias, diagnostico, estado) VALUES
(1, 2, 'HC-2024-0001', '2024-01-15', 'Dolor molar superior derecho', 'Dolor agudo al masticar desde hace 3 días', 'Sin antecedentes relevantes', 'Ninguna conocida', 'Caries profunda pieza 16', 'activa'),
(2, 2, 'HC-2024-0002', '2024-02-20', 'Control y limpieza dental', NULL, 'Hipertensión controlada', 'Penicilina', 'Gingivitis leve generalizada', 'activa'),
(3, 2, 'HC-2024-0003', '2024-03-10', 'Consulta estética', NULL, 'Sin antecedentes', 'Ninguna', 'Decoloración dental anterior', 'activa');

-- Citas de ejemplo (fechas relativas a hoy)
INSERT INTO citas (paciente_id, odontologo_id, fecha, hora_inicio, hora_fin, motivo, estado, created_by) VALUES
(1, 2, CURDATE(), '08:00:00', '08:30:00', 'Control post-restauración', 'confirmada', 1),
(2, 2, CURDATE(), '09:00:00', '09:45:00', 'Limpieza dental programada', 'pendiente', 1),
(3, 2, CURDATE(), '10:00:00', '11:00:00', 'Blanqueamiento dental - Sesión 1', 'pendiente', 1),
(4, 2, CURDATE(), '14:00:00', '14:30:00', 'Consulta de primera vez', 'confirmada', 1),
(5, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '08:00:00', '08:30:00', 'Revisión de ortodoncia', 'pendiente', 1),
(6, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', '11:00:00', 'Extracción cordal inferior', 'confirmada', 1),
(7, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '09:00:00', '10:30:00', 'Tratamiento de conductos pieza 36', 'pendiente', 1),
(1, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '11:00:00', '11:30:00', 'Control y seguimiento', 'pendiente', 1);

-- Planes de tratamiento de ejemplo
INSERT INTO planes_tratamiento (historia_id, paciente_id, odontologo_id, nombre_plan, descripcion, fecha_inicio, fecha_fin_estimada, costo_total, estado) VALUES
(1, 1, 2, 'Restauración pieza 16', 'Tratamiento restaurador con resina compuesta para caries profunda', '2024-01-20', '2024-02-20', 190000, 'en_curso'),
(2, 2, 2, 'Profilaxis y tratamiento periodontal', 'Limpieza profunda y seguimiento de gingivitis', '2024-02-25', '2024-05-25', 180000, 'en_curso'),
(3, 3, 2, 'Blanqueamiento dental completo', 'Blanqueamiento profesional en consultorio - 3 sesiones', '2024-03-15', '2024-04-15', 840000, 'pendiente');

-- Facturas de ejemplo
INSERT INTO facturas (numero_factura, paciente_id, odontologo_id, plan_id, fecha_emision, fecha_vencimiento, subtotal, descuento, iva, total, total_pagado, saldo_pendiente, estado, created_by) VALUES
('FAC-2024-0001', 1, 2, 1, '2024-01-20', '2024-02-20', 190000, 0, 0, 190000, 100000, 90000, 'parcial', 1),
('FAC-2024-0002', 2, 2, 2, '2024-02-25', '2024-03-25', 180000, 0, 0, 180000, 180000, 0, 'pagada', 1),
('FAC-2024-0003', 4, 2, NULL, '2024-03-01', '2024-04-01', 35000, 0, 0, 35000, 0, 35000, 'pendiente', 1);

-- Pagos de ejemplo
INSERT INTO pagos (factura_id, fecha_pago, monto, metodo_pago, referencia_pago, observaciones, registrado_por) VALUES
(1, '2024-01-20', 100000, 'efectivo', NULL, 'Abono inicial del tratamiento', 1),
(2, '2024-02-25', 180000, 'transferencia', 'TRF-2024-0045', 'Pago total del tratamiento', 1);

-- Notificaciones de ejemplo
INSERT INTO notificaciones (usuario_id, tipo, titulo, mensaje, leida, url_accion) VALUES
(1, 'sistema', 'Bienvenido a DentiSoft', 'El sistema ha sido configurado correctamente. ¡Comienza a gestionar tu clínica!', 0, '/DentiSoft1.0/dashboard.php'),
(2, 'cita', 'Citas programadas para hoy', 'Tienes 4 citas programadas para el día de hoy.', 0, '/DentiSoft1.0/modules/citas/index.php'),
(1, 'pago', 'Factura pendiente de cobro', 'La factura FAC-2024-0001 tiene un saldo pendiente de $90,000 COP.', 0, '/DentiSoft1.0/modules/facturacion/ver.php?id=1');
