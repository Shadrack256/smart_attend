-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: smart_attend
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
-- Table structure for table `attendance`
--

DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('present','late','absent') DEFAULT 'present',
  `scan_lat` decimal(10,7) DEFAULT NULL,
  `scan_lng` decimal(10,7) DEFAULT NULL,
  `distance_m` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`session_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attendance`
--

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
INSERT INTO `attendance` VALUES (76,46,2,'2026-09-14 15:10:58','absent',NULL,NULL,NULL),(77,46,5,'2026-09-14 15:10:58','absent',NULL,NULL,NULL),(78,46,4,'2026-09-14 15:10:58','absent',NULL,NULL,NULL),(79,46,13,'2026-09-14 15:10:58','absent',NULL,NULL,NULL),(80,47,2,'2026-09-15 03:21:56','absent',NULL,NULL,NULL),(81,47,5,'2026-09-15 03:21:56','absent',NULL,NULL,NULL),(82,47,4,'2026-09-15 03:21:56','absent',NULL,NULL,NULL),(83,47,13,'2026-09-15 03:21:56','absent',NULL,NULL,NULL),(87,48,2,'2026-09-15 03:35:51','absent',NULL,NULL,NULL),(88,48,5,'2026-09-15 03:35:51','absent',NULL,NULL,NULL),(89,48,4,'2026-09-15 03:35:51','absent',NULL,NULL,NULL),(90,48,13,'2026-09-15 03:35:51','absent',NULL,NULL,NULL),(91,49,2,'2026-09-15 05:50:33','absent',NULL,NULL,NULL),(92,49,5,'2026-09-15 05:50:33','absent',NULL,NULL,NULL),(93,49,4,'2026-09-15 05:50:33','absent',NULL,NULL,NULL),(94,49,13,'2026-09-15 05:50:33','absent',NULL,NULL,NULL);
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_code` varchar(20) NOT NULL,
  `course_name` varchar(150) NOT NULL,
  `lecturer_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `lecturer_id` (`lecturer_id`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`lecturer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,'BSM 2102','Innovation And Entrepreneurship Skills',3),(2,'BSM 2103','DATA Communication',8);
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `enrollments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `enrolled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enroll` (`student_id`,`course_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,2,1,'2026-09-12 20:56:13'),(2,5,1,'2026-09-12 21:33:56'),(3,4,1,'2026-09-12 21:33:56'),(5,2,2,'2026-09-14 09:34:22'),(6,5,2,'2026-09-14 09:34:22'),(7,4,2,'2026-09-14 09:34:22'),(8,11,2,'2026-09-14 09:34:22'),(9,13,1,'2026-09-14 10:56:19');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `session_date` date NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `qr_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `radius_m` int(11) DEFAULT 100,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES (46,1,'2026-09-14','2026-09-14 18:10:37','2026-09-14 18:10:58',NULL,NULL,0,0.3273040,32.6147200,100),(47,1,'2026-09-15','2026-09-15 06:21:35','2026-09-15 06:21:56',NULL,NULL,0,0.3392330,32.6263430,100),(48,1,'2026-09-15','2026-09-15 06:35:47','2026-09-15 06:35:51',NULL,NULL,0,0.3392330,32.6263430,100),(49,1,'2026-09-15','2026-09-15 08:50:09','2026-09-15 08:50:33',NULL,NULL,0,0.3392330,32.6263430,100);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `k` varchar(50) NOT NULL,
  `v` varchar(255) NOT NULL,
  PRIMARY KEY (`k`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('brand_color','#234ce1'),('default_radius_m','1000'),('demo_contact_email',''),('favicon',''),('footer_enabled','1'),('footer_text','Ssebagereka Shadrack'),('geofence_enabled','1'),('institution_address',''),('institution_email',''),('institution_name',''),('institution_phone',''),('institution_website',''),('login_bg','loginbg_05b56eef088da313.png'),('login_bg_overlay','40'),('report_signer_name',''),('report_title',''),('system_logo','logo_d0052521f12b6b9c.png'),('system_name','SmartAttend'),('system_tagline','Admin Console');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `reg_number` varchar(50) DEFAULT NULL,
  `staff_id` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','lecturer','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `reg_number` (`reg_number`),
  UNIQUE KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,NULL,'Ssebagereka Shadrack','shadrackssebagereka2002@gmail.com','user_1_780c4d716250851e.png','$2y$10$6Zq5QmlVoRCdwrGXucwjqeUse6t1ylJcHbkC4Ige8YpxaUXqqNkwC','admin','2026-09-11 21:29:12'),(2,'2025/ITB/DAY/1790/G',NULL,'Kasoga Shaminah','kasoga24@gmail.com','user_2_c1f6ba24592f2222.jpg','$2y$10$hHncTJNh0ov2J0WT1927De7m2/0H4RwxezpAsBhGwo9RUEEgkKl52','student','2026-09-12 14:15:17'),(3,NULL,'STFID001','Katumba Abbas','abbaskatumba@gmail.com','user_3_47aa8a024c8f24ac.png','$2y$10$jx55Wqz7MMX9Ik8z9.2BjOTkQ8X1BcrrZMWex5fkJ0.wRVoaxOgMe','lecturer','2026-09-12 16:08:54'),(4,'2025/ITB/DAY/2314/G',NULL,'Nakalema Hanisha','nakalemahanisha7@gmail.com',NULL,'$2y$10$SLujDkSp4GQ.68.6IKf0ae7ENgjZriCzSRaLVxkJGu.y/F56uNjgy','student','2026-09-12 21:15:59'),(5,'2025/ITB/DAY/0268/P',NULL,'Mayanda Gonzaga','mayandagonzaga@gmail.com',NULL,'$2y$10$0e22FZLax545uu081fgb3eMamy9Gy02caBTl/HvIquAi1gOk16Gl.','student','2026-09-12 21:17:51'),(6,NULL,'STFID002','Ssegane Karim','sseganekarim@gmail.com',NULL,'$2y$10$cmluxT0bYmgFv62blPkJj.ovO2U6ElgNiLD/gS64y/dNfWjAmtCTS','lecturer','2026-09-13 12:46:06'),(7,NULL,'STFID003','Namugalu Dativah','namugaludativah@gmail.com',NULL,'$2y$10$7qqe3THLTQ2gbn.ipAh.uutgzdxqBuEw830yOJxMYt6N1SUL3pHCy','lecturer','2026-09-13 12:47:44'),(8,NULL,'STFID004','Ankwasa Recheal','ankwasarecheal@gmail.com','user_8_8d4982953cd427af.jpg','$2y$10$H0nU8PQPWwyKc5cZeE5sRep7XA7Y6C2760Bz.1OkihJDhneGUAl1e','lecturer','2026-09-13 12:50:16'),(10,NULL,'STFID005','Kasoga Shaminah','kasogashaminah@gmail.com',NULL,'$2y$10$lIC/HzIebOfUlYCb7FkQ/.t9ltk5.73b8oI3AS5qe96AddkeIBFnq','lecturer','2026-09-13 12:53:56'),(11,'2025/ITB/DAY/2717/p',NULL,'Ssebagereka Shadrack','shadrackssebagereka2003@gmail.com',NULL,'$2y$10$DovLkzh04mXJj/LNllkY8.kOqoaZu2faXBJtiBZ3f4UeQXsfAkzT.','student','2026-09-13 13:13:47'),(12,'2025/ITB/DAY/1948/G',NULL,'Ssegane Karim','sseganekarim746@gmail.com',NULL,'$2y$10$ckynhep7LH.exd6RTyfebODuEXMjqigel7qfAHGO7dMtNSo1TTf22','student','2026-09-14 10:48:08'),(13,'2025/ITB/DAY/2303/G',NULL,'Mutatina Freedom','mutatinafreedom@gmail.com','user_13_095a260d83995eb3.jpg','$2y$10$v1QAlk2Kc0ENHAIERGeJhOSidB1bsZsSJeUPPcKjCL.e7ucjhCfay','student','2026-09-14 10:54:12'),(14,'2025/DCS/DAY/1900/G',NULL,'Katushabe Vivian','kelishabae27@gmail.com','user_14_21a105f9f5cb21ab.jpg','$2y$10$saj7Sclu7YHPWvXGIWrfuOxJvm/vOzlYdinvlArKf5vXzHBXFS1ru','student','2026-09-15 05:55:26');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'smart_attend'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-15 10:34:16
