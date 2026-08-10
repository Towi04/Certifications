-- Método de pago en casos (cash|transfer|openpay|other).
-- Si la columna ya existe, ignora el error de duplicado.
ALTER TABLE certification_cases
  ADD COLUMN payment_method VARCHAR(32) NULL
  COMMENT 'cash|transfer|openpay|other'
  AFTER payment_confirmed_at;
