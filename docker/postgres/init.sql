-- Crea la base de datos dedicada para tests
-- Este script se ejecuta automáticamente al inicializar el contenedor
CREATE DATABASE laravel_api_test;
GRANT ALL PRIVILEGES ON DATABASE laravel_api_test TO laravel;
