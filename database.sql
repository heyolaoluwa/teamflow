-- TeamFlow v3 Schema
-- Run in cPanel > phpMyAdmin > select database > SQL tab
-- FRESH install: paste all and click Go

-- 1. Workspaces
CREATE TABLE IF NOT EXISTS workspaces (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL,
  description VARCHAR(255)  DEFAULT '',
  logo_url    VARCHAR(255)  DEFAULT NULL,
  created_by  INT           NULL,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- 2. Directorates (must come first — members references it)
CREATE TABLE IF NOT EXISTS directorates (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100)  NOT NULL,
  description VARCHAR(255)  DEFAULT '',
  director_id INT           NULL,   -- FK to members added after members table
  created_by  INT           NULL,
  created_at  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
);

-- 3. Members
CREATE TABLE IF NOT EXISTS members (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  name                 VARCHAR(100)  NOT NULL,
  email                VARCHAR(150)  NOT NULL UNIQUE,
  role                 VARCHAR(100),
  user_role            ENUM('member','director','admin') DEFAULT 'member',
  department           VARCHAR(100),
  employment_type      ENUM('full-time','part-time','contractor') DEFAULT 'full-time',
  status               ENUM('active','on-leave','inactive') DEFAULT 'active',
  phone                VARCHAR(30),
  avatar_color         VARCHAR(20)  DEFAULT '#6C5CE7',
  password_hash        VARCHAR(255) NOT NULL DEFAULT '',
  temp_password        VARCHAR(100) NULL,
  must_change_password TINYINT(1)   DEFAULT 1,
  director_id          INT          NULL,
  directorate_id       INT          NULL,
  workspace_id         INT          NULL,
  last_login           TIMESTAMP    NULL,
  created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id)    REFERENCES members(id)     ON DELETE SET NULL,
  FOREIGN KEY (directorate_id) REFERENCES directorates(id) ON DELETE SET NULL,
  FOREIGN KEY (workspace_id)   REFERENCES workspaces(id) ON DELETE SET NULL
);

-- 3. Add the circular FK: directorates.director_id → members.id
SET @cnt = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'directorates'
    AND CONSTRAINT_NAME = 'fk_directorate_director'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SELECT IF(@cnt = 0,
  'ALTER TABLE directorates ADD CONSTRAINT fk_directorate_director FOREIGN KEY (director_id) REFERENCES members(id) ON DELETE SET NULL',
  'SELECT "fk_directorate_director already exists"'
) INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cnt = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'directorates'
    AND CONSTRAINT_NAME = 'fk_directorate_created_by'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SELECT IF(@cnt = 0,
  'ALTER TABLE directorates ADD CONSTRAINT fk_directorate_created_by FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL',
  'SELECT "fk_directorate_created_by already exists"'
) INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @cnt = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'workspaces'
    AND CONSTRAINT_NAME = 'fk_workspace_created_by'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SELECT IF(@cnt = 0,
  'ALTER TABLE workspaces ADD CONSTRAINT fk_workspace_created_by FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL',
  'SELECT "fk_workspace_created_by already exists"'
) INTO @sql;
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Shifts
CREATE TABLE IF NOT EXISTS shifts (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  member_id  INT  NOT NULL,
  shift_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time   TIME NOT NULL,
  status     ENUM('upcoming','active','completed','absent') DEFAULT 'upcoming',
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- 5. Tasks  (assigned_directorate_id is new in v3)
CREATE TABLE IF NOT EXISTS tasks (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  title                 VARCHAR(200) NOT NULL,
  assigned_to           INT  NULL,
  assigned_directorate_id INT NULL,
  due_date              DATE,
  priority              ENUM('low','medium','high') DEFAULT 'medium',
  status                ENUM('pending','in-progress','completed') DEFAULT 'pending',
  work_plan             TEXT NULL,
  closing_note          TEXT NULL,
  created_by            INT  NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_to)            REFERENCES members(id)      ON DELETE SET NULL,
  FOREIGN KEY (created_by)             REFERENCES members(id)      ON DELETE SET NULL,
  FOREIGN KEY (assigned_directorate_id) REFERENCES directorates(id) ON DELETE SET NULL
);

-- 6. Messages
CREATE TABLE IF NOT EXISTS messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  member_id   INT  NULL,
  sender_name VARCHAR(100) NOT NULL,
  content     TEXT         NOT NULL,
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
);

-- 7. Attendance
CREATE TABLE IF NOT EXISTS attendance (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  member_id        INT      NOT NULL,
  clock_in         DATETIME NOT NULL,
  clock_out        DATETIME NULL,
  work_minutes     INT      NULL,
  date             DATE     NOT NULL,
  task_note        TEXT     NULL,
  achievement_note TEXT     NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- Run this on existing databases to add the note columns:
-- ALTER TABLE attendance ADD COLUMN task_note TEXT NULL, ADD COLUMN achievement_note TEXT NULL;

-- 8. Leave / Break Requests
CREATE TABLE IF NOT EXISTS leave_requests (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  member_id      INT NOT NULL,
  type           ENUM('annual_leave','sick_leave','emergency_leave',
                      'maternity_leave','paternity_leave','short_break','other') NOT NULL,
  start_date     DATE         NOT NULL,
  end_date       DATE         NULL,
  reason         TEXT,
  status         ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by    INT          NULL,
  reviewer_notes VARCHAR(255) NULL,
  created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id)   REFERENCES members(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES members(id) ON DELETE SET NULL
);

-- After running this, visit: yourdomain.com/Teamapi/setup.php
-- to create the initial admin account.
