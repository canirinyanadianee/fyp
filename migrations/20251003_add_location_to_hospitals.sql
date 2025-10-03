-- Migration: Add 'location' column to 'hospitals' table
ALTER TABLE hospitals ADD COLUMN location VARCHAR(255) NULL AFTER state;
