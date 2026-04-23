ALTER TABLE payroll_entries
  ADD COLUMN paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER worked_night;

UPDATE payroll_entries pe
INNER JOIN employees e ON e.id = pe.employee_id
SET pe.paid_amount = ROUND((pe.worked_day * e.day_rate) + (pe.worked_night * e.night_rate), 2)
WHERE pe.paid_amount = 0.00;
