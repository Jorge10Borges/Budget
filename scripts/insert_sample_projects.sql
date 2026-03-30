-- Insertar 3 proyectos de ejemplo en la tabla `projects`
-- Asegúrate de ejecutar esto contra la BD correcta (DB_NAME en .env)

START TRANSACTION;

INSERT INTO `projects` (
  `external_id`, `name`, `description`, `client`, `owner_user_id`, `status`, `budget_amount`, `currency`, `start_date`, `end_date`, `metadata`, `is_active`
) VALUES
('PRJ-001', 'Edificio Central', 'Construcción del edificio central administrativo', 'Inmobiliaria Alfa', 1, 'active', 1250000.00, 'USD', '2026-04-01', '2027-09-30', JSON_OBJECT('phase','foundation','manager','Carlos Ruiz'), 1),
('PRJ-002', 'Rehabilitación Plaza Mayor', 'Rehabilitación integral de la Plaza Mayor', 'Municipio Santiago', 2, 'draft', 320000.00, 'USD', '2026-06-15', '2026-12-31', JSON_OBJECT('heritage', TRUE, 'permit', 'pending'), 1),
('PRJ-003', 'Ampliación Planta Norte', 'Ampliación de planta industrial norte', 'TechBuild S.A.', 3, 'active', 890000.00, 'USD', '2025-11-01', NULL, JSON_OBJECT('stages', JSON_ARRAY('design','construction'), 'priority', 'high'), 1);

COMMIT;

-- Fin
