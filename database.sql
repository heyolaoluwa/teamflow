-- Run this in cPanel > phpMyAdmin
-- Select your database first, then paste this and click Go

CREATE TABLE IF NOT EXISTS members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  role VARCHAR(100),
  department VARCHAR(100),
  employment_type ENUM('full-time','part-time','contractor') DEFAULT 'full-time',
  status ENUM('active','on-leave','inactive') DEFAULT 'active',
  phone VARCHAR(30),
  avatar_color VARCHAR(20) DEFAULT '#6C5CE7',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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
  assigned_to VARCHAR(100),
  due_date DATE,
  priority ENUM('low','medium','high') DEFAULT 'medium',
  status ENUM('pending','in-progress','completed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_name VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sample data
INSERT INTO members (name, email, role, department, employment_type, status, phone, avatar_color) VALUES
('James Lee', 'james@company.com', 'Floor Manager', 'Operations', 'full-time', 'active', '+65 9123 4567', '#6C5CE7'),
('Sofia Reyes', 'sofia@company.com', 'Cashier', 'Sales', 'part-time', 'active', '+65 9234 5678', '#00B894'),
('Maya Patel', 'maya@company.com', 'Supervisor', 'Operations', 'full-time', 'on-leave', '+65 9345 6789', '#E17055'),
('Tom Kim', 'tom@company.com', 'Stock Associate', 'Support', 'part-time', 'active', '+65 9456 7890', '#0984E3'),
('Aisha Lowe', 'aisha@company.com', 'Cashier', 'Sales', 'full-time', 'active', '+65 9567 8901', '#FDCB6E');

INSERT INTO shifts (member_id, shift_date, start_time, end_time, status) VALUES
(1, CURDATE(), '08:00:00', '16:00:00', 'active'),
(2, CURDATE(), '09:00:00', '14:00:00', 'active'),
(4, CURDATE(), '10:00:00', '18:00:00', 'active'),
(3, CURDATE(), '14:00:00', '22:00:00', 'upcoming'),
(5, CURDATE(), '16:00:00', '22:00:00', 'upcoming');

INSERT INTO tasks (title, assigned_to, due_date, priority, status) VALUES
('Update staff handbook', 'James Lee', CURDATE(), 'high', 'completed'),
('Send payroll summaries', NULL, CURDATE(), 'high', 'completed'),
('Review onboarding docs', 'James Lee', CURDATE(), 'medium', 'pending'),
('Post next week schedule', 'James Lee', DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'high', 'in-progress'),
('Conduct team check-in', 'Maya Patel', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'medium', 'pending'),
('Order supplies inventory', 'Tom Kim', DATE_ADD(CURDATE(), INTERVAL 4 DAY), 'low', 'pending');

INSERT INTO messages (sender_name, content) VALUES
('Sofia Reyes', 'Morning everyone! Who is covering the 2pm shift?'),
('James Lee', 'I can do it if Tom is busy'),
('Maya Patel', 'Reminder: team meeting at 3pm in the break room!');
