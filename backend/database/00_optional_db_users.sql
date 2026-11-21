-- ============================================================================
-- OPTIONAL: give each database its own MySQL user (least privilege).
-- Then put these users/passwords in config.php (db_auth / db_academic).
-- Run AFTER importing 01_eduwatch_auth.sql and 02_eduwatch_academic.sql, as a MySQL admin (e.g. root).
-- Change both passwords below first!
--
-- Why: if one part of the app is ever hacked, the attacker only reaches ONE database.
-- The grades user cannot read password hashes, and the login user cannot read or change grades.
-- ============================================================================

CREATE USER IF NOT EXISTS 'eduwatch_auth'@'localhost'     IDENTIFIED BY 'CHANGE_ME_auth_password';
CREATE USER IF NOT EXISTS 'eduwatch_auth'@'127.0.0.1'     IDENTIFIED BY 'CHANGE_ME_auth_password';
CREATE USER IF NOT EXISTS 'eduwatch_acad'@'localhost'     IDENTIFIED BY 'CHANGE_ME_academic_password';
CREATE USER IF NOT EXISTS 'eduwatch_acad'@'127.0.0.1'     IDENTIFIED BY 'CHANGE_ME_academic_password';

GRANT SELECT, INSERT, UPDATE, DELETE ON `eduwatch_auth`.*     TO 'eduwatch_auth'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `eduwatch_auth`.*     TO 'eduwatch_auth'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON `eduwatch_academic`.* TO 'eduwatch_acad'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `eduwatch_academic`.* TO 'eduwatch_acad'@'127.0.0.1';
FLUSH PRIVILEGES;
