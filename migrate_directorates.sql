-- ============================================================
-- TeamFlow v3 Migration — run this in phpMyAdmin on EXISTING
-- databases that already have the v2 tables.
-- (Fresh installs: use database.sql instead — skip this file)
-- ============================================================

-- Step 1: Create the directorates table
CREATE TABLE IF NOT EXISTS directorates (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT '',
  director_id INT          NULL,
  created_by  INT          NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Step 2: Add new columns to existing tables
ALTER TABLE members ADD COLUMN IF NOT EXISTS directorate_id INT NULL;
ALTER TABLE tasks   ADD COLUMN IF NOT EXISTS assigned_directorate_id INT NULL;

-- Step 3: Add foreign keys (safe to skip if they already exist)
ALTER TABLE members
  ADD CONSTRAINT fk_member_directorate
  FOREIGN KEY (directorate_id) REFERENCES directorates(id) ON DELETE SET NULL;

ALTER TABLE tasks
  ADD CONSTRAINT fk_task_directorate
  FOREIGN KEY (assigned_directorate_id) REFERENCES directorates(id) ON DELETE SET NULL;

ALTER TABLE directorates
  ADD CONSTRAINT fk_directorate_director
  FOREIGN KEY (director_id) REFERENCES members(id) ON DELETE SET NULL;

ALTER TABLE directorates
  ADD CONSTRAINT fk_directorate_created_by
  FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL;
