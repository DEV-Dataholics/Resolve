-- Estructura de Base de Datos para Dataholics Resolve
-- Script de Instalación Inicial

DROP DATABASE IF EXISTS noodluis_DEV_resolve;
CREATE DATABASE IF NOT EXISTS noodluis_DEV_resolve;
USE noodluis_DEV_resolve;

-- 1. Empresas (Multi-tenant)
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NULL,        -- Link personalizado: /portal/{slug}
    status ENUM('active', 'inactive') DEFAULT 'active',
    is_internal BOOLEAN DEFAULT FALSE,    -- TRUE para Dataholics y empresas internas
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Usuarios
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'servicedesk', 'client') DEFAULT 'client',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Equipos (Teams)
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tickets
CREATE TABLE IF NOT EXISTS tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    creator_id INT NOT NULL,
    assigned_team_id INT NULL,
    assigned_agent_id INT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    type ENUM('external', 'internal') DEFAULT 'external',
    status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    attachment_urls JSON NULL,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_team_id) REFERENCES teams(id) ON SET NULL,
    FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Comentarios
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    author_id INT NOT NULL,
    text TEXT NOT NULL,
    attachment_urls JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos Iniciales de Prueba
INSERT INTO companies (name, slug, is_internal) VALUES ('Dataholics', 'dataholics', TRUE);
INSERT INTO teams (name, description) VALUES ('Frontend', 'Desarrollo de interfaces'), ('Backend', 'Desarrollo de APIs e Infraestructura');

-- ============================================================
-- MIGRACIÓN para servidores existentes (ejecutar en producción)
-- ============================================================
-- ALTER TABLE companies ADD COLUMN slug VARCHAR(100) UNIQUE NULL AFTER name;
-- UPDATE companies SET slug = 'dataholics' WHERE id = 1;
