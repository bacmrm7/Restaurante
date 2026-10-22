-- MySQL dump 10.13  Distrib 9.7.2, for macos15 (arm64)
--
-- Host: localhost    Database: erp_seguridad
-- ------------------------------------------------------
-- Server version	9.7.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '';

--
-- Table structure for table `logs_auditoria`
--

DROP TABLE IF EXISTS `logs_auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs_auditoria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `accion` varchar(100) NOT NULL,
  `detalles` text,
  `ip_origen` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_auditoria_usuario` (`usuario_id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logs_auditoria`
--

LOCK TABLES `logs_auditoria` WRITE;
/*!40000 ALTER TABLE `logs_auditoria` DISABLE KEYS */;
INSERT INTO `logs_auditoria` VALUES (1,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-05 21:56:34'),(2,1,'TEST_MIGRATION','Prueba de validación de arquitectura independiente','127.0.0.1','2026-10-05 23:44:04'),(3,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-06 00:53:58'),(4,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-06 01:04:19'),(5,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 01:53:32'),(6,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 01:54:02'),(7,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 02:09:11'),(8,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 02:24:02'),(9,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 02:54:33'),(10,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:35:03'),(11,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:39:11'),(12,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:39:21'),(13,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:44:54'),(14,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:45:08'),(15,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:45:24'),(16,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:45:39'),(17,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:46:03'),(18,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:50:13'),(19,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:56:32'),(20,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 03:58:54'),(21,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 04:00:48'),(22,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 04:02:16'),(23,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 15:02:54'),(24,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 15:06:37'),(25,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-13 15:19:27'),(26,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 10:44:59'),(27,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 10:46:09'),(28,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:09:47'),(29,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:10:44'),(30,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:11:38'),(31,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:12:29'),(32,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:16:34'),(33,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 19:25:37'),(34,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-14 23:24:10'),(35,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-19 22:05:15'),(36,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-19 22:22:01'),(37,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-19 22:25:22'),(38,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-19 23:20:18'),(39,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-19 23:20:26'),(40,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 12:54:24'),(41,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 18:00:07'),(42,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 18:07:57'),(43,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 18:08:27'),(44,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 18:08:49'),(45,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 20:54:31'),(46,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:01:32'),(47,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:06:48'),(48,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:07:26'),(49,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:09:34'),(50,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:10:11'),(51,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:12:36'),(52,2,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-20 21:15:26'),(53,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-21 16:14:00'),(54,1,'Inicio de sesión','Inicio de sesión exitoso al sistema','::1','2026-10-21 16:54:39');
/*!40000 ALTER TABLE `logs_auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulo`
--

DROP TABLE IF EXISTS `modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_modulo` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_modulo` (`nombre_modulo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulo`
--

LOCK TABLES `modulo` WRITE;
/*!40000 ALTER TABLE `modulo` DISABLE KEYS */;
/*!40000 ALTER TABLE `modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos`
--

DROP TABLE IF EXISTS `permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permisos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `modulo` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos`
--

LOCK TABLES `permisos` WRITE;
/*!40000 ALTER TABLE `permisos` DISABLE KEYS */;
/*!40000 ALTER TABLE `permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_modulo_rol`
--

DROP TABLE IF EXISTS `r_modulo_rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `r_modulo_rol` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rol_id` int NOT NULL,
  `modulo_id` int NOT NULL,
  `habilitado` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `rol_id` (`rol_id`,`modulo_id`),
  KEY `modulo_id` (`modulo_id`),
  CONSTRAINT `r_modulo_rol_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `r_modulo_rol_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulo` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_modulo_rol`
--

LOCK TABLES `r_modulo_rol` WRITE;
/*!40000 ALTER TABLE `r_modulo_rol` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_modulo_rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_usuario_rol`
--

DROP TABLE IF EXISTS `r_usuario_rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `r_usuario_rol` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `rol_id` int NOT NULL,
  `fecha_asignacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`,`rol_id`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `r_usuario_rol_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `r_usuario_rol_ibfk_2` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_usuario_rol`
--

LOCK TABLES `r_usuario_rol` WRITE;
/*!40000 ALTER TABLE `r_usuario_rol` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_usuario_rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_permiso`
--

DROP TABLE IF EXISTS `rol_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rol_permiso` (
  `rol_id` int NOT NULL,
  `permiso_id` int NOT NULL,
  PRIMARY KEY (`rol_id`,`permiso_id`),
  KEY `fk_rolpermiso_permiso` (`permiso_id`),
  CONSTRAINT `fk_rolpermiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rolpermiso_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_permiso`
--

LOCK TABLES `rol_permiso` WRITE;
/*!40000 ALTER TABLE `rol_permiso` DISABLE KEYS */;
/*!40000 ALTER TABLE `rol_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador',NULL),(2,'Cajero / Almacenista',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rol_id` int NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `fk_usuarios_rol` (`rol_id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'admin','admin123',NULL,1,'activo','2026-10-05 23:21:40'),(2,'cajero1','123456',NULL,2,'activo','2026-10-13 03:31:17');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vistas`
--

DROP TABLE IF EXISTS `vistas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vistas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_vista` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ruta_acceso` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ruta_acceso` (`ruta_acceso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vistas`
--

LOCK TABLES `vistas` WRITE;
/*!40000 ALTER TABLE `vistas` DISABLE KEYS */;
/*!40000 ALTER TABLE `vistas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vistas_permiso`
--

DROP TABLE IF EXISTS `vistas_permiso`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vistas_permiso` (
  `id` int NOT NULL AUTO_INCREMENT,
  `r_modulo_rol_id` int NOT NULL,
  `vista_id` int NOT NULL,
  `permiso_id` int NOT NULL,
  `permitido` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `r_modulo_rol_id` (`r_modulo_rol_id`,`vista_id`,`permiso_id`),
  KEY `vista_id` (`vista_id`),
  KEY `permiso_id` (`permiso_id`),
  CONSTRAINT `vistas_permiso_ibfk_1` FOREIGN KEY (`r_modulo_rol_id`) REFERENCES `r_modulo_rol` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vistas_permiso_ibfk_2` FOREIGN KEY (`vista_id`) REFERENCES `vistas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vistas_permiso_ibfk_3` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vistas_permiso`
--

LOCK TABLES `vistas_permiso` WRITE;
/*!40000 ALTER TABLE `vistas_permiso` DISABLE KEYS */;
/*!40000 ALTER TABLE `vistas_permiso` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'erp_seguridad'
--
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-21 13:46:54
