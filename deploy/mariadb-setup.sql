-- Run as a MariaDB administrator. Replace the password before executing.
CREATE DATABASE IF NOT EXISTS pixiepoint
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'pixiepoint'@'%' IDENTIFIED BY 'replace-with-a-strong-database-password';
ALTER USER 'pixiepoint'@'%' IDENTIFIED BY 'replace-with-a-strong-database-password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES
  ON pixiepoint.* TO 'pixiepoint'@'%';
FLUSH PRIVILEGES;

