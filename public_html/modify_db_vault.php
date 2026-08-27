<?php

$host = 'localhost';
$db   = 'noodluis_resolve';
$user = 'noodluis_DEV_resolve';
$pass = '+wxM$&RkY^Ye';

$mysqli = new mysqli($host, $user, $pass, $db);

if ($mysqli->connect_error) {
    die("Error de conexión: " . $mysqli->connect_error);
}

$sql = "
CREATE TABLE IF NOT EXISTS `project_credentials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `environment` varchar(50) NOT NULL DEFAULT 'Prod',
  `username` varchar(255) DEFAULT NULL,
  `encrypted_payload` text NOT NULL,
  `iv` varchar(255) NOT NULL,
  `auth_tag` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `fk_proj_cred_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($mysqli->query($sql) === TRUE) {
    echo "Tabla project_credentials creada exitosamente.\n";
} else {
    echo "Error creando la tabla: " . $mysqli->error . "\n";
}

$mysqli->close();
