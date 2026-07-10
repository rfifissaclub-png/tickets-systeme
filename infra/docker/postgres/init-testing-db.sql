-- Runs once, on first container init, alongside the POSTGRES_DB database.
-- Pest's phpunit.xml points DB_DATABASE at this separate database so test
-- runs never touch local dev data.
CREATE DATABASE tms_testing OWNER tms;
