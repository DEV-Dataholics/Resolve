SET NAMES utf8mb4;

INSERT INTO kb_articles (title, slug, category, content, status, author_id, created_at, updated_at)
VALUES
('Calculadoras Sotelo - Manual Tecnico', 'calculadoras-sotelo-manual-tecnico', 'Calculadoras Sotelo', 'Manual tecnico base del proyecto Calculadoras Sotelo. Incluye arquitectura React + PHP, flujo CSV Genesis, endpoints /api/upload y /api/calculate, reglas de diesel y despliegue en Site5. Fuente: knowledgebase/proyectos/Calculadoras Sotelo/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('Calculadoras Sotelo - Manual de Usuario', 'calculadoras-sotelo-manual-usuario', 'Calculadoras Sotelo', 'Manual de usuario para carga de CSV, captura manual por viaje, recalculo y aprobacion semanal de nomina. Fuente: knowledgebase/proyectos/Calculadoras Sotelo/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('CFI - Manual Tecnico', 'cfi-manual-tecnico', 'CFI', 'Manual tecnico del MVP Nuevo Pizarron Digital. Define stack CI4 + MySQL, fases de implementacion y lineamientos de seguridad por rol. Fuente: knowledgebase/proyectos/CFI/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('CFI - Manual de Usuario', 'cfi-manual-usuario', 'CFI', 'Manual de usuario por rol para activos, viajes, mantenimiento y finanzas en CFI. Fuente: knowledgebase/proyectos/CFI/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('Dataholics Resolve - Manual Tecnico', 'dataholics-resolve-manual-tecnico', 'Dataholics Resolve', 'Manual tecnico de arquitectura Resolve: API CI4, panel administrativo, flujo de tickets y knowledge base en tabla kb_articles. Fuente: knowledgebase/proyectos/Dataholics Resolve/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('Dataholics Resolve - Manual de Usuario', 'dataholics-resolve-manual-usuario', 'Dataholics Resolve', 'Manual de usuario para login, gestion de tickets, uso de KB y panel admin de Resolve. Fuente: knowledgebase/proyectos/Dataholics Resolve/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('DL Terra - Manual Tecnico', 'dl-terra-manual-tecnico', 'DL Terra', 'Manual tecnico del sistema DL Terra: arquitectura CodeIgniter 4 + MySQL, modulo de usuarios admin, autenticacion API y despliegue por FTP. Fuente: knowledgebase/proyectos/dlterra/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('DL Terra - Manual de Usuario', 'dl-terra-manual-usuario', 'DL Terra', 'Manual de usuario para operacion diaria en ventas, clientes, cobranza, inventario, finanzas y obra en DL Terra. Fuente: knowledgebase/proyectos/dlterra/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('foraneo-chihuahua - Manual Tecnico', 'foraneo-chihuahua-manual-tecnico', 'foraneo-chihuahua', 'Manual tecnico para nomina foranea: API PHP stateless, flujo de calculo diesel y criterios de QA/regresion. Fuente: knowledgebase/proyectos/foraneo-chihuahua/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('foraneo-chihuahua - Manual de Usuario', 'foraneo-chihuahua-manual-usuario', 'foraneo-chihuahua', 'Manual de usuario para operadores administrativos: carga de CSV, captura manual y aprobacion de resultados. Fuente: knowledgebase/proyectos/foraneo-chihuahua/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('PRISMA - Manual Tecnico', 'prisma-manual-tecnico', 'PRISMA', 'Manual tecnico del blueprint PRISMA: modelo de datos, modulos de administracion, impacto y donantes, y reglas de control. Fuente: knowledgebase/proyectos/PRISMA/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('PRISMA - Manual de Usuario', 'prisma-manual-usuario', 'PRISMA', 'Manual de usuario de alto nivel para operacion de PRISMA por modulo y por etapa de caso. Fuente: knowledgebase/proyectos/PRISMA/Manual_Usuario.md', 'published', NULL, NOW(), NOW()),

('United Way - Chihuahua - Manual Tecnico', 'united-way-chihuahua-manual-tecnico', 'United Way - Chihuahua', 'Manual tecnico de Somos Comunidad: React + CI4, despliegue en Site5, ruteo SPA/API y configuraciones criticas. Fuente: knowledgebase/proyectos/United Way - Chihuahua/Manual_Tecnico.md', 'published', NULL, NOW(), NOW()),
('United Way - Chihuahua - Manual de Usuario', 'united-way-chihuahua-manual-usuario', 'United Way - Chihuahua', 'Manual de usuario para registro, participacion y consulta de impacto en Somos Comunidad. Fuente: knowledgebase/proyectos/United Way - Chihuahua/Manual_Usuario.md', 'published', NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  category = VALUES(category),
  content = VALUES(content),
  status = VALUES(status),
  updated_at = NOW();
