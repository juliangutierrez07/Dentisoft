-- Migration: Add ultimo_acceso to usuarios
-- Fecha: 2026-06-10

ALTER TABLE usuarios
    ADD COLUMN ultimo_acceso DATETIME NULL AFTER estado;
