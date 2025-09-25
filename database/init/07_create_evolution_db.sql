-- Create Evolution API database
-- This script creates the evolution_db database for the Evolution API service

-- Create the evolution_db database
CREATE DATABASE evolution_db;

-- Connect to the evolution_db database
\c evolution_db;

-- Create basic tables that Evolution API might need
-- (Evolution API will create its own tables, but we ensure the database exists)

-- Note: Evolution API will automatically create its own schema and tables
-- when it starts up and connects to this database
