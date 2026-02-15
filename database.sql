CREATE DATABASE IF NOT EXISTS trainee_platform
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE trainee_platform;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('trainee','admin') NOT NULL,
  location VARCHAR(190),
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  bio TEXT,
  experience TEXT,
  location VARCHAR(190),
  photo VARCHAR(255),
  status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_programs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,
  category_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  duration_hours INT NOT NULL DEFAULT 1,
  price INT NOT NULL DEFAULT 0,
  availability_slots VARCHAR(255),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_program_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_program_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainee_id INT NOT NULL,
  trainer_id INT NOT NULL,
  program_id INT NOT NULL,
  session_date DATE NOT NULL,
  session_time TIME NOT NULL,
  duration_minutes INT NOT NULL,
  status ENUM('pending','accepted','rejected','paid','completed') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_booking_trainee FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  amount INT NOT NULL,
  status ENUM('paid','failed') NOT NULL,
  method VARCHAR(50),
  paid_at DATETIME,
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS materials (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,
  program_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  url VARCHAR(255) NOT NULL,
  type ENUM('document') NOT NULL DEFAULT 'document',
  CONSTRAINT fk_material_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_material_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainer_id INT NOT NULL,
  program_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  url VARCHAR(255) NOT NULL,
  CONSTRAINT fk_video_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_video_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS quizzes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  program_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  total_marks INT DEFAULT 100,
  active_from DATETIME NULL,
  active_to DATETIME NULL,
  CONSTRAINT fk_quiz_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  program_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  due_date DATETIME,
  CONSTRAINT fk_task_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS progress (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainee_id INT NOT NULL,
  program_id INT NOT NULL,
  completed_lessons INT NOT NULL DEFAULT 0,
  total_lessons INT NOT NULL DEFAULT 5,
  quiz_score INT NULL,
  completion_percent INT NOT NULL DEFAULT 0,
  last_accessed DATETIME,
  CONSTRAINT fk_progress_trainee FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_progress_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainee_id INT NOT NULL,
  program_id INT NOT NULL,
  trainer_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  issued_at DATETIME NOT NULL,
  CONSTRAINT fk_certificate_trainee FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_certificate_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE CASCADE,
  CONSTRAINT fk_certificate_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ratings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trainee_id INT NOT NULL,
  trainer_id INT NOT NULL,
  program_id INT NULL,
  rating INT NOT NULL,
  review TEXT,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uniq_trainee_trainer (trainee_id, trainer_id, program_id),
  CONSTRAINT fk_rating_trainee FOREIGN KEY (trainee_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rating_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(id) ON DELETE CASCADE,
  CONSTRAINT fk_rating_program FOREIGN KEY (program_id) REFERENCES training_programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150),
  email VARCHAR(190),
  message TEXT NOT NULL,
  status ENUM('new','handled') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(100) PRIMARY KEY,
  `value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

