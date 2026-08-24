-- ============================================================
-- Migration: add "file_upload" question type to forms
-- ============================================================
-- Run once per database, AFTER 2026_forms.sql.
-- cPanel → phpMyAdmin → select the database → SQL tab → paste & Go.
-- Run on BOTH: bndpitco_oyubiacyf (production) and bndpitco_oyubiacyfdemo (demo).
-- Safe to re-run (it just re-asserts the column definition).
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE form_fields
  MODIFY COLUMN type
  ENUM('short_text','paragraph','multiple_choice','checkboxes','dropdown','email','phone','number','date','file_upload')
  NOT NULL DEFAULT 'short_text';
