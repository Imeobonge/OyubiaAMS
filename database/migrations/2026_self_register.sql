-- ============================================================
-- Migration: QR self-registration toggle on editions
-- ============================================================
-- Run once per database. cPanel → phpMyAdmin → select DB → SQL → Go.
-- Run on BOTH: bndpitco_oyubiacyf (production) and bndpitco_oyubiacyfdemo (demo).
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE editions
  ADD COLUMN self_register_open TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active;
