INSERT INTO expense_categories (code, name, description, sort_order)
VALUES
  ('MATERIALS', 'Materiales', 'Compra de materiales de construccion o insumos directos', 10),
  ('LABOR', 'Mano de obra', 'Pagos por personal operativo, tecnicos y contratistas', 20),
  ('SUBCONTRACT', 'Subcontratos', 'Servicios tercerizados para actividades especificas', 30),
  ('EQUIPMENT_RENTAL', 'Alquiler de equipo', 'Renta de maquinaria, herramientas o vehiculos', 40),
  ('LOGISTICS', 'Logistica y transporte', 'Fletes, envios, combustible y traslados', 50),
  ('PERMITS', 'Permisos y licencias', 'Tramites, permisos municipales y derechos', 60),
  ('UTILITIES', 'Servicios y suministros', 'Agua, energia electrica, internet y suministros generales', 70),
  ('SAFETY', 'Seguridad y salud', 'EPP, senalizacion, capacitacion y prevencion', 80),
  ('MAINTENANCE', 'Mantenimiento y reparaciones', 'Mantenimiento preventivo y correctivo', 90),
  ('ADMIN', 'Gastos administrativos', 'Papeleria, administracion y soporte de oficina', 100),
  ('PROFESSIONAL_FEES', 'Honorarios profesionales', 'Arquitectura, ingenieria, consultoria y legales', 110),
  ('TAXES', 'Impuestos y tasas', 'Retenciones, impuestos y contribuciones', 120),
  ('INSURANCE', 'Seguros y fianzas', 'Polizas, coberturas y garantias', 130),
  ('FINANCIAL', 'Gastos financieros', 'Intereses, comisiones bancarias y financiamiento', 140),
  ('CONTINGENCY', 'Imprevistos', 'Gastos no planeados o contingencias del proyecto', 150)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  sort_order = VALUES(sort_order),
  is_active = 1;
