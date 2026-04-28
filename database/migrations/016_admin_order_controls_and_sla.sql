ALTER TABLE orders
  ADD COLUMN admin_priority VARCHAR(20) NOT NULL DEFAULT 'normal' AFTER status,
  ADD COLUMN admin_sla_due_at DATETIME NULL AFTER admin_priority,
  ADD INDEX idx_orders_admin_priority_sla (admin_priority, admin_sla_due_at);
