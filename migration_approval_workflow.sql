-- Migration: Add Exam Approval Workflow
-- Run this script in phpMyAdmin or MySQL CLI
-- Date: 2026-01-18

USE exam_timetable;

-- =====================================================
-- Step 1: Add new columns to examens table
-- =====================================================

-- Add formation_id column (optional FK)
ALTER TABLE examens 
ADD COLUMN formation_id INT NULL AFTER module_id;

-- Add approval status columns
ALTER TABLE examens 
ADD COLUMN accepted_by_chefdep BOOLEAN DEFAULT NULL AFTER status,
ADD COLUMN accepted_by_doyen BOOLEAN DEFAULT NULL AFTER accepted_by_chefdep;

-- Add foreign key for formation_id
ALTER TABLE examens
ADD CONSTRAINT fk_examens_formation 
FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL;

-- Add indexes for the new columns
ALTER TABLE examens
ADD INDEX idx_formation_id (formation_id),
ADD INDEX idx_accepted_chefdep (accepted_by_chefdep),
ADD INDEX idx_accepted_doyen (accepted_by_doyen);

-- =====================================================
-- Step 2: Populate formation_id from existing data
-- =====================================================

-- Set formation_id based on module's formation
UPDATE examens e
JOIN modules m ON e.module_id = m.id
SET e.formation_id = m.formation_id
WHERE e.formation_id IS NULL;

-- =====================================================
-- Step 3: Update departements.chef_id to reference professeurs
-- =====================================================

-- First, we need to convert existing chef_id values from users.id to professeurs.id
-- Create a temporary column to hold the new value
ALTER TABLE departements 
ADD COLUMN chef_prof_id INT NULL;

-- Convert user_id to professeur_id
UPDATE departements d
JOIN professeurs p ON d.chef_id = p.user_id
SET d.chef_prof_id = p.id
WHERE d.chef_id IS NOT NULL;

-- Drop the old foreign key constraint
ALTER TABLE departements
DROP FOREIGN KEY departements_ibfk_1;

-- Drop the old chef_id column and rename the new one
ALTER TABLE departements
DROP COLUMN chef_id;

ALTER TABLE departements
CHANGE COLUMN chef_prof_id chef_id INT NULL;

-- Add the new foreign key referencing professeurs
ALTER TABLE departements
ADD CONSTRAINT fk_departements_chef 
FOREIGN KEY (chef_id) REFERENCES professeurs(id) ON DELETE SET NULL;

-- =====================================================
-- Verification queries (optional - run to check)
-- =====================================================

-- Check examens table structure
-- DESCRIBE examens;

-- Check departements table structure  
-- DESCRIBE departements;

-- Verify foreign keys
-- SELECT * FROM information_schema.TABLE_CONSTRAINTS 
-- WHERE TABLE_NAME IN ('examens', 'departements') AND CONSTRAINT_TYPE = 'FOREIGN KEY';

SELECT 'Migration completed successfully!' as status;
