-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: odonto_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `tabla_afectada` varchar(100) DEFAULT NULL,
  `registro_id` int(11) DEFAULT NULL,
  `datos_anteriores` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_anteriores`)),
  `datos_nuevos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_nuevos`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-19 21:34:59'),(2,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-20 18:32:19'),(3,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-20 21:35:47'),(4,4,'login','usuarios',4,NULL,NULL,'::1','2026-05-20 21:35:57'),(5,4,'logout','usuarios',4,NULL,NULL,'::1','2026-05-20 21:36:20'),(6,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-20 21:43:42'),(7,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-20 21:43:57'),(8,4,'login','usuarios',4,NULL,NULL,'::1','2026-05-20 21:44:14'),(9,4,'logout','usuarios',4,NULL,NULL,'::1','2026-05-20 21:52:08'),(10,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-20 21:52:18'),(11,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-20 21:53:07'),(12,5,'login','usuarios',5,NULL,NULL,'::1','2026-05-20 21:54:51'),(13,5,'logout','usuarios',5,NULL,NULL,'::1','2026-05-20 22:17:44'),(14,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-20 23:33:13'),(15,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-20 23:39:21'),(16,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-20 23:55:19'),(17,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-20 23:55:22'),(18,4,'login','usuarios',4,NULL,NULL,'::1','2026-05-20 23:55:34'),(19,4,'logout','usuarios',4,NULL,NULL,'::1','2026-05-20 23:55:37'),(20,5,'login','usuarios',5,NULL,NULL,'::1','2026-05-21 00:55:06'),(21,5,'logout','usuarios',5,NULL,NULL,'::1','2026-05-21 00:55:12'),(22,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-21 00:55:29'),(23,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-21 00:59:35'),(24,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-21 01:09:24'),(25,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-21 01:59:07'),(26,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-21 02:22:43'),(27,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-21 02:22:54'),(28,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-21 02:23:02'),(29,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-21 14:57:30'),(30,1,'logout','usuarios',1,NULL,NULL,'::1','2026-05-21 15:48:32'),(31,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-21 18:15:25'),(32,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-22 02:01:23'),(33,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-22 15:02:33'),(34,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-22 18:12:04'),(35,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-28 14:09:17'),(36,1,'eliminar_permanente','pacientes',11,'{\"id\":11,\"numero_documento\":\"1118471378\",\"tipo_documento\":\"TI\",\"nombre\":\"Leider Fabian\",\"apellido\":\"Ramos Cano\",\"fecha_nacimiento\":\"2008-08-13\",\"genero\":\"M\",\"telefono\":\"3112533941\",\"email\":\"leiderfabianramoscano99@gmail.com\",\"direccion\":\"B\\/La Y\",\"ciudad\":\"San Jose Del Fragua\",\"eps\":\"Asmet Salud\",\"tipo_afiliacion\":\"contributivo\",\"grupo_sanguineo\":\"O+\",\"estado\":\"activo\",\"created_at\":\"2026-05-21 15:13:47\",\"updated_at\":\"2026-05-21 15:49:01\"}',NULL,'::1','2026-05-28 14:41:55'),(37,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-28 15:42:00'),(38,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-28 15:42:41'),(39,1,'login','usuarios',1,NULL,NULL,'127.0.0.1','2026-05-28 15:43:00'),(40,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-28 17:16:48'),(41,1,'inactivar','pacientes',13,'{\"id\":13,\"numero_documento\":\"6805856\",\"tipo_documento\":\"CC\",\"nombre\":\"Jaiber\",\"apellido\":\"Gutierrez Rojas\",\"fecha_nacimiento\":\"1997-07-17\",\"genero\":\"M\",\"telefono\":\"3144232446\",\"email\":\"bbultosa@gmail.com\",\"direccion\":\"B\\/ Sumawasy\",\"ciudad\":\"San Jose Del Fragua\",\"eps\":\"Asmet Salud\",\"tipo_afiliacion\":\"subsidiado\",\"grupo_sanguineo\":\"A+\",\"estado\":\"inactivo\",\"created_at\":\"2026-05-21 15:31:15\",\"updated_at\":\"2026-05-28 09:31:45\"}','{\"estado\":\"inactivo\"}','::1','2026-05-28 17:22:48'),(42,1,'login','usuarios',1,NULL,NULL,'::1','2026-05-29 02:18:16'),(43,1,'login','usuarios',1,NULL,NULL,'::1','2026-06-10 18:17:42'),(44,1,'logout','usuarios',1,NULL,NULL,'::1','2026-06-10 20:40:28'),(45,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 20:40:41'),(46,1,'logout','usuarios',1,NULL,NULL,'::1','2026-06-10 20:41:41'),(47,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 20:41:53'),(48,1,'logout','usuarios',1,NULL,NULL,'::1','2026-06-10 20:45:55'),(49,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 20:46:32'),(50,1,'logout','usuarios',1,NULL,NULL,'::1','2026-06-10 21:00:28'),(51,4,'login','usuarios',4,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 21:00:40'),(52,4,'logout','usuarios',4,NULL,NULL,'::1','2026-06-10 21:02:38'),(53,4,'login','usuarios',4,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 21:09:34'),(54,4,'logout','usuarios',4,NULL,NULL,'::1','2026-06-10 21:56:06'),(55,4,'login','usuarios',4,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 21:56:44'),(56,4,'acceso_denegado','sistema',4,NULL,'{\"permiso\":\"citas.crear\",\"uri\":\"\\/DentiSoft1.0\\/modules\\/citas\\/crear.php\",\"ip\":\"::1\"}','::1','2026-06-10 21:56:47'),(57,4,'logout','usuarios',4,NULL,NULL,'::1','2026-06-10 22:02:25'),(58,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 22:02:34'),(59,1,'logout','usuarios',1,NULL,NULL,'::1','2026-06-10 22:02:38'),(60,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-10 22:03:02'),(61,1,'login','usuarios',1,NULL,'{\"ip\":\"::1\"}','::1','2026-06-11 14:40:21');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `citas`
--

DROP TABLE IF EXISTS `citas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `citas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `odontologo_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `sesion_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `motivo` text DEFAULT NULL,
  `estado` enum('pendiente','confirmada','atendida','cancelada','no_asistio') DEFAULT 'pendiente',
  `recordatorio_enviado` tinyint(1) DEFAULT 0,
  `notas` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `odontologo_id` (`odontologo_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `citas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`odontologo_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `citas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `citas`
--

LOCK TABLES `citas` WRITE;
/*!40000 ALTER TABLE `citas` DISABLE KEYS */;
INSERT INTO `citas` VALUES (1,1,2,NULL,NULL,'2026-05-19','08:00:00','08:30:00','Control post-restauraci├│n','confirmada',0,NULL,1,'2026-05-19 21:29:47','2026-05-20 18:40:04'),(2,2,2,NULL,NULL,'2026-05-19','09:00:00','09:45:00','Limpieza dental programada','pendiente',0,NULL,1,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(3,3,2,NULL,NULL,'2026-05-19','10:00:00','11:00:00','Blanqueamiento dental - Sesi├│n 1','pendiente',0,NULL,1,'2026-05-19 21:29:47','2026-05-21 02:24:37'),(4,4,2,NULL,NULL,'2026-05-19','14:00:00','14:30:00','Consulta de primera vez','confirmada',0,NULL,1,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(5,5,2,NULL,NULL,'2026-05-20','08:00:00','08:30:00','Revisi├│n de ortodoncia','pendiente',0,NULL,1,'2026-05-19 21:29:47','2026-05-20 18:40:04'),(6,6,2,NULL,NULL,'2026-05-20','10:00:00','11:00:00','Extracci├│n cordal inferior','confirmada',0,NULL,1,'2026-05-19 21:29:47','2026-05-20 18:40:04'),(7,7,2,NULL,NULL,'2026-05-21','09:00:00','10:30:00','Tratamiento de conductos pieza 36','pendiente',0,NULL,1,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(8,1,2,NULL,NULL,'2026-05-22','11:00:00','11:30:00','Control y seguimiento','pendiente',0,NULL,1,'2026-05-19 21:29:47','2026-05-22 04:10:27'),(9,9,4,3,NULL,'2026-05-25','08:00:00','08:30:00','','confirmada',0,NULL,1,'2026-05-22 15:05:18','2026-05-22 15:17:08'),(10,13,4,3,NULL,'2026-05-25','08:00:00','08:30:00','','pendiente',0,NULL,1,'2026-05-22 16:12:39','2026-05-22 16:12:39'),(11,12,4,3,NULL,'2026-05-25','08:00:00','08:30:00','','pendiente',0,NULL,1,'2026-05-22 20:06:28','2026-05-22 20:06:28'),(12,5,4,2,NULL,'2026-05-30','15:49:00','16:19:00','','cancelada',0,NULL,1,'2026-05-22 20:49:23','2026-05-29 02:19:45'),(13,10,4,3,NULL,'2026-05-25','09:00:00','09:30:00','','pendiente',0,NULL,1,'2026-05-22 22:27:35','2026-05-22 22:27:35');
/*!40000 ALTER TABLE `citas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_facturas`
--

DROP TABLE IF EXISTS `detalle_facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_facturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factura_id` int(11) NOT NULL,
  `procedimiento_id` int(11) DEFAULT NULL,
  `descripcion` varchar(300) NOT NULL,
  `pieza_dental` varchar(5) DEFAULT NULL,
  `cantidad` int(11) DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  `descuento_item` decimal(10,2) DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `factura_id` (`factura_id`),
  KEY `procedimiento_id` (`procedimiento_id`),
  CONSTRAINT `detalle_facturas_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`),
  CONSTRAINT `detalle_facturas_ibfk_2` FOREIGN KEY (`procedimiento_id`) REFERENCES `procedimientos_catalogo` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_facturas`
--

LOCK TABLES `detalle_facturas` WRITE;
/*!40000 ALTER TABLE `detalle_facturas` DISABLE KEYS */;
INSERT INTO `detalle_facturas` VALUES (1,4,NULL,'Blanqueamiento dental completo',NULL,1,840000.00,0.00,840000.00),(2,5,NULL,'Profilaxis y tratamiento periodontal',NULL,1,180000.00,0.00,180000.00);
/*!40000 ALTER TABLE `detalle_facturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facturas`
--

DROP TABLE IF EXISTS `facturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_factura` varchar(20) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `odontologo_id` int(11) NOT NULL,
  `plan_id` int(11) DEFAULT NULL,
  `fecha_emision` date NOT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `iva` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_pagado` decimal(10,2) DEFAULT 0.00,
  `saldo_pendiente` decimal(10,2) DEFAULT 0.00,
  `estado` enum('pendiente','pagada','parcial','vencida','anulada') DEFAULT 'pendiente',
  `notas` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_factura` (`numero_factura`),
  KEY `paciente_id` (`paciente_id`),
  KEY `odontologo_id` (`odontologo_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `facturas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  CONSTRAINT `facturas_ibfk_2` FOREIGN KEY (`odontologo_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `facturas_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facturas`
--

LOCK TABLES `facturas` WRITE;
/*!40000 ALTER TABLE `facturas` DISABLE KEYS */;
INSERT INTO `facturas` VALUES (1,'FAC-2024-0001',1,2,1,'2024-01-20','2024-02-20',190000.00,0.00,0.00,190000.00,100000.00,90000.00,'parcial',NULL,1,'2026-05-19 21:29:47','2026-05-28 16:06:42'),(2,'FAC-2024-0002',2,2,2,'2024-02-25','2024-03-25',180000.00,0.00,0.00,180000.00,180000.00,0.00,'pagada',NULL,1,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(3,'FAC-2024-0003',4,2,NULL,'2024-03-01','2024-04-01',35000.00,0.00,0.00,35000.00,0.00,35000.00,'pendiente',NULL,1,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(4,'F20260528105155',1,4,3,'2026-05-27','2026-05-28',840000.00,0.00,0.00,840000.00,840000.00,0.00,'pagada','Listo El Pago',1,'2026-05-28 15:51:55','2026-05-29 02:42:05'),(5,'F20260528111228',10,2,2,'2026-05-28','2026-05-28',180000.00,0.00,0.00,180000.00,0.00,180000.00,'pendiente','sdsd',1,'2026-05-28 16:12:28','2026-05-28 16:12:28');
/*!40000 ALTER TABLE `facturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historias_clinicas`
--

DROP TABLE IF EXISTS `historias_clinicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historias_clinicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `paciente_id` int(11) NOT NULL,
  `odontologo_id` int(11) NOT NULL,
  `numero_historia` varchar(20) NOT NULL,
  `fecha_apertura` date NOT NULL,
  `motivo_consulta` text DEFAULT NULL,
  `enfermedad_actual` text DEFAULT NULL,
  `antecedentes_medicos` text DEFAULT NULL,
  `antecedentes_odontologicos` text DEFAULT NULL,
  `medicamentos_actuales` text DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `habito_tabaco` tinyint(1) DEFAULT 0,
  `habito_alcohol` tinyint(1) DEFAULT 0,
  `habito_bruxismo` tinyint(1) DEFAULT 0,
  `otros_habitos` text DEFAULT NULL,
  `presion_arterial` varchar(10) DEFAULT NULL,
  `frecuencia_cardiaca` int(11) DEFAULT NULL,
  `temperatura` decimal(4,1) DEFAULT NULL,
  `examen_extraoral` text DEFAULT NULL,
  `examen_intraoral` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `plan_tratamiento_inicial` text DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('activa','archivada') DEFAULT 'activa',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_historia` (`numero_historia`),
  KEY `paciente_id` (`paciente_id`),
  KEY `odontologo_id` (`odontologo_id`),
  CONSTRAINT `historias_clinicas_ibfk_1` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  CONSTRAINT `historias_clinicas_ibfk_2` FOREIGN KEY (`odontologo_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historias_clinicas`
--

LOCK TABLES `historias_clinicas` WRITE;
/*!40000 ALTER TABLE `historias_clinicas` DISABLE KEYS */;
INSERT INTO `historias_clinicas` VALUES (1,1,2,'HC-2024-0001','2024-01-15','Dolor molar superior derecho','Dolor agudo al masticar desde hace 3 d??as','Sin antecedentes relevantes',NULL,NULL,'Ninguna conocida',0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'Caries profunda pieza 16',NULL,NULL,'activa','2026-05-19 21:29:47','2026-05-19 21:29:47'),(2,2,2,'HC-2024-0002','2024-02-20','Control y limpieza dental',NULL,'Hipertensi??n controlada',NULL,NULL,'Penicilina',0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'Gingivitis leve generalizada',NULL,NULL,'activa','2026-05-19 21:29:47','2026-05-19 21:29:47'),(3,3,2,'HC-2024-0003','2024-03-10','Consulta est??tica',NULL,'Sin antecedentes',NULL,NULL,'Ninguna',0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'Decoloraci??n dental anterior',NULL,NULL,'activa','2026-05-19 21:29:47','2026-05-19 21:29:47'),(4,9,4,'HC-2026-9250','2026-05-20','Presenta dolor en en las encias de abajo','Fiebre, Dolor sobre las cordales','Asma','Ortodoncia','','',0,0,0,'','',NULL,NULL,'','','','','','activa','2026-05-21 03:40:59','2026-05-21 03:40:59'),(5,13,4,'HC-2026-3049','2026-05-21','Dolor en las encias','Presenta sintomas de fiebre','Ninguno','Limpieza Odontologica','Ninguno','Ninguna',0,1,0,'','',NULL,NULL,'','','Se hayo un tipo de sangrado frecuentemente, debido una mala ortodoncia','Tomar 2 pastas de ibuprofeno para el dolor que se le presenta una de estas al desayuno y otra a la cena','','activa','2026-05-21 21:00:37','2026-05-21 21:00:37');
/*!40000 ALTER TABLE `historias_clinicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imagenes_clinicas`
--

DROP TABLE IF EXISTS `imagenes_clinicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `imagenes_clinicas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `historia_id` int(11) NOT NULL,
  `pieza_dental` varchar(5) DEFAULT NULL,
  `tipo` enum('radiografia','foto_clinica','otro') DEFAULT 'foto_clinica',
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(500) NOT NULL,
  `tamanio_bytes` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `historia_id` (`historia_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `imagenes_clinicas_ibfk_1` FOREIGN KEY (`historia_id`) REFERENCES `historias_clinicas` (`id`),
  CONSTRAINT `imagenes_clinicas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imagenes_clinicas`
--

LOCK TABLES `imagenes_clinicas` WRITE;
/*!40000 ALTER TABLE `imagenes_clinicas` DISABLE KEYS */;
INSERT INTO `imagenes_clinicas` VALUES (1,5,NULL,'foto_clinica','radiografia.jpg','/assets/uploads/fotos/hc_6a0f72759a5eb1.95368891_radiografia.jpg',9775,'Adjunto inicial de historia cl├¡nica',1,'2026-05-21 21:00:37');
/*!40000 ALTER TABLE `imagenes_clinicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `tipo` enum('cita','pago','sistema','alerta') DEFAULT 'sistema',
  `titulo` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `url_accion` varchar(300) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
INSERT INTO `notificaciones` VALUES (1,1,'sistema','Bienvenido a DentiSoft','El sistema ha sido configurado correctamente. ??Comienza a gestionar tu cl??nica!',1,'/DentiSoft1.0/dashboard.php','2026-05-19 21:29:47'),(2,2,'cita','Citas programadas para hoy','Tienes 4 citas programadas para el d??a de hoy.',0,'/DentiSoft1.0/modules/citas/index.php','2026-05-19 21:29:47'),(3,1,'pago','Factura pendiente de cobro','La factura FAC-2024-0001 tiene un saldo pendiente de $90,000 COP.',1,'/DentiSoft1.0/modules/facturacion/ver.php?id=1','2026-05-19 21:29:47');
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `odontograma`
--

DROP TABLE IF EXISTS `odontograma`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `odontograma` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `historia_id` int(11) NOT NULL,
  `pieza_dental` varchar(5) NOT NULL,
  `estado` enum('sano','caries','obturado','extraccion_indicada','ausente','corona','protesis','implante','fractura','tratamiento_conductos','otro') DEFAULT 'sano',
  `caras_afectadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`caras_afectadas`)),
  `color_estado` varchar(7) DEFAULT '#28a745',
  `notas` text DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pieza` (`historia_id`,`pieza_dental`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `odontograma_ibfk_1` FOREIGN KEY (`historia_id`) REFERENCES `historias_clinicas` (`id`),
  CONSTRAINT `odontograma_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `odontograma`
--

LOCK TABLES `odontograma` WRITE;
/*!40000 ALTER TABLE `odontograma` DISABLE KEYS */;
INSERT INTO `odontograma` VALUES (1,5,'17','sano','[\"distal\"]','#10b981','dgdf',1,'2026-05-28 17:19:00');
/*!40000 ALTER TABLE `odontograma` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pacientes`
--

DROP TABLE IF EXISTS `pacientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pacientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_documento` varchar(20) NOT NULL,
  `tipo_documento` enum('CC','TI','CE','PAS','RC') DEFAULT 'CC',
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` enum('M','F','Otro') DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `ciudad` varchar(100) DEFAULT 'Neiva',
  `eps` varchar(100) DEFAULT NULL,
  `tipo_afiliacion` enum('contributivo','subsidiado','particular','otro') DEFAULT 'particular',
  `grupo_sanguineo` varchar(5) DEFAULT NULL,
  `estado` enum('activo','inactivo','suspendido') DEFAULT 'activo',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_documento` (`numero_documento`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pacientes`
--

LOCK TABLES `pacientes` WRITE;
/*!40000 ALTER TABLE `pacientes` DISABLE KEYS */;
INSERT INTO `pacientes` VALUES (1,'1075312456','CC','Ana Mar├¡a','Rodr├¡guez P├®rez','1990-03-15','F','3112345678','ana.rodriguez@email.com','Calle 10 #5-23 Barrio Centro','Neiva','Nueva EPS','contributivo','O+','activo','2026-05-19 21:29:47','2026-05-20 18:40:04'),(2,'1075456789','CC','Juan Carlos','L├│pez Silva','1985-07-22','M','3156789012','juan.lopez@email.com','Carrera 15 #8-45 Barrio Altico','Neiva','Sanitas','contributivo','A+','activo','2026-05-19 21:29:47','2026-05-20 18:40:04'),(3,'1075234567','CC','Laura Patricia','Mart├¡nez G├│mez','1995-11-08','F','3201234567','laura.martinez@email.com','Avenida Circunvalar Cra 1 #21-50','Neiva','Amet Salud','subsidiado','B+','activo','2026-05-19 21:29:47','2026-05-20 23:35:15'),(4,'1075567890','CC','Pedro Antonio','Hern├índez Ruiz','1978-01-30','M','3178901234','pedro.hernandez@email.com','Calle 21 #3-15 Barrio Calixto','Neiva',NULL,'particular','AB+','activo','2026-05-19 21:29:47','2026-05-21 02:19:57'),(5,'1030987654','CC','Sof├¡a Valentina','Torres Castillo','2000-05-20','F','3145678901','sofia.torres@email.com','Carrera 5 #12-30 Barrio Las Granjas','Neiva','Sura','contributivo','O-','activo','2026-05-19 21:29:47','2026-05-20 18:40:04'),(6,'36123456','CC','Roberto','D├¡az Vargas','1970-09-12','M','3167890123','roberto.diaz@email.com','Calle 5 #2-10 Barrio C??ndido','Neiva','Compensar','contributivo','A-','activo','2026-05-19 21:29:47','2026-05-20 18:40:04'),(7,'1075678901','CC','Camila Andrea','Su├írez Moreno','1998-12-03','F','3123456789','camila.suarez@email.com','Avenida 26 #7-80 Barrio Santa Isabel','Neiva',NULL,'particular','O+','activo','2026-05-19 21:29:47','2026-05-20 18:40:04'),(8,'1020345678','CC','Diego Fernando','Ram├¡rez Ortiz','1988-04-17','M','3190123456','diego.ramirez@email.com','Carrera 8 #15-42 Barrio El Jard??n','Neiva','Famisanar','contributivo','B-','activo','2026-05-19 21:29:47','2026-05-21 02:19:57'),(9,'1117511568','CC','Jhoan Steven','Zambrano Vera','2008-02-02','M','3202860609','stevenzambrano@gmail.com','Km 3 Via Florencia','Morelia','Asmet Salud','particular','A+','activo','2026-05-20 20:47:01','2026-05-20 20:47:01'),(10,'1118471589','TI','Ana Valeria','Gutierrez Rivera','2017-10-13','F','3203294059','jjulian.gutierrez08@gmail.com','B/ Sumawasy','San Jose Del Fragua','Amet Salud','particular','A+','activo','2026-05-21 18:29:36','2026-05-21 18:29:36'),(12,'1117811948','CC','Emerson','Corredor Murcia','2006-10-22','M','3229602906','murciacorredoremerson@gmail.com','B/ Malvinas','Florencia','Nueva EPS','subsidiado','O+','inactivo','2026-05-21 20:20:15','2026-05-28 14:31:40'),(13,'6805856','CC','Jaiber','Gutierrez Rojas','1997-07-17','M','3144232446','bbultosa@gmail.com','B/ Sumawasy','San Jose Del Fragua','Asmet Salud','subsidiado','A+','inactivo','2026-05-21 20:31:15','2026-05-28 17:22:48');
/*!40000 ALTER TABLE `pacientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `factura_id` int(11) NOT NULL,
  `fecha_pago` date NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` enum('efectivo','transferencia','tarjeta_credito','tarjeta_debito','cheque','otro') DEFAULT 'efectivo',
  `referencia_pago` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `factura_id` (`factura_id`),
  KEY `registrado_por` (`registrado_por`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`factura_id`) REFERENCES `facturas` (`id`),
  CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES (1,1,'2026-01-20',100000.00,'efectivo',NULL,'Abono inicial del tratamiento',1,'2026-05-19 21:29:47'),(2,2,'2026-02-25',180000.00,'transferencia','TRF-2024-0045','Pago total del tratamiento',1,'2026-05-19 21:29:47'),(3,4,'2026-05-28',840000.00,'efectivo',NULL,'Metodo seleccionado: Efectivo.',1,'2026-05-29 02:42:05');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `planes_tratamiento`
--

DROP TABLE IF EXISTS `planes_tratamiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planes_tratamiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `historia_id` int(11) NOT NULL,
  `paciente_id` int(11) NOT NULL,
  `odontologo_id` int(11) NOT NULL,
  `nombre_plan` varchar(200) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin_estimada` date DEFAULT NULL,
  `costo_total` decimal(10,2) DEFAULT 0.00,
  `estado` enum('pendiente','en_curso','completado','cancelado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `historia_id` (`historia_id`),
  KEY `paciente_id` (`paciente_id`),
  KEY `odontologo_id` (`odontologo_id`),
  CONSTRAINT `planes_tratamiento_ibfk_1` FOREIGN KEY (`historia_id`) REFERENCES `historias_clinicas` (`id`),
  CONSTRAINT `planes_tratamiento_ibfk_2` FOREIGN KEY (`paciente_id`) REFERENCES `pacientes` (`id`),
  CONSTRAINT `planes_tratamiento_ibfk_3` FOREIGN KEY (`odontologo_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `planes_tratamiento`
--

LOCK TABLES `planes_tratamiento` WRITE;
/*!40000 ALTER TABLE `planes_tratamiento` DISABLE KEYS */;
INSERT INTO `planes_tratamiento` VALUES (1,1,1,2,'Restauraci??n pieza 16','Tratamiento restaurador con resina compuesta para caries profunda','2024-01-20','2024-02-20',190000.00,'en_curso',NULL,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(2,2,2,2,'Profilaxis y tratamiento periodontal','Limpieza profunda y seguimiento de gingivitis','2024-02-25','2024-05-25',180000.00,'en_curso',NULL,'2026-05-19 21:29:47','2026-05-19 21:29:47'),(3,3,3,2,'Blanqueamiento dental completo','Blanqueamiento profesional en consultorio - 3 sesiones','2024-03-15','2024-04-15',840000.00,'pendiente',NULL,'2026-05-19 21:29:47','2026-05-19 21:29:47');
/*!40000 ALTER TABLE `planes_tratamiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `procedimientos_catalogo`
--

DROP TABLE IF EXISTS `procedimientos_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `procedimientos_catalogo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) DEFAULT NULL,
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio_base` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duracion_minutos` int(11) DEFAULT 30,
  `categoria` enum('diagnostico','preventivo','restaurador','endodoncia','cirugia','ortodoncia','protesis','periodoncia','otro') DEFAULT 'otro',
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `procedimientos_catalogo`
--

LOCK TABLES `procedimientos_catalogo` WRITE;
/*!40000 ALTER TABLE `procedimientos_catalogo` DISABLE KEYS */;
INSERT INTO `procedimientos_catalogo` VALUES (1,'890201','Consulta de primera vez odontolog??a general',NULL,35000.00,30,'diagnostico',1,'2026-05-19 21:29:47'),(2,'890202','Consulta de control o seguimiento',NULL,25000.00,20,'diagnostico',1,'2026-05-19 21:29:47'),(3,'890301','Limpieza y profilaxis dental',NULL,60000.00,45,'preventivo',1,'2026-05-19 21:29:47'),(4,'890401','Restauraci??n en resina compuesta 1 cara',NULL,80000.00,45,'restaurador',1,'2026-05-19 21:29:47'),(5,'890402','Restauraci??n en resina compuesta 2 caras',NULL,110000.00,60,'restaurador',1,'2026-05-19 21:29:47'),(6,'890403','Restauraci??n en resina compuesta 3 caras',NULL,140000.00,75,'restaurador',1,'2026-05-19 21:29:47'),(7,'890501','Extracci??n dental simple',NULL,70000.00,30,'cirugia',1,'2026-05-19 21:29:47'),(8,'890502','Extracci??n dental compleja / cordal',NULL,180000.00,60,'cirugia',1,'2026-05-19 21:29:47'),(9,'890601','Tratamiento de conductos unirradicular',NULL,350000.00,90,'endodoncia',1,'2026-05-19 21:29:47'),(10,'890602','Tratamiento de conductos birradicular',NULL,420000.00,90,'endodoncia',1,'2026-05-19 21:29:47'),(11,'890701','Corona metalcer??mica',NULL,650000.00,60,'protesis',1,'2026-05-19 21:29:47'),(12,'890801','Aplicaci??n de sellantes',NULL,40000.00,30,'preventivo',1,'2026-05-19 21:29:47'),(13,'890901','Blanqueamiento dental profesional',NULL,280000.00,60,'otro',1,'2026-05-19 21:29:47');
/*!40000 ALTER TABLE `procedimientos_catalogo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `permisos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permisos`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'administrador','Acceso total al sistema','{\"pacientes\":\"crud\",\"historias\":\"crud\",\"citas\":\"crud\",\"tratamientos\":\"crud\",\"facturacion\":\"crud\",\"reportes\":\"crud\",\"usuarios\":\"crud\"}','2026-05-19 21:29:47'),(2,'odontologo','Acceso a historias, citas, tratamientos y facturaci??n','{\"pacientes\":\"cr\",\"historias\":\"crud\",\"citas\":\"crud\",\"tratamientos\":\"crud\",\"facturacion\":\"cr\",\"reportes\":\"r\",\"usuarios\":\"\"}','2026-05-19 21:29:47'),(3,'asistente','Acceso a citas, pacientes y notificaciones','{\"pacientes\":\"cr\",\"historias\":\"r\",\"citas\":\"crud\",\"tratamientos\":\"r\",\"facturacion\":\"r\",\"reportes\":\"\",\"usuarios\":\"\"}','2026-05-19 21:29:47');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones_tratamiento`
--

DROP TABLE IF EXISTS `sesiones_tratamiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesiones_tratamiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `numero_sesion` int(11) NOT NULL,
  `procedimiento_id` int(11) DEFAULT NULL,
  `pieza_dental` varchar(5) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `observaciones_sesion` text DEFAULT NULL,
  `costo_sesion` decimal(10,2) DEFAULT 0.00,
  `fecha_programada` date DEFAULT NULL,
  `fecha_realizada` date DEFAULT NULL,
  `estado` enum('pendiente','realizada','cancelada') DEFAULT 'pendiente',
  `progreso` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `notas` text DEFAULT NULL,
  `fecha_ultimo_avance` datetime DEFAULT NULL,
  `odontologo_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `plan_id` (`plan_id`),
  KEY `procedimiento_id` (`procedimiento_id`),
  KEY `odontologo_id` (`odontologo_id`),
  CONSTRAINT `sesiones_tratamiento_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `planes_tratamiento` (`id`),
  CONSTRAINT `sesiones_tratamiento_ibfk_2` FOREIGN KEY (`procedimiento_id`) REFERENCES `procedimientos_catalogo` (`id`),
  CONSTRAINT `sesiones_tratamiento_ibfk_3` FOREIGN KEY (`odontologo_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones_tratamiento`
--

LOCK TABLES `sesiones_tratamiento` WRITE;
/*!40000 ALTER TABLE `sesiones_tratamiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones_tratamiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,'Administrador','Sistema','admin@dentisoft.com','$2y$10$0JIIJKWcGKy63jgyuBTgMu5lK52Ccj2CN4yK6S.QgEx5fdzKnSYMi','3100000000','activo','2026-06-11 09:40:21','2026-05-19 21:29:47','2026-06-11 14:40:21'),(2,2,'Carlos','Mart├¡nez L├│pez','carlos.martinez@dentisoft.com','$2y$10$gJtKMjtvI5ET9BzafeHIbOgZp.Ku3MIoJkiFLB3KB8TaETGTXj272','3151234567','activo',NULL,'2026-05-19 21:29:47','2026-05-21 02:19:57'),(3,3,'Mar├¡a','Garc├¡a Rojas','maria.garcia@dentisoft.com','$2y$10$gJtKMjtvI5ET9BzafeHIbOgZp.Ku3MIoJkiFLB3KB8TaETGTXj272','3209876543','activo',NULL,'2026-05-19 21:29:47','2026-05-21 02:19:57'),(4,2,'Diana Marley','Rivera Paredes','dianamarley22@gmail.com','$2y$10$ytucGJR5USoIZlKLU21toOMFzNMDl/a8twGajokWlFYdHGJ2Jls26','3203294059','activo','2026-06-10 16:56:44','2026-05-20 21:35:42','2026-06-10 21:56:44'),(5,3,'pruebao','asitenprueba','asistenteprueba@gmail.com','$2y$10$ZOuOWzLpE3/YIl6cG78UFexLEw61GdIAdoCzRmRr.EfCqYF1oo3fW','31256999785','activo','2026-05-20 19:55:06','2026-05-20 21:53:02','2026-05-21 00:55:06');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-11  9:50:26
