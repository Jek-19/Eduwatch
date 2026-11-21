-- ============================================================================
-- EduWatch  |  Database 1 of 2:  eduwatch_auth
-- Logins, password resets, user settings and login attempts.
-- (Grades live in the OTHER database, eduwatch_academic - the two are linked only by student ID.)
--
-- How to use: phpMyAdmin -> Import this file. Safe to run more than once.
-- DEMO ACCOUNTS at the bottom use well-known passwords. Change or delete them before real use.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `eduwatch_auth` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `eduwatch_auth`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`             VARCHAR(30)  NOT NULL COMMENT 'student ID (124-/224-xxxxx) or staff username',
  `email`                VARCHAR(120) NULL     COMMENT 'used for "Forgot password"',
  `password_hash`        VARCHAR(255) NOT NULL COMMENT 'bcrypt hash - passwords are never stored',
  `role`                 ENUM('student','teacher') NOT NULL,
  `full_name`            VARCHAR(120) NOT NULL,
  `must_change_password` TINYINT(1)   NOT NULL DEFAULT 0,
  `is_active`            TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at`        DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      INT UNSIGNED NOT NULL,
  `token_hash`   CHAR(64)     NOT NULL COMMENT 'SHA-256 of the emailed token (the token itself is never stored)',
  `expires_at`   DATETIME     NOT NULL,
  `used_at`      DATETIME     NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `requested_ip` VARCHAR(45)  NULL,
  PRIMARY KEY (`id`),
  KEY `idx_resets_token` (`token_hash`),
  KEY `idx_resets_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` INT UNSIGNED NOT NULL,
  `theme`   ENUM('light','dark')   NOT NULL DEFAULT 'light',
  `tone`    ENUM('formal','humor') NOT NULL DEFAULT 'formal',
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`     VARCHAR(30) NOT NULL,
  `ip`           VARCHAR(45) NULL,
  `success`      TINYINT(1)  NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_user_time` (`username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------------------------
-- DEMO ACCOUNTS
--   Staff:    admin / Admin@12345        prof.lopez / Teacher@123
--   Students: every student ID below / Student@123      (e.g. 224-00001)
-- Real teachers should each get their OWN staff account (php tools/create_teacher.php) so the
-- audit log shows who changed which grade.
-- ---------------------------------------------------------------------------------------------

INSERT IGNORE INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `full_name`, `must_change_password`, `is_active`) VALUES
(1, 'admin', 'admin@example.com', '$2y$10$5DZoSOuxsfuRW.wMdLZ0IuJNWs6wWNR78fAWLqgnZ6R.kgxo5pi2u', 'teacher', 'Demo Admin', 0, 1),
(2, 'prof.lopez', 'elena.lopez@example.com', '$2y$10$fiKXy1XGIpbzMSP13ctq6.TTygv4StNFVv.vDGHGqSeD14Mefykxi', 'teacher', 'Prof. Elena Lopez', 0, 1),
(3, '224-00001', 'mark.santos@example.com', '$2y$10$tSv.tvHML59FZPfFmoVXIesbPkwgLkiooQaGYUZBLWxogUe5hY2bO', 'student', 'Mark Anthony Santos', 0, 1),
(4, '124-00002', 'angela.reyes@example.com', '$2y$10$Jlt5da/FzQnTkXtiQTPIB.rjt57fSWxyNqPPX3I/2HuWwDT8bVaGi', 'student', 'Angela Reyes', 0, 1),
(5, '224-00003', 'johnpaul.cruz@example.com', '$2y$10$A/uRFNg4SP57OktAR2HNL.i3hBXrIemnmglEOcIu9wBMBcfqVVxyq', 'student', 'John Paul Cruz', 0, 1),
(6, '124-00004', 'mariaclara.garcia@example.com', '$2y$10$tMOGZZQF.eMjJ/mKntph2.ZxeV6/2ayB/OXpT6PGKvWwEB90CGUbq', 'student', 'Maria Clara Garcia', 0, 1),
(7, '224-00005', 'carlo.delacruz@example.com', '$2y$10$LOwwzG.Mc3nicOBI4t3CeOqOoSBk68PHIN59837fmBEaekJvfveIe', 'student', 'Carlo Dela Cruz', 0, 1),
(8, '124-00006', 'sofia.mendoza@example.com', '$2y$10$lbXE.SyIN72H6tuwQhYJ3uJxNZDIcIUxAy1rsPU6aKggw3WxkgzGW', 'student', 'Sofia Mendoza', 0, 1),
(9, '224-00007', 'miguel.ramos@example.com', '$2y$10$/vZfS9hrsEamrU1TsMrY/OvZnx5nqWI2/A1mYNNw40213JeGlzEKq', 'student', 'Miguel Ramos', 0, 1),
(10, '124-00008', 'bea.torres@example.com', '$2y$10$h.P8.Edgxdv.R84965r9p.1ugKSiK9LFI9K/nN6ZArhVtSJfVXXKG', 'student', 'Bea Torres', 0, 1),
(11, '224-00009', 'kyle.villanueva@example.com', '$2y$10$eLDZMv2.eOCADuRdHu3YJOB9Fp/jIesYLnY2zIUqphqp/p5o0iKGi', 'student', 'Kyle Villanueva', 0, 1),
(12, '124-00010', 'jasmine.navarro@example.com', '$2y$10$XNwJeE5w91ejih71/sAJreVHqWPdL7T3XoQBja5rRwVTqBGQgFLMG', 'student', 'Jasmine Navarro', 0, 1),
(13, '224-00011', 'paolo.aquino@example.com', '$2y$10$Fj7gZ/jRxheESjtKqLKqnOodLEZ53JOPe8AthS2QTTGcsn0FJLNM.', 'student', 'Paolo Aquino', 0, 1),
(14, '124-00012', 'camille.bautista@example.com', '$2y$10$JxJ5a3an.S3PKzMgNvMHe.U5n/mFn2i.2wQIBw4snCuX.zxq0EVnC', 'student', 'Camille Bautista', 0, 1);

INSERT IGNORE INTO `user_settings` (`user_id`, `theme`, `tone`) VALUES
(1, 'light', 'formal'),
(2, 'light', 'formal'),
(3, 'light', 'humor'),
(4, 'dark', 'formal'),
(5, 'light', 'formal'),
(6, 'light', 'humor'),
(7, 'light', 'formal'),
(8, 'light', 'formal'),
(9, 'light', 'formal'),
(10, 'light', 'humor'),
(11, 'light', 'formal'),
(12, 'light', 'formal'),
(13, 'light', 'formal'),
(14, 'light', 'formal');
