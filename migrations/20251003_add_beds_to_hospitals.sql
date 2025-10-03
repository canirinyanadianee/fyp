-- Migration: Add 'beds' column to 'hospitals' table
ALTER TABLE hospitals ADD COLUMN beds INT NULL AFTER location;
