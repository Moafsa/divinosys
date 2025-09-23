-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: divinosys
-- ------------------------------------------------------
-- Server version	8.0.43

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

--
-- Current Database: `divinosys`
--

-- Database already created by Docker environment variables
-- Just use the existing database
USE `divinosys`;

--
-- Table structure for table `atividade`
--

DROP TABLE IF EXISTS `atividade`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `atividade` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `atividade` varchar(255) NOT NULL,
  `ordem` int NOT NULL,
  `condicao` int NOT NULL,
  `start` datetime DEFAULT NULL,
  `color` varchar(10) DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `atividade`
--

LOCK TABLES `atividade` WRITE;
/*!40000 ALTER TABLE `atividade` DISABLE KEYS */;
/*!40000 ALTER TABLE `atividade` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `imagem` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'XIS','2025-04-17 21:22:58','uploads/categorias/6808407a6b548.jpeg'),(2,'Cachorro-Quente','2025-04-17 21:22:58','uploads/categorias/6808402e81e2d.jpg'),(3,'Bauru','2025-04-17 21:22:58','uploads/categorias/68084002d6db7.jpg'),(4,'PF e A La Minuta','2025-04-17 21:22:58','uploads/categorias/680840402bdc6.jpg'),(5,'Torrada','2025-04-17 21:22:58','uploads/categorias/6808406eb51b1.jpg'),(6,'Rod├¡zio','2025-04-17 21:22:58','uploads/categorias/6808405db8576.jpg'),(7,'Por├º├Áes','2025-04-17 21:22:58','uploads/categorias/6808404dc15af.jpg'),(8,'Bebidas','2025-04-17 21:22:58','uploads/categorias/68084014a32c3.jpg'),(9,'Bebidas Alco├│licas','2025-04-17 21:22:58','uploads/categorias/6808402551958.png'),(10,'Combo','2025-04-24 16:27:34','uploads/categorias/680a6675f3a5b.jpeg');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_financeiras`
--

DROP TABLE IF EXISTS `categorias_financeiras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_financeiras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('receita','despesa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_financeiras`
--

LOCK TABLES `categorias_financeiras` WRITE;
/*!40000 ALTER TABLE `categorias_financeiras` DISABLE KEYS */;
INSERT INTO `categorias_financeiras` VALUES (1,'Vendas','receita','Receitas provenientes de vendas','2025-05-07 21:42:06','2025-05-07 21:42:06'),(2,'Servi├ºos','receita','Receitas provenientes de servi├ºos','2025-05-07 21:42:06','2025-05-07 21:42:06'),(3,'Aluguel','despesa','Despesas com aluguel','2025-05-07 21:42:06','2025-05-07 21:42:06'),(4,'Sal├írios','despesa','Despesas com sal├írios','2025-05-07 21:42:06','2025-05-07 21:42:06'),(5,'Fornecedores','despesa','Despesas com fornecedores','2025-05-07 21:42:06','2025-05-07 21:42:06'),(6,'Impostos','despesa','Despesas com impostos','2025-05-07 21:42:06','2025-05-07 21:42:06'),(7,'Manuten├º├úo','despesa','Despesas com manuten├º├úo','2025-05-07 21:42:06','2025-05-07 21:42:06'),(8,'Outros','receita','Outras receitas','2025-05-07 21:42:06','2025-05-07 21:42:06'),(9,'Outros','despesa','Outras despesas','2025-05-07 21:42:06','2025-05-07 21:42:06');
/*!40000 ALTER TABLE `categorias_financeiras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `endereco` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin NOT NULL,
  `bairro` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `cidade` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `estado` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `complemento` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `cep` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `ponto_referencia` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `tel1` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `tel2` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `cpf_cnpj` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `rg` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `condominio` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `bloco` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `apartamento` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `local_entrega` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  `observacoes` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_bin DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contas_financeiras`
--

DROP TABLE IF EXISTS `contas_financeiras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contas_financeiras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('conta_corrente','poupanca','carteira','outros') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `saldo_inicial` decimal(10,2) NOT NULL DEFAULT '0.00',
  `saldo_atual` decimal(10,2) NOT NULL DEFAULT '0.00',
  `banco` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agencia` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conta` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contas_financeiras`
--

LOCK TABLES `contas_financeiras` WRITE;
/*!40000 ALTER TABLE `contas_financeiras` DISABLE KEYS */;
INSERT INTO `contas_financeiras` VALUES (1,'Caixa','carteira',0.00,599.97,NULL,NULL,NULL,'2025-05-12 19:45:06','2025-05-13 14:43:05');
/*!40000 ALTER TABLE `contas_financeiras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cor`
--

DROP TABLE IF EXISTS `cor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cor` (
  `id` int NOT NULL,
  `cor` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cor`
--

LOCK TABLES `cor` WRITE;
/*!40000 ALTER TABLE `cor` DISABLE KEYS */;
INSERT INTO `cor` VALUES (1,'info'),(1,'info');
/*!40000 ALTER TABLE `cor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `despesas`
--

DROP TABLE IF EXISTS `despesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `despesas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `valor` varchar(255) NOT NULL,
  `despesa` varchar(255) NOT NULL,
  `data` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `despesas`
--

LOCK TABLES `despesas` WRITE;
/*!40000 ALTER TABLE `despesas` DISABLE KEYS */;
/*!40000 ALTER TABLE `despesas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entregadores`
--

DROP TABLE IF EXISTS `entregadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `entregadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `telefone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Ativo','Inativo','Em Entrega') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Ativo',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entregadores`
--

LOCK TABLES `entregadores` WRITE;
/*!40000 ALTER TABLE `entregadores` DISABLE KEYS */;
/*!40000 ALTER TABLE `entregadores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estoque`
--

DROP TABLE IF EXISTS `estoque`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `estoque` (
  `id` int NOT NULL AUTO_INCREMENT,
  `produto_id` int NOT NULL,
  `estoque_atual` decimal(10,2) DEFAULT '0.00',
  `estoque_minimo` decimal(10,2) DEFAULT '0.00',
  `preco_custo` decimal(10,2) DEFAULT NULL,
  `marca` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fornecedor` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_compra` date DEFAULT NULL,
  `data_validade` date DEFAULT NULL,
  `unidade` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_produto_id` (`produto_id`),
  KEY `idx_estoque_atual` (`estoque_atual`),
  KEY `idx_data_validade` (`data_validade`),
  CONSTRAINT `estoque_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estoque`
--

LOCK TABLES `estoque` WRITE;
/*!40000 ALTER TABLE `estoque` DISABLE KEYS */;
INSERT INTO `estoque` VALUES (1,79,1.00,5.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-04-24 18:15:51','2025-04-24 18:28:28'),(2,80,0.00,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-04-26 17:09:40','2025-04-26 17:09:40'),(6,59,0.00,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-04-27 18:02:09','2025-04-27 18:02:09'),(7,55,0.00,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-04-27 18:43:16','2025-04-27 18:43:16'),(8,18,0.00,0.00,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2025-05-03 00:42:28','2025-05-03 00:42:28');
/*!40000 ALTER TABLE `estoque` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `imagens_movimentacoes`
--

DROP TABLE IF EXISTS `imagens_movimentacoes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `imagens_movimentacoes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `movimentacao_id` int NOT NULL,
  `caminho` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `movimentacao_id` (`movimentacao_id`),
  CONSTRAINT `imagens_movimentacoes_ibfk_1` FOREIGN KEY (`movimentacao_id`) REFERENCES `movimentacoes_financeiras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `imagens_movimentacoes`
--

LOCK TABLES `imagens_movimentacoes` WRITE;
/*!40000 ALTER TABLE `imagens_movimentacoes` DISABLE KEYS */;
/*!40000 ALTER TABLE `imagens_movimentacoes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ingredientes`
--

DROP TABLE IF EXISTS `ingredientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ingredientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tipo` enum('pao','proteina','queijo','salada','molho','complemento') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `preco_adicional` decimal(10,2) DEFAULT '0.00',
  `disponivel` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ingredientes`
--

LOCK TABLES `ingredientes` WRITE;
/*!40000 ALTER TABLE `ingredientes` DISABLE KEYS */;
INSERT INTO `ingredientes` VALUES (1,'P├úo de Xis','pao',0.00,1,'2025-04-17 21:22:58'),(2,'P├úo de Hot Dog','pao',0.00,1,'2025-04-17 21:22:58'),(3,'Hamb├║rguer','proteina',0.00,1,'2025-04-17 21:22:58'),(4,'Cora├º├úo de Frango','proteina',0.00,1,'2025-04-17 21:22:58'),(5,'Fil├®','proteina',0.00,1,'2025-04-17 21:22:58'),(6,'Frango','proteina',0.00,1,'2025-04-17 21:22:58'),(7,'Calabresa','proteina',0.00,1,'2025-04-17 21:22:58'),(8,'Bacon','proteina',0.00,1,'2025-04-17 21:22:58'),(9,'Salsicha','proteina',0.00,1,'2025-04-17 21:22:58'),(10,'Salsicha Vegetariana','proteina',0.00,1,'2025-04-17 21:22:58'),(11,'Patinho','proteina',0.00,1,'2025-04-17 21:22:58'),(12,'Alcatra','proteina',0.00,1,'2025-04-17 21:22:58'),(13,'Cox├úo Mole','proteina',0.00,1,'2025-04-17 21:22:58'),(14,'Queijo','queijo',0.00,1,'2025-04-17 21:22:58'),(15,'Queijo Ralado','queijo',0.00,1,'2025-04-17 21:22:58'),(16,'Queijo Cheddar','queijo',0.00,1,'2025-04-17 21:22:58'),(17,'Alface','salada',0.00,1,'2025-04-17 21:22:58'),(18,'Tomate','salada',0.00,1,'2025-04-17 21:22:58'),(19,'Cebola','salada',0.00,1,'2025-04-17 21:22:58'),(20,'R├║cula','salada',0.00,1,'2025-04-17 21:22:58'),(21,'Tomate Seco','salada',0.00,1,'2025-04-17 21:22:58'),(22,'Palmito','salada',0.00,1,'2025-04-17 21:22:58'),(23,'Pepino','salada',0.00,1,'2025-04-17 21:22:58'),(24,'Salada Mista','salada',0.00,1,'2025-04-17 21:22:58'),(25,'Maionese','molho',0.00,1,'2025-04-17 21:22:58'),(26,'Molho','molho',0.00,1,'2025-04-17 21:22:58'),(27,'Ovo','complemento',0.00,1,'2025-04-17 21:22:58'),(28,'Presunto','complemento',0.00,1,'2025-04-17 21:22:58'),(29,'Milho','complemento',0.00,1,'2025-04-17 21:22:58'),(30,'Ervilha','complemento',0.00,1,'2025-04-17 21:22:58'),(31,'Batata Palha','complemento',0.00,1,'2025-04-17 21:22:58'),(32,'Batata Frita','complemento',0.00,1,'2025-04-17 21:22:58'),(33,'Arroz','complemento',0.00,1,'2025-04-17 21:22:58'),(34,'Feij├úo','complemento',0.00,1,'2025-04-17 21:22:58'),(35,'Azeitona','complemento',0.00,1,'2025-04-17 21:22:58'),(36,'Ovo de Codorna','complemento',0.00,1,'2025-04-17 21:22:58'),(37,'Polenta','complemento',0.00,1,'2025-04-17 21:22:58');
/*!40000 ALTER TABLE `ingredientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_pedidos`
--

DROP TABLE IF EXISTS `log_pedidos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_pedidos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idpedido` int NOT NULL,
  `status_anterior` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `novo_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `usuario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data_alteracao` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idpedido` (`idpedido`),
  CONSTRAINT `log_pedidos_ibfk_1` FOREIGN KEY (`idpedido`) REFERENCES `pedido` (`idpedido`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_pedidos`
--

LOCK TABLES `log_pedidos` WRITE;
/*!40000 ALTER TABLE `log_pedidos` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_pedidos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mesas`
--

DROP TABLE IF EXISTS `mesas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mesas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_mesa` varchar(255) NOT NULL,
  `nome` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=511 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mesas`
--

LOCK TABLES `mesas` WRITE;
/*!40000 ALTER TABLE `mesas` DISABLE KEYS */;
INSERT INTO `mesas` VALUES (471,'1','','1'),(472,'2','','1'),(473,'3','','1'),(474,'4','','1'),(475,'5','','1'),(491,'6','','1'),(492,'7','','1'),(493,'8','','1'),(494,'9','','1'),(495,'10','','1'),(496,'11','','1'),(497,'12','','1'),(498,'13','','1'),(499,'14','','1'),(500,'15','','1'),(501,'16','','1'),(502,'17','','1'),(503,'18','','1'),(504,'19','','1'),(505,'20','','1'),(506,'21',NULL,'1'),(507,'22',NULL,'1'),(508,'23',NULL,'1'),(509,'24',NULL,'1'),(510,'25',NULL,'1');
/*!40000 ALTER TABLE `mesas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimentacoes_financeiras`
--

DROP TABLE IF EXISTS `movimentacoes_financeiras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `movimentacoes_financeiras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int DEFAULT NULL,
  `tipo` enum('receita','despesa') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `categoria_id` int NOT NULL,
  `conta_id` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_movimentacao` date NOT NULL,
  `data_vencimento` date DEFAULT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` enum('pendente','pago','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `forma_pagamento` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comprovante` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `conta_id` (`conta_id`),
  KEY `fk_movimentacao_pedido` (`pedido_id`),
  CONSTRAINT `fk_movimentacao_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_financeiras` (`id`),
  CONSTRAINT `fk_movimentacao_conta` FOREIGN KEY (`conta_id`) REFERENCES `contas_financeiras` (`id`),
  CONSTRAINT `fk_movimentacao_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedido` (`idpedido`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimentacoes_financeiras`
--

LOCK TABLES `movimentacoes_financeiras` WRITE;
/*!40000 ALTER TABLE `movimentacoes_financeiras` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimentacoes_financeiras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parcelas_financeiras`
--

DROP TABLE IF EXISTS `parcelas_financeiras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parcelas_financeiras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `movimentacao_id` int NOT NULL,
  `numero_parcela` int NOT NULL,
  `total_parcelas` int NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_vencimento` date NOT NULL,
  `status` enum('pendente','pago','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendente',
  `data_pagamento` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `movimentacao_id` (`movimentacao_id`),
  CONSTRAINT `fk_parcela_movimentacao` FOREIGN KEY (`movimentacao_id`) REFERENCES `movimentacoes_financeiras` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parcelas_financeiras`
--

LOCK TABLES `parcelas_financeiras` WRITE;
/*!40000 ALTER TABLE `parcelas_financeiras` DISABLE KEYS */;
/*!40000 ALTER TABLE `parcelas_financeiras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido`
--

DROP TABLE IF EXISTS `pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido` (
  `idpedido` int NOT NULL AUTO_INCREMENT,
  `idmesa` int DEFAULT NULL,
  `cliente` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery` tinyint(1) DEFAULT '0',
  `endereco_entrega` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ponto_referencia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `telefone_cliente` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `data` date NOT NULL,
  `hora_pedido` time NOT NULL,
  `hora_saida_entrega` time DEFAULT NULL,
  `hora_entrega` time DEFAULT NULL,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Pendente',
  `valor_total` decimal(10,2) DEFAULT '0.00',
  `taxa_entrega` decimal(10,2) DEFAULT '0.00',
  `forma_pagamento` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `troco_para` decimal(10,2) DEFAULT NULL,
  `entregador_id` int DEFAULT NULL,
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `usuario_id` int DEFAULT NULL,
  `tipo` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'mesa',
  `cliente_id` int DEFAULT NULL,
  PRIMARY KEY (`idpedido`),
  KEY `idx_idmesa` (`idmesa`),
  KEY `idx_status` (`status`),
  KEY `fk_pedido_cliente` (`cliente_id`),
  KEY `fk_pedido_entregador` (`entregador_id`),
  CONSTRAINT `fk_pedido_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pedido_entregador` FOREIGN KEY (`entregador_id`) REFERENCES `entregadores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido`
--

LOCK TABLES `pedido` WRITE;
/*!40000 ALTER TABLE `pedido` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_item_ingredientes`
--

DROP TABLE IF EXISTS `pedido_item_ingredientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_item_ingredientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_item_id` int NOT NULL,
  `ingrediente_id` int NOT NULL,
  `incluido` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `pedido_item_id` (`pedido_item_id`),
  CONSTRAINT `fk_pedido_item_ingredientes_pedido_itens` FOREIGN KEY (`pedido_item_id`) REFERENCES `pedido_itens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_item_ingredientes`
--

LOCK TABLES `pedido_item_ingredientes` WRITE;
/*!40000 ALTER TABLE `pedido_item_ingredientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedido_item_ingredientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pedido_itens`
--

DROP TABLE IF EXISTS `pedido_itens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pedido_itens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pedido_id` int NOT NULL,
  `produto_id` int NOT NULL,
  `quantidade` int NOT NULL,
  `valor_unitario` decimal(10,2) NOT NULL,
  `valor_total` decimal(10,2) NOT NULL,
  `observacao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ingredientes_sem` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ingredientes_com` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tamanho` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`id`),
  KEY `pedido_id` (`pedido_id`),
  KEY `idx_produto` (`produto_id`),
  CONSTRAINT `fk_pedido_itens_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedido` (`idpedido`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pedido_itens`
--

LOCK TABLES `pedido_itens` WRITE;
/*!40000 ALTER TABLE `pedido_itens` DISABLE KEYS */;
/*!40000 ALTER TABLE `pedido_itens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfil_estabelecimento`
--

DROP TABLE IF EXISTS `perfil_estabelecimento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `perfil_estabelecimento` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nome_estabelecimento` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnpj` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `endereco` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefone` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mensagem_header` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `criado_em` datetime DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfil_estabelecimento`
--

LOCK TABLES `perfil_estabelecimento` WRITE;
/*!40000 ALTER TABLE `perfil_estabelecimento` DISABLE KEYS */;
/*!40000 ALTER TABLE `perfil_estabelecimento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produto_ingredientes`
--

DROP TABLE IF EXISTS `produto_ingredientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produto_ingredientes` (
  `produto_id` int NOT NULL,
  `ingrediente_id` int NOT NULL,
  `padrao` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`produto_id`,`ingrediente_id`),
  KEY `ingrediente_id` (`ingrediente_id`),
  CONSTRAINT `produto_ingredientes_ibfk_1` FOREIGN KEY (`produto_id`) REFERENCES `produtos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `produto_ingredientes_ibfk_2` FOREIGN KEY (`ingrediente_id`) REFERENCES `ingredientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produto_ingredientes`
--

LOCK TABLES `produto_ingredientes` WRITE;
/*!40000 ALTER TABLE `produto_ingredientes` DISABLE KEYS */;
INSERT INTO `produto_ingredientes` VALUES (12,1,1,'2025-04-17 21:27:15'),(12,3,1,'2025-04-17 21:27:15'),(12,14,1,'2025-04-17 21:27:15'),(12,17,1,'2025-04-17 21:27:15'),(12,18,1,'2025-04-17 21:27:15'),(12,25,1,'2025-04-17 21:27:15'),(12,27,1,'2025-04-17 21:27:15'),(12,28,1,'2025-04-17 21:27:15'),(12,29,1,'2025-04-17 21:27:15'),(12,30,1,'2025-04-17 21:27:15'),(13,1,1,'2025-04-17 21:27:15'),(13,4,1,'2025-04-17 21:27:15'),(13,14,1,'2025-04-17 21:27:15'),(13,17,1,'2025-04-17 21:27:15'),(13,18,1,'2025-04-17 21:27:15'),(13,25,1,'2025-04-17 21:27:15'),(13,27,1,'2025-04-17 21:27:15'),(13,28,1,'2025-04-17 21:27:15'),(13,29,1,'2025-04-17 21:27:15'),(13,30,1,'2025-04-17 21:27:15'),(14,1,1,'2025-04-17 21:27:15'),(14,3,1,'2025-04-17 21:27:15'),(14,14,1,'2025-04-17 21:27:15'),(14,17,1,'2025-04-17 21:27:15'),(14,18,1,'2025-04-17 21:27:15'),(14,25,1,'2025-04-17 21:27:15'),(14,27,1,'2025-04-17 21:27:15'),(14,28,1,'2025-04-17 21:27:15'),(14,29,1,'2025-04-17 21:27:15'),(14,30,1,'2025-04-17 21:27:15'),(15,1,1,'2025-04-17 21:27:15'),(15,3,1,'2025-04-17 21:27:15'),(15,7,1,'2025-04-17 21:27:15'),(15,14,1,'2025-04-17 21:27:15'),(15,17,1,'2025-04-17 21:27:15'),(15,18,1,'2025-04-17 21:27:15'),(15,25,1,'2025-04-17 21:27:15'),(15,27,1,'2025-04-17 21:27:15'),(15,28,1,'2025-04-17 21:27:15'),(15,29,1,'2025-04-17 21:27:15'),(15,30,1,'2025-04-17 21:27:15'),(16,1,1,'2025-04-17 21:27:15'),(16,3,1,'2025-04-17 21:27:15'),(16,8,1,'2025-04-17 21:27:15'),(16,14,1,'2025-04-17 21:27:15'),(16,17,1,'2025-04-17 21:27:15'),(16,18,1,'2025-04-17 21:27:15'),(16,25,1,'2025-04-17 21:27:15'),(16,27,1,'2025-04-17 21:27:15'),(16,28,1,'2025-04-17 21:27:15'),(16,29,1,'2025-04-17 21:27:15'),(16,30,1,'2025-04-17 21:27:15'),(17,1,1,'2025-04-17 21:27:15'),(17,14,1,'2025-04-17 21:27:15'),(17,17,1,'2025-04-17 21:27:15'),(17,18,1,'2025-04-17 21:27:15'),(17,22,1,'2025-04-17 21:27:15'),(17,23,1,'2025-04-17 21:27:15'),(17,25,1,'2025-04-17 21:27:15'),(17,29,1,'2025-04-17 21:27:15'),(17,30,1,'2025-04-17 21:27:15'),(18,1,1,'2025-04-17 21:27:15'),(18,5,1,'2025-04-17 21:27:15'),(18,14,1,'2025-04-17 21:27:15'),(18,17,1,'2025-04-17 21:27:15'),(18,18,1,'2025-04-17 21:27:15'),(18,25,1,'2025-04-17 21:27:15'),(18,27,1,'2025-04-17 21:27:15'),(18,28,1,'2025-04-17 21:27:15'),(18,29,1,'2025-04-17 21:27:15'),(18,30,1,'2025-04-17 21:27:15'),(19,1,1,'2025-04-17 21:27:15'),(19,3,1,'2025-04-17 21:27:15'),(19,14,1,'2025-04-17 21:27:15'),(19,17,1,'2025-04-17 21:27:15'),(19,18,1,'2025-04-17 21:27:15'),(19,19,1,'2025-04-17 21:27:15'),(19,25,1,'2025-04-17 21:27:15'),(19,27,1,'2025-04-17 21:27:15'),(19,28,1,'2025-04-17 21:27:15'),(19,29,1,'2025-04-17 21:27:15'),(19,30,1,'2025-04-17 21:27:15'),(20,1,1,'2025-04-17 21:27:15'),(20,6,1,'2025-04-17 21:27:15'),(20,14,1,'2025-04-17 21:27:15'),(20,17,1,'2025-04-17 21:27:15'),(20,18,1,'2025-04-17 21:27:15'),(20,25,1,'2025-04-17 21:27:15'),(20,27,1,'2025-04-17 21:27:15'),(20,28,1,'2025-04-17 21:27:15'),(20,29,1,'2025-04-17 21:27:15'),(20,30,1,'2025-04-17 21:27:15'),(21,1,1,'2025-04-17 21:27:15'),(21,5,1,'2025-04-17 21:27:15'),(21,14,1,'2025-04-17 21:27:15'),(21,20,1,'2025-04-17 21:27:15'),(21,21,1,'2025-04-17 21:27:15'),(21,25,1,'2025-04-17 21:27:15'),(21,27,1,'2025-04-17 21:27:15'),(21,28,1,'2025-04-17 21:27:15'),(21,29,1,'2025-04-17 21:27:15'),(21,30,1,'2025-04-17 21:27:15'),(22,1,1,'2025-04-17 21:27:15'),(22,3,1,'2025-04-17 21:27:15'),(22,4,1,'2025-04-17 21:27:15'),(22,6,1,'2025-04-17 21:27:15'),(22,7,1,'2025-04-17 21:27:15'),(22,8,1,'2025-04-17 21:27:15'),(22,14,1,'2025-04-17 21:27:15'),(22,17,1,'2025-04-17 21:27:15'),(22,18,1,'2025-04-17 21:27:15'),(22,19,1,'2025-04-17 21:27:15'),(22,25,1,'2025-04-17 21:27:15'),(22,27,1,'2025-04-17 21:27:15'),(22,28,1,'2025-04-17 21:27:15'),(22,29,1,'2025-04-17 21:27:15'),(22,30,1,'2025-04-17 21:27:15'),(23,2,1,'2025-04-17 21:27:15'),(23,9,1,'2025-04-17 21:27:15'),(23,15,1,'2025-04-17 21:27:15'),(23,25,1,'2025-04-17 21:27:15'),(23,26,1,'2025-04-17 21:27:15'),(23,29,1,'2025-04-17 21:27:15'),(23,30,1,'2025-04-17 21:27:15'),(23,31,1,'2025-04-17 21:27:15'),(24,2,1,'2025-04-17 21:27:15'),(24,9,1,'2025-04-17 21:27:15'),(24,15,1,'2025-04-17 21:27:15'),(24,25,1,'2025-04-17 21:27:15'),(24,26,1,'2025-04-17 21:27:15'),(24,29,1,'2025-04-17 21:27:15'),(24,30,1,'2025-04-17 21:27:15'),(24,31,1,'2025-04-17 21:27:15'),(25,2,1,'2025-04-17 21:27:15'),(25,10,1,'2025-04-17 21:27:15'),(25,15,1,'2025-04-17 21:27:15'),(25,25,1,'2025-04-17 21:27:15'),(25,26,1,'2025-04-17 21:27:15'),(25,29,1,'2025-04-17 21:27:15'),(25,30,1,'2025-04-17 21:27:15'),(25,31,1,'2025-04-17 21:27:15'),(26,5,1,'2025-04-17 21:27:16'),(26,14,1,'2025-04-17 21:27:16'),(26,24,1,'2025-04-17 21:27:16'),(26,26,1,'2025-04-17 21:27:16'),(26,28,1,'2025-04-17 21:27:16'),(26,32,1,'2025-04-17 21:27:16'),(26,33,1,'2025-04-17 21:27:16'),(27,5,1,'2025-04-17 21:27:16'),(27,14,1,'2025-04-17 21:27:16'),(27,24,1,'2025-04-17 21:27:16'),(27,26,1,'2025-04-17 21:27:16'),(27,28,1,'2025-04-17 21:27:16'),(27,32,1,'2025-04-17 21:27:16'),(27,33,1,'2025-04-17 21:27:16'),(28,5,1,'2025-04-17 21:27:16'),(28,14,1,'2025-04-17 21:27:16'),(28,24,1,'2025-04-17 21:27:16'),(28,26,1,'2025-04-17 21:27:16'),(28,28,1,'2025-04-17 21:27:16'),(28,32,1,'2025-04-17 21:27:16'),(28,33,1,'2025-04-17 21:27:16'),(29,12,1,'2025-04-17 21:27:16'),(29,14,1,'2025-04-17 21:27:16'),(29,24,1,'2025-04-17 21:27:16'),(29,26,1,'2025-04-17 21:27:16'),(29,28,1,'2025-04-17 21:27:16'),(29,32,1,'2025-04-17 21:27:16'),(29,33,1,'2025-04-17 21:27:16'),(30,12,1,'2025-04-17 21:27:16'),(30,14,1,'2025-04-17 21:27:16'),(30,24,1,'2025-04-17 21:27:16'),(30,26,1,'2025-04-17 21:27:16'),(30,28,1,'2025-04-17 21:27:16'),(30,32,1,'2025-04-17 21:27:16'),(30,33,1,'2025-04-17 21:27:16'),(31,12,1,'2025-04-17 21:27:16'),(31,14,1,'2025-04-17 21:27:16'),(31,24,1,'2025-04-17 21:27:16'),(31,26,1,'2025-04-17 21:27:16'),(31,28,1,'2025-04-17 21:27:16'),(31,32,1,'2025-04-17 21:27:16'),(31,33,1,'2025-04-17 21:27:16'),(32,11,1,'2025-04-17 21:27:16'),(32,24,1,'2025-04-17 21:27:16'),(32,27,1,'2025-04-17 21:27:16'),(32,32,1,'2025-04-17 21:27:16'),(32,33,1,'2025-04-17 21:27:16'),(32,34,1,'2025-04-17 21:27:16'),(33,5,1,'2025-04-17 21:27:16'),(33,24,1,'2025-04-17 21:27:16'),(33,27,1,'2025-04-17 21:27:16'),(33,32,1,'2025-04-17 21:27:16'),(33,33,1,'2025-04-17 21:27:16'),(33,34,1,'2025-04-17 21:27:16'),(34,13,1,'2025-04-17 21:27:16'),(34,24,1,'2025-04-17 21:27:16'),(34,27,1,'2025-04-17 21:27:16'),(34,32,1,'2025-04-17 21:27:16'),(34,33,1,'2025-04-17 21:27:16'),(34,34,1,'2025-04-17 21:27:16'),(35,12,1,'2025-04-17 21:27:16'),(35,24,1,'2025-04-17 21:27:16'),(35,27,1,'2025-04-17 21:27:16'),(35,32,1,'2025-04-17 21:27:16'),(35,33,1,'2025-04-17 21:27:16'),(35,34,1,'2025-04-17 21:27:16'),(36,5,1,'2025-04-17 21:27:16'),(36,24,1,'2025-04-17 21:27:16'),(36,31,1,'2025-04-17 21:27:16'),(36,32,1,'2025-04-17 21:27:16'),(36,33,1,'2025-04-17 21:27:16'),(37,1,1,'2025-04-17 21:27:16'),(37,14,1,'2025-04-17 21:27:16'),(37,17,1,'2025-04-17 21:27:16'),(37,18,1,'2025-04-17 21:27:16'),(37,25,1,'2025-04-17 21:27:16'),(37,27,1,'2025-04-17 21:27:16'),(37,28,1,'2025-04-17 21:27:16'),(38,1,1,'2025-04-17 21:27:16'),(38,8,1,'2025-04-17 21:27:16'),(38,14,1,'2025-04-17 21:27:16'),(38,17,1,'2025-04-17 21:27:16'),(38,18,1,'2025-04-17 21:27:16'),(38,25,1,'2025-04-17 21:27:16'),(38,28,1,'2025-04-17 21:27:16'),(39,5,1,'2025-04-17 21:27:16'),(39,6,1,'2025-04-17 21:27:16'),(39,24,1,'2025-04-17 21:27:16'),(39,32,1,'2025-04-17 21:27:16'),(39,33,1,'2025-04-17 21:27:16'),(40,5,1,'2025-04-17 21:27:16'),(40,14,1,'2025-04-17 21:27:16'),(40,22,1,'2025-04-17 21:27:16'),(40,23,1,'2025-04-17 21:27:16'),(40,35,1,'2025-04-17 21:27:16'),(40,36,1,'2025-04-17 21:27:16'),(41,5,1,'2025-04-17 21:27:16'),(41,14,1,'2025-04-17 21:27:16'),(41,22,1,'2025-04-17 21:27:16'),(41,23,1,'2025-04-17 21:27:16'),(41,35,1,'2025-04-17 21:27:16'),(41,36,1,'2025-04-17 21:27:16'),(42,5,1,'2025-04-17 21:27:16'),(42,14,1,'2025-04-17 21:27:16'),(42,22,1,'2025-04-17 21:27:16'),(42,23,1,'2025-04-17 21:27:16'),(42,35,1,'2025-04-17 21:27:16'),(42,36,1,'2025-04-17 21:27:16'),(44,8,1,'2025-04-17 21:27:16'),(44,16,1,'2025-04-17 21:27:16'),(44,32,1,'2025-04-17 21:27:16'),(46,8,1,'2025-04-17 21:27:16'),(46,16,1,'2025-04-17 21:27:16'),(46,32,1,'2025-04-17 21:27:16');
/*!40000 ALTER TABLE `produto_ingredientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produtos`
--

DROP TABLE IF EXISTS `produtos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produtos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `codigo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `categoria_id` int NOT NULL,
  `nome` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `descricao` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `preco_normal` decimal(10,2) NOT NULL,
  `preco_mini` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `imagem` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `produtos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produtos`
--

LOCK TABLES `produtos` WRITE;
/*!40000 ALTER TABLE `produtos` DISABLE KEYS */;
INSERT INTO `produtos` VALUES (12,'1001',1,'XIS DA CASA','P├úo, hamb├║rguer, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',29.00,26.00,'2025-04-17 21:26:18',NULL),(13,'1002',1,'XIS CORA├ç├âO','P├úo, cora├º├úo de frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',34.00,29.00,'2025-04-17 21:26:18',NULL),(14,'1003',1,'XIS DUPLO','P├úo, 2 hamb├║rgueres, 2 ovos, 2 presuntos, queijos, milho, ervilha, alface, tomate, maionese',35.00,30.00,'2025-04-17 21:26:18',NULL),(15,'1004',1,'XIS CALABRESA','P├úo, hamb├║rguer, calabresa, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',30.00,26.00,'2025-04-17 21:26:18',NULL),(16,'1005',1,'XIS BACON','P├úo, hamb├║rguer, bacon, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',34.00,30.00,'2025-04-17 21:26:18',NULL),(17,'1006',1,'XIS VEGETARIANO','P├úo, alface, tomate, queijo, palmito, pepino, milho, ervilha, maionese',28.00,25.00,'2025-04-17 21:26:18',NULL),(18,'1007',1,'FIL├ë','P├úo, fil├®, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',40.00,35.00,'2025-04-17 21:26:18',NULL),(19,'1008',1,'XIS CEBOLA','P├úo, hamb├║rguer, cebola, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',31.00,29.00,'2025-04-17 21:26:18',NULL),(20,'1009',1,'XIS FRANGO','P├úo, frango, ovo, presunto, queijo, milho, ervilha, alface, tomate, maionese',33.00,28.00,'2025-04-17 21:26:18',NULL),(21,'1010',1,'XIS TOMATE SECO COM R├ÜCULA','P├úo, fil├®, r├║cula, tomate seco, ovo, presunto, queijo, milho, ervilha, maionese',43.00,37.00,'2025-04-17 21:26:18',NULL),(22,'1011',1,'XIS ENTREVERO','P├úo, calabresa, cora├º├úo, carne, frango, bacon, cebola, ovo, queijo, presunto, alface, tomate, milho, ervilha, maionese',40.00,35.00,'2025-04-17 21:26:18',NULL),(23,'2001',2,'CACHORRO-QUENTE SIMPLES','P├úo, 1 salsicha, molho, milho, ervilha, queijo ralado, maionese e batata palha',21.00,NULL,'2025-04-17 21:26:18',NULL),(24,'2002',2,'CACHORRO-QUENTE DUPLO','P├úo, 2 salsichas, molho, milho, ervilha, queijo ralado, maionese e batata palha',23.00,NULL,'2025-04-17 21:26:18',NULL),(25,'2003',2,'CACHORRO-QUENTE VEGETARIANO','P├úo, 1 salsicha vegetariana, molho, milho, ervilha, queijo ralado, maionese e batata palha',25.00,NULL,'2025-04-17 21:26:18',NULL),(26,'3001',3,'1/4 BAURU FIL├ë (1 PESSOA)','Bife de fil├® com molho, presunto, queijo, salada mista, batata frita e arroz',65.00,NULL,'2025-04-17 21:26:18',NULL),(27,'3002',3,'1/2 BAURU FIL├ë (2 PESSOAS)','Bife de fil├® com molho, presunto, queijo, salada mista, batata frita e arroz',100.00,NULL,'2025-04-17 21:26:18',NULL),(28,'3003',3,'BAURU FIL├ë (4 PESSOAS)','Bife de fil├® com molho, presunto, queijo, salada mista, batata frita e arroz',190.00,NULL,'2025-04-17 21:26:18',NULL),(29,'3004',3,'1/4 BAURU ALCATRA (1 PESSOA)','Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',55.00,NULL,'2025-04-17 21:26:18',NULL),(30,'3005',3,'1/2 BAURU ALCATRA (2 PESSOAS)','Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',90.00,NULL,'2025-04-17 21:26:18',NULL),(31,'3006',3,'BAURU ALCATRA (4 PESSOAS)','Bife de alcatra com molho, presunto, queijo, salada mista, batata frita e arroz',150.00,NULL,'2025-04-17 21:26:18',NULL),(32,'4001',4,'PRATO FEITO DA CASA','Patinho, arroz, feij├úo, batata frita, ovo, salada mista e p├úo',30.00,NULL,'2025-04-17 21:26:19',NULL),(33,'4002',4,'PRATO FEITO FIL├ë','Fil├®, arroz, feij├úo, batata frita, ovo, salada mista e p├úo',42.00,NULL,'2025-04-17 21:26:19',NULL),(34,'4003',4,'PRATO FEITO COX├âO MOLE','Cox├úo mole, arroz, feij├úo, batata frita, ovo, salada mista e p├úo',38.00,NULL,'2025-04-17 21:26:19',NULL),(35,'4004',4,'├Ç LA MINUTA ALCATRA','Bife de alcatra, arroz, feij├úo, batata frita, ovo, salada mista e p├úo',40.00,NULL,'2025-04-17 21:26:19',NULL),(36,'4005',4,'├Ç LA MINUTA FIL├ë','Bife de fil├®, arroz, salada e batata palha ou batata frita',45.00,NULL,'2025-04-17 21:26:19',NULL),(37,'5001',5,'TORRADA AMERICANA','P├úo de xis, tomate, alface, maionese, 2 fatias de presunto, 2 fatias de queijo e ovo',24.00,NULL,'2025-04-17 21:26:19',NULL),(38,'5002',5,'TORRADA COM BACON','3 p├úes, 2 fatias de presunto, 4 fatias de queijo, alface, tomate e maionese',28.00,NULL,'2025-04-17 21:26:19',NULL),(39,'6001',6,'ROD├ìZIO DE BIFES','Bife de gado, frango e porco, bauru, arroz, batata frita, massa, salada e p├úo',62.00,NULL,'2025-04-17 21:26:19',NULL),(40,'7001',7,'T├üBUA DE FRIOS PEQUENA','Azeitona, queijo, palmito, pepino, p├úo torrado, ovo de codorna e fil├®',55.00,NULL,'2025-04-17 21:26:19',NULL),(41,'7002',7,'T├üBUA DE FRIOS M├ëDIA','Azeitona, queijo, palmito, pepino, p├úo torrado, ovo de codorna e fil├®',90.00,NULL,'2025-04-17 21:26:19',NULL),(42,'7003',7,'T├üBUA DE FRIOS GRANDE','Carnes (frango e gado), batata, polenta, queijo, ovo de codorna e cebola',105.00,NULL,'2025-04-17 21:26:19',NULL),(43,'7004',7,'BATATA FRITA PEQUENA (200G)',NULL,18.00,NULL,'2025-04-17 21:26:19',NULL),(44,'7005',7,'BATATA FRITA PEQUENA COM CHEDDAR E BACON',NULL,30.00,NULL,'2025-04-17 21:26:19',NULL),(45,'7006',7,'BATATA FRITA GRANDE (400G)',NULL,28.00,NULL,'2025-04-17 21:26:19',NULL),(46,'7007',7,'BATATA FRITA GRANDE COM CHEDDAR E BACON',NULL,35.00,NULL,'2025-04-17 21:26:19',NULL),(47,'7008',7,'POLENTA FRITA (500G)',NULL,22.00,NULL,'2025-04-17 21:26:19',NULL),(48,'7009',7,'QUEIJO NA CHAPA',NULL,10.00,NULL,'2025-04-17 21:26:19',NULL),(49,'7010',7,'BATATA, POLENTA E QUEIJO',NULL,42.00,NULL,'2025-04-17 21:26:19',NULL),(50,'8001',8,'├üGUA MINERAL',NULL,5.00,NULL,'2025-04-17 21:26:19',NULL),(51,'8002',8,'├üGUA T├öNICA (LATA)',NULL,6.00,NULL,'2025-04-17 21:26:19',NULL),(52,'8003',8,'H2O 500ML',NULL,7.00,NULL,'2025-04-17 21:26:19',NULL),(53,'8004',8,'H2O 1,5L',NULL,12.00,NULL,'2025-04-17 21:26:19',NULL),(54,'8005',8,'REFRIGERANTE (LATA)',NULL,7.00,NULL,'2025-04-17 21:26:19',NULL),(55,'8006',8,'REFRIGERANTE KS','',5.00,NULL,'2025-04-17 21:26:19',NULL),(56,'8007',8,'REFRIGERANTE 600ML',NULL,8.00,NULL,'2025-04-17 21:26:19',NULL),(57,'8008',8,'REFRIGERANTE 1L',NULL,10.00,NULL,'2025-04-17 21:26:19',NULL),(58,'8009',8,'REFRIGERANTE 2L',NULL,17.00,NULL,'2025-04-17 21:26:19',NULL),(59,'8010',8,'COCA-COLA 2L','',17.00,NULL,'2025-04-17 21:26:19','680e71c41de88.png'),(60,'8011',8,'SUCO NATURAL',NULL,9.00,NULL,'2025-04-17 21:26:19',NULL),(61,'9001',9,'CERVEJA LONG NECK',NULL,10.00,NULL,'2025-04-17 21:26:19',NULL),(62,'9002',9,'CERVEJA 600ML',NULL,17.00,NULL,'2025-04-17 21:26:19',NULL),(63,'9003',9,'CHOPP 300ML',NULL,11.00,NULL,'2025-04-17 21:26:19',NULL),(64,'9004',9,'CHOPP 1L',NULL,30.00,NULL,'2025-04-17 21:26:19',NULL),(65,'9005',9,'TA├çA DE VINHO MOSCATO',NULL,12.00,NULL,'2025-04-17 21:26:19',NULL),(66,'9006',9,'TA├çA DE VINHO MERLOT',NULL,15.00,NULL,'2025-04-17 21:26:19',NULL),(79,'',8,'Suco Delvale','',10.00,NULL,'2025-04-24 18:15:51','680a82a017935.jpg'),(80,NULL,10,'Combo','Combo',65.00,NULL,'2025-04-26 17:09:40','680d2485ec5b9.jpeg');
/*!40000 ALTER TABLE `produtos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `login` varchar(255) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `nivel` int NOT NULL,
  `pergunta` varchar(255) NOT NULL,
  `resposta` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (7,'admin2','$2y$10$jXb/fYAb/u7CrudBx/BQLOmq65AAu02hmIiDCY0dMVx7wr/wkoyxK',1,'admin12','admin12'),(8,'admin','$2y$10$bOYm96HqCJ4p7lcazLBpuO0JllFT6UE8PIC/N2qbHgIzenqKqB2WK',1,'admin','admin');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendas`
--

DROP TABLE IF EXISTS `vendas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vendas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `valor` varchar(255) NOT NULL,
  `cliente` varchar(255) NOT NULL,
  `rendimento` varchar(255) NOT NULL,
  `data` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendas`
--

LOCK TABLES `vendas` WRITE;
/*!40000 ALTER TABLE `vendas` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'divinosys'
--

--
-- Dumping routines for database 'divinosys'
--
/*!50003 DROP FUNCTION IF EXISTS `generate_product_code` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `generate_product_code`() RETURNS char(4) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
    DETERMINISTIC
BEGIN
    DECLARE new_code CHAR(4);
    DECLARE code_exists INT;
    
    generate_code: LOOP
        
        SET new_code = LPAD(FLOOR(RAND() * 8999 + 1000), 4, '0');
        
        
        SELECT COUNT(*) INTO code_exists 
        FROM produtos 
        WHERE codigo = new_code;
        
        IF code_exists = 0 THEN
            LEAVE generate_code;
        END IF;
    END LOOP;
    
    RETURN new_code;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `insert_produto_com_ingredientes` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `insert_produto_com_ingredientes`(IN `p_categoria` VARCHAR(50), IN `p_nome` VARCHAR(100), IN `p_preco` DECIMAL(10,2), IN `p_ingredientes` TEXT)
BEGIN
    DECLARE produto_id INT;
    DECLARE ingredient_id INT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE ingredients_cursor CURSOR FOR 
        SELECT CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(p_ingredientes, ',', n.n), ',', -1)) AS UNSIGNED) as id
        FROM 
        (SELECT 1 + units.i + tens.i * 10 n
         FROM 
         (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) units,
         (SELECT 0 i UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) tens
         WHERE 1 + units.i + tens.i * 10 <= (LENGTH(p_ingredientes) - LENGTH(REPLACE(p_ingredientes, ',', '')) + 1)
        ) n;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    
    INSERT INTO produtos (categoria, nome, preco_venda) 
    VALUES (p_categoria, p_nome, p_preco);
    
    SET produto_id = LAST_INSERT_ID();
    
    
    OPEN ingredients_cursor;
    
    read_loop: LOOP
        FETCH ingredients_cursor INTO ingredient_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO produto_ingredientes (produto_id, ingrediente_id)
        VALUES (produto_id, ingredient_id);
    END LOOP;
    
    CLOSE ingredients_cursor;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-18 19:57:36
