-- Migration: Upgrade appointments table to support approval/rejection and reasons
-- Run this in your blood_management database

ALTER TABLE `appointments`
  MODIFY `status` ENUM('pending','approved','rejected','confirmed','cancelled','completed') DEFAULT 'pending';

-- Add optional notes on the appointment
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `status`;

-- Track staff decision for approve/reject
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `decision_reason` TEXT NULL AFTER `notes`,
  ADD COLUMN IF NOT EXISTS `decided_by` INT NULL AFTER `decision_reason`,
  ADD COLUMN IF NOT EXISTS `decided_at` TIMESTAMP NULL AFTER `decided_by`;

-- Track cancellation separately
ALTER TABLE `appointments`
  ADD COLUMN IF NOT EXISTS `cancel_reason` TEXT NULL AFTER `decided_at`,
  ADD COLUMN IF NOT EXISTS `cancelled_at` TIMESTAMP NULL AFTER `cancel_reason`;

-- Optional: add foreign keys (if you want strong integrity)
-- ALTER TABLE `appointments` ADD CONSTRAINT `fk_appointments_decided_by_user`
--   FOREIGN KEY (`decided_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
