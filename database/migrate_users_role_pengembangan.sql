USE sensus_hardware;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role ENUM('admin', 'pengembangan') NOT NULL DEFAULT 'admin' AFTER nama;

UPDATE users
SET role = 'admin'
WHERE role IS NULL OR role = '';

INSERT INTO users (username, password, nama, role) VALUES
('fadli', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'fadli', 'pengembangan'),
('rian', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'rian', 'pengembangan'),
('fahmi', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'fahmi', 'pengembangan'),
('chris', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'chris', 'pengembangan'),
('amal', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'amal', 'pengembangan'),
('akbar', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'akbar', 'pengembangan'),
('noval', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'noval', 'pengembangan'),
('temy', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'temy', 'pengembangan'),
('deni', '$2y$10$9yotIJ/V9FUXpkTXEB.WOOBPNTyitAANSms.5gA296Diub9fe5Go2', 'deni', 'pengembangan'),
('aisriasmara', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'aisriasmara', 'admin'),
('yuga', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'yuga', 'admin'),
('bayu', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'bayu', 'admin'),
('fachrie', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'fachrie', 'admin'),
('manaf', '$2y$10$VDXV09gdSiaz0nC7u6UfBeoWMlFbijVHT9pY8NlZz6ifcffPvpPkG', 'manaf', 'admin')
ON DUPLICATE KEY UPDATE
password = VALUES(password),
nama = VALUES(nama),
role = VALUES(role);
