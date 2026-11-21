-- ============================================================================
-- EduWatch  |  OPTIONAL: convert an EXISTING eduwatch_academic database to the 1-5 grading scale
-- 1.00 = highest, 5.00 = lowest, 3.00 = passing.
--
-- Use this ONLY if your database already holds grades on the old 0-100 scale and you do not want to
-- drop it and re-import 02_eduwatch_academic.sql. A brand-new install does not need this file.
--
-- How to use: BACK UP first (phpMyAdmin -> Export), then phpMyAdmin -> Import this file.
-- Safe to run more than once: only values ABOVE 5 (the old scale) are converted.
--
-- Conversion table (old score -> new grade):
--   97-100 -> 1.00    94-96 -> 1.25    91-93 -> 1.50    88-90 -> 1.75    85-87 -> 2.00
--   82-84  -> 2.25    79-81 -> 2.50    76-78 -> 2.75    75    -> 3.00    below 75 -> 5.00
-- ============================================================================

USE `eduwatch_academic`;

-- 1) Convert the grades themselves (this also fires the table's "updated_at" timestamp).
UPDATE `grades`
   SET `score` = CASE
        WHEN `score` >= 97 THEN 1.00
        WHEN `score` >= 94 THEN 1.25
        WHEN `score` >= 91 THEN 1.50
        WHEN `score` >= 88 THEN 1.75
        WHEN `score` >= 85 THEN 2.00
        WHEN `score` >= 82 THEN 2.25
        WHEN `score` >= 79 THEN 2.50
        WHEN `score` >= 76 THEN 2.75
        WHEN `score` >= 75 THEN 3.00
        ELSE 5.00
   END
 WHERE `score` > 5;

-- 2) Convert the old/new values kept in the audit log so the history stays readable.
UPDATE `grade_audit_log`
   SET `old_score` = CASE
        WHEN `old_score` IS NULL OR `old_score` <= 5 THEN `old_score`
        WHEN `old_score` >= 97 THEN 1.00 WHEN `old_score` >= 94 THEN 1.25 WHEN `old_score` >= 91 THEN 1.50
        WHEN `old_score` >= 88 THEN 1.75 WHEN `old_score` >= 85 THEN 2.00 WHEN `old_score` >= 82 THEN 2.25
        WHEN `old_score` >= 79 THEN 2.50 WHEN `old_score` >= 76 THEN 2.75 WHEN `old_score` >= 75 THEN 3.00
        ELSE 5.00 END,
       `new_score` = CASE
        WHEN `new_score` IS NULL OR `new_score` <= 5 THEN `new_score`
        WHEN `new_score` >= 97 THEN 1.00 WHEN `new_score` >= 94 THEN 1.25 WHEN `new_score` >= 91 THEN 1.50
        WHEN `new_score` >= 88 THEN 1.75 WHEN `new_score` >= 85 THEN 2.00 WHEN `new_score` >= 82 THEN 2.25
        WHEN `new_score` >= 79 THEN 2.50 WHEN `new_score` >= 76 THEN 2.75 WHEN `new_score` >= 75 THEN 3.00
        ELSE 5.00 END
 WHERE `old_score` > 5 OR `new_score` > 5;

-- 3) Replace the old 0-100 check with a 1-5 check (use DROP CHECK instead of DROP CONSTRAINT on MySQL 8.0.16-8.0.18).
ALTER TABLE `grades` DROP CONSTRAINT `chk_grades_score`;
ALTER TABLE `grades` ADD CONSTRAINT `chk_grades_score` CHECK (`score` >= 1 AND `score` <= 5);
ALTER TABLE `grades` MODIFY `score` DECIMAL(5,2) NOT NULL COMMENT '1.00 (highest) - 5.00 (lowest)';

-- 4) Forget the old saved risk results (they were worked out on the 0-100 scale).
--    EduWatch recalculates them automatically the next time a dashboard opens (or press "Recalculate all risk").
DELETE FROM `risk_results`;
