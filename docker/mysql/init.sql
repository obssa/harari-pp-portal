CREATE DATABASE IF NOT EXISTS harari_test CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON harari_test.* TO 'harari'@'%';
FLUSH PRIVILEGES;
-- ADR-0037: the restore drill tests restore backups into this second database.
CREATE DATABASE IF NOT EXISTS harari_drill CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
GRANT ALL PRIVILEGES ON harari_drill.* TO 'harari'@'%';
FLUSH PRIVILEGES;
