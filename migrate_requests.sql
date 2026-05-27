-- ============================================================
-- TeamFlow — Requests Feature Migration
-- Run this in phpMyAdmin AFTER migrate_directorates.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS leave_requests (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  member_id      INT NOT NULL,
  type           ENUM(
                   'annual_leave',
                   'sick_leave',
                   'emergency_leave',
                   'maternity_leave',
                   'paternity_leave',
                   'short_break',
                   'other'
                 ) NOT NULL,
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
