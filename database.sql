-- TeamFlow v2 Schema
-- Run in cPanel > phpMyAdmin > select database > SQL tab
-- FRESH install: paste all and click Go

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  role VARCHAR(100),
  user_role ENUM('member','director','admin') DEFAULT 'member',
  department VARCHAR(100),
  employment_type ENUM('full-time','part-time','contractor') DEFAULT 'full-time',
  status ENUM('active','on-leave','inactive') DEFAULT 'active',
  phone VARCHAR(30),
  avatar_color VARCHAR(20) DEFAULT '#6C5CE7',
  password_hash VARCHAR(255) NOT NULL DEFAULT '',
  temp_password VARCHAR(100) NULL,
  must_change_password TINYINT(1) DEFAULT 1,
  director_id INT NULL,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (director_id) REFERENCES members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  shift_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  status ENUM('upcoming','active','completed','absent') DEFAULT 'upcoming',
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  assigned_to INT NULL,
  due_date DATE,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  status ENUM('pending','in-progress','completed') DEFAULT 'pending',
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assigned_to) REFERENCES members(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NULL,
  sender_name VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS attendance (
  id INT AUTO_INCREMENT PRIMARY KEY,
  member_id INT NOT NULL,
  clock_in DATETIME NOT NULL,
  clock_out DATETIME NULL,
  work_minutes INT NULL,
  date DATE NOT NULL,
  FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- After running this, visit: yourdomain.com/Teamapi/setup.php
-- to create the initial admin account.
