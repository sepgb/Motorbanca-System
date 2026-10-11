CREATE DATABASE IF NOT EXISTS motorbanca_db CHARACTER SET utf8mb4;
USE motorbanca_db;
CREATE TABLE clusters (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL UNIQUE,
  next_trip_available TINYINT(1) NOT NULL DEFAULT 1,
  next_motorbanca_id INT NULL
) ENGINE = InnoDB;
CREATE TABLE motorbancas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  motorbanca_name VARCHAR(50) NOT NULL UNIQUE,
  owner_name VARCHAR(100) NOT NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  cluster_id INT NOT NULL,
  status ENUM(
    'Not Queued',
    'Queued',
    'Boarding',
    'Departed',
    'Arrived',
    'Completed'
  ) NOT NULL DEFAULT 'Not Queued',
  queued_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cluster_id) REFERENCES clusters(id)
) ENGINE = InnoDB;
-- password = hashed. temp_password = readable copy so the PCG can view/give credentials.
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  temp_password VARCHAR(60) NULL,
  role ENUM('admin', 'operator') NOT NULL,
  motorbanca_id INT NULL UNIQUE,
  FOREIGN KEY (motorbanca_id) REFERENCES motorbancas(id) ON DELETE CASCADE
) ENGINE = InnoDB;
CREATE TABLE trips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  motorbanca_id INT NOT NULL,
  cluster_id INT NOT NULL,
  status ENUM(
    'Queued',
    'Boarding',
    'Departed',
    'Arrived',
    'Completed',
    'Cancelled'
  ) NOT NULL DEFAULT 'Queued',
  trip_date DATE NOT NULL,
  start_time DATETIME NULL,
  departure_time DATETIME NULL,
  arrival_time DATETIME NULL,
  FOREIGN KEY (motorbanca_id) REFERENCES motorbancas(id) ON DELETE CASCADE,
  FOREIGN KEY (cluster_id) REFERENCES clusters(id)
) ENGINE = InnoDB;
CREATE TABLE passengers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  trip_id INT NOT NULL,
  name VARCHAR(80) NOT NULL,
  age TINYINT UNSIGNED NOT NULL,
  barangay VARCHAR(60) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE = InnoDB;
CREATE TABLE barangays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cluster_id INT NOT NULL,
  name VARCHAR(60) NOT NULL,
  FOREIGN KEY (cluster_id) REFERENCES clusters(id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- Clusters, barangays and motorbancas are loaded by database/load_list.sql
INSERT INTO clusters (name)
VALUES ('Cluster 1'),
('Cluster 2'),
('Cluster 3'),
('Cluster 4'),
('Cluster 5');
-- The first admin (pcg_admin / admin123) is created automatically on first visit to login.php
-- Crew list filled in by the operator before a boat can join the queue (also auto-created by the app).
CREATE TABLE IF NOT EXISTS trip_crew (
  trip_id INT PRIMARY KEY,
  address VARCHAR(160) NOT NULL,
  contact VARCHAR(30) NOT NULL,
  crew TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE = InnoDB;