-- MySQL dump 10.13  Distrib 9.7.2, for macos15 (arm64)
--
-- Host: localhost    Database: erp_inventario
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
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Bebidas','Refrescos, jugos y aguas','2026-09-12 01:37:22'),(2,'Materia Prima',NULL,'2026-09-26 03:19:29'),(3,'Acompañantes',NULL,'2026-09-26 03:19:29'),(4,'Platos Principales',NULL,'2026-09-26 03:19:29'),(5,'Empaques y Consumibles',NULL,'2026-09-26 03:19:29'),(6,'Materia Prima',NULL,'2026-09-26 03:19:35'),(7,'Acompañantes',NULL,'2026-09-26 03:19:35'),(8,'Platos Principales',NULL,'2026-09-26 03:19:35'),(9,'Empaques y Consumibles',NULL,'2026-09-26 03:19:35'),(10,'Acompañantes',NULL,'2026-09-26 03:21:43'),(11,'Bebidas',NULL,'2026-09-26 03:21:43'),(12,'Empaques y Consumibles',NULL,'2026-09-26 03:21:43'),(13,'Materia Prima',NULL,'2026-09-26 03:21:43'),(14,'Platos Principales',NULL,'2026-09-26 03:21:43'),(15,'Acompañantes',NULL,'2026-09-26 03:28:41'),(16,'Bebidas',NULL,'2026-09-26 03:28:41'),(17,'Empaques y Consumibles',NULL,'2026-09-26 03:28:41'),(18,'Materia Prima',NULL,'2026-09-26 03:28:41'),(19,'Platos Principales',NULL,'2026-09-26 03:28:41');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cierre_caja`
--

DROP TABLE IF EXISTS `cierre_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cierre_caja` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `total_ventas` decimal(10,2) DEFAULT '0.00',
  `costo_bom` decimal(10,2) DEFAULT '0.00',
  `gastos_dia` decimal(10,2) DEFAULT '0.00',
  `ganancia_neta` decimal(10,2) DEFAULT '0.00',
  `efectivo_esperado` decimal(10,2) DEFAULT '0.00',
  `efectivo_real` decimal(10,2) DEFAULT '0.00',
  `diferencia` decimal(10,2) DEFAULT '0.00',
  `usuario_id` int NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fecha` (`fecha`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cierre_caja`
--

LOCK TABLES `cierre_caja` WRITE;
/*!40000 ALTER TABLE `cierre_caja` DISABLE KEYS */;
INSERT INTO `cierre_caja` VALUES (1,'2026-10-20',12.00,7.19,29.50,-24.69,0.00,0.00,0.00,2,'2026-10-20 05:58:33');
/*!40000 ALTER TABLE `cierre_caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `proveedor_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `fecha_compra` datetime DEFAULT CURRENT_TIMESTAMP,
  `total_compra` decimal(10,2) NOT NULL DEFAULT '0.00',
  `estado` enum('Completada','Anulada') DEFAULT 'Completada',
  PRIMARY KEY (`id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `compras_ibfk_1` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compras`
--

DROP TABLE IF EXISTS `detalle_compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_compras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `compra_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `detalle_compras_ibfk_1` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_compras_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compras`
--

LOCK TABLES `detalle_compras` WRITE;
/*!40000 ALTER TABLE `detalle_compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_ventas`
--

DROP TABLE IF EXISTS `detalle_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detalle_ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` int NOT NULL,
  `producto_id` int NOT NULL,
  `cantidad` int NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_ventas`
--

LOCK TABLES `detalle_ventas` WRITE;
/*!40000 ALTER TABLE `detalle_ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gastos`
--

DROP TABLE IF EXISTS `gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gastos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `concepto` varchar(150) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `frecuencia` enum('semanal','mensual','diario','unico') DEFAULT 'semanal',
  `fecha` date NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gastos`
--

LOCK TABLES `gastos` WRITE;
/*!40000 ALTER TABLE `gastos` DISABLE KEYS */;
INSERT INTO `gastos` VALUES (2,'alquiler',50.00,'mensual','2026-10-20','2026-10-20 04:57:38'),(3,'Wifi',30.00,'mensual','2026-10-07','2026-10-20 04:57:58'),(4,'Sueldos',80.00,'semanal','2026-10-20','2026-10-20 14:50:01'),(5,'Compra de Carbón',18.00,'semanal','2026-10-20','2026-10-20 18:45:59');
/*!40000 ALTER TABLE `gastos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos`
--

DROP TABLE IF EXISTS `movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `producto_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` int NOT NULL,
  `stock_anterior` int NOT NULL,
  `stock_nuevo` int NOT NULL,
  `motivo` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `movimientos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=232 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos`
--

LOCK TABLES `movimientos` WRITE;
/*!40000 ALTER TABLE `movimientos` DISABLE KEYS */;
INSERT INTO `movimientos` VALUES (224,12,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:16:47'),(225,11,1,'entrada',100,0,100,'Reabastecimiento de inventario','2026-10-21 16:16:52'),(226,10,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:16:59'),(227,5,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:17:26'),(228,4,1,'entrada',500,0,500,'Reabastecimiento de inventario','2026-10-21 16:17:33'),(229,3,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:17:39'),(230,2,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:17:44'),(231,1,1,'entrada',50,0,50,'Reabastecimiento de inventario','2026-10-21 16:17:50');
/*!40000 ALTER TABLE `movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos_ventas`
--

DROP TABLE IF EXISTS `pagos_ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pagos_ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venta_id` int NOT NULL,
  `metodo_pago` enum('Efectivo USD','Efectivo BS','Pago Movil','Punto de Venta','Zelle') NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `referencia` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  CONSTRAINT `pagos_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos_ventas`
--

LOCK TABLES `pagos_ventas` WRITE;
/*!40000 ALTER TABLE `pagos_ventas` DISABLE KEYS */;
INSERT INTO `pagos_ventas` VALUES (9,9,'Efectivo USD',2.00,''),(11,11,'Efectivo USD',2.00,''),(12,12,'Efectivo USD',2.00,''),(13,13,'Efectivo USD',2.00,''),(15,18,'Efectivo BS',2000.00,''),(16,19,'Pago Movil',10000.00,'98765'),(19,1,'Efectivo USD',2.00,''),(21,3,'Pago Movil',13000.00,'7931'),(23,5,'Efectivo USD',48.00,''),(25,8,'Pago Movil',39.00,'98765'),(26,9,'Efectivo USD',24.00,''),(27,10,'Efectivo USD',13.00,''),(28,11,'Pago Movil',48000.00,'98765'),(29,12,'Efectivo BS',12000.00,''),(31,14,'Pago Movil',52000.00,'98765'),(32,15,'Efectivo USD',12.00,''),(33,16,'Pago Movil',26000.00,'98765'),(34,17,'Efectivo BS',1200.00,''),(35,18,'Efectivo USD',91.00,''),(36,19,'Efectivo USD',156.00,''),(37,20,'Pago Movil',13000.00,'98765'),(38,21,'Efectivo USD',12.00,''),(39,23,'Zelle',12.00,'098987978');
/*!40000 ALTER TABLE `pagos_ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `productos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria_id` int NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `precio_compra` decimal(10,2) NOT NULL DEFAULT '0.00',
  `precio_venta` decimal(10,2) NOT NULL,
  `stock_actual` int NOT NULL DEFAULT '0',
  `stock_minimo` int NOT NULL DEFAULT '5',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `es_combo` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,18,'INS-001','Pollo MARINADO','Pollo + adobe',5.15,0.00,50,5,'2026-09-26 04:25:16',0),(2,15,'INS-002','Ración de Ensalada','Porción individual de ensalada',0.23,0.00,50,5,'2026-09-26 04:26:32',0),(3,16,'BEB-001','Refresco 1.25L','Refresco Retornable',0.60,0.00,50,5,'2026-09-26 04:28:02',0),(4,15,'INS-003','Arepas (unidad)','Unidad de arepa precocida/para asar para acompanamiento de combos',0.02,0.00,500,20,'2026-09-26 04:33:57',0),(5,15,'INS-004','Rueda de Queso Cebú','Rueda/porcion de queso cebu para acompanamiento de combos',0.53,0.00,50,5,'2026-09-26 04:36:47',0),(6,19,'CMB-001','Combo Pollo Entero','1 Pollo asado, 1 Ración de ensalada, 10 Arepas y 1 Rueda de queso cebú',0.00,12.00,0,0,'2026-09-26 04:40:15',1),(7,19,'CMB-002','Combo Pollo Entero + Refresco 1.25L','1 Pollo asado, 1 Ración de ensalada, 10 Arepas, 1 Rueda de queso cebú y 1 Refresco 1.25L',0.00,13.00,0,0,'2026-09-26 04:41:48',1),(10,3,'INS-005','Kit Salsas y Aderezos','Salsas y aderezos para ensalada y pollos',0.50,0.00,50,5,'2026-10-20 04:06:23',0),(11,5,'EMP-001','Bandeja para Pollo','Bandejas de anime CT4',0.08,0.00,100,5,'2026-10-20 04:09:26',0),(12,5,'EMP-002','Bolsa Plástica','Bolsa para empacar pedidos',0.02,0.00,50,5,'2026-10-20 04:10:28',0);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `proveedores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rif_cedula` varchar(20) NOT NULL,
  `razon_social` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rif_cedula` (`rif_cedula`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'J-12345678-0','Distribuidora Avícola El Pollo Real C.A.','0414-1234567','Maracaibo, Edo. Zulia','2026-09-30 03:02:18');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recetas`
--

DROP TABLE IF EXISTS `recetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recetas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `combo_id` int NOT NULL,
  `insumo_id` int NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT '1.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `combo_id` (`combo_id`),
  KEY `insumo_id` (`insumo_id`),
  CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`combo_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recetas_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recetas`
--

LOCK TABLES `recetas` WRITE;
/*!40000 ALTER TABLE `recetas` DISABLE KEYS */;
INSERT INTO `recetas` VALUES (1,6,1,1.00,'2026-10-20 01:51:35'),(2,6,4,10.00,'2026-10-20 01:51:48'),(3,6,2,1.00,'2026-10-20 01:52:02'),(4,6,5,1.00,'2026-10-20 01:52:06'),(6,6,12,1.00,'2026-10-20 04:15:49'),(9,6,13,1.00,'2026-10-20 04:22:51'),(12,7,12,1.00,'2026-10-20 04:31:48'),(13,7,10,1.00,'2026-10-20 04:31:53'),(14,7,1,1.00,'2026-10-20 04:31:59'),(15,7,13,1.00,'2026-10-20 04:32:04'),(16,7,2,1.00,'2026-10-20 04:32:06'),(17,7,3,1.00,'2026-10-20 04:32:10'),(18,7,5,1.00,'2026-10-20 04:32:14'),(19,7,4,10.00,'2026-10-20 13:11:58'),(20,6,10,1.00,'2026-10-20 13:25:23'),(21,6,11,2.00,'2026-10-20 13:40:26'),(22,7,11,2.00,'2026-10-20 13:40:40');
/*!40000 ALTER TABLE `recetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo_factura` varchar(20) NOT NULL,
  `usuario_id` int NOT NULL,
  `fecha_venta` datetime DEFAULT CURRENT_TIMESTAMP,
  `total` decimal(10,2) NOT NULL,
  `estado` enum('Completada','Anulada') DEFAULT 'Completada',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_factura` (`codigo_factura`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'erp_inventario'
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
