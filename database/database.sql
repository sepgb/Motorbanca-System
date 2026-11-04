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
  address VARCHAR(160) NULL,
  contact VARCHAR(30) NULL,
  capacity SMALLINT UNSIGNED NOT NULL,
  cluster_id INT NOT NULL,
  status ENUM(
    'Not Queued',
    'Inspection',
    'Queued',
    'Boarding',
    'Departed',
    'Arrived',
    'Completed'
  ) NOT NULL DEFAULT 'Not Queued',
  queued_at DATETIME NULL,
  depart_req DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cluster_id) REFERENCES clusters(id)
) ENGINE = InnoDB;
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
    'Inspection',
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
INSERT INTO clusters (name)
VALUES ('Cluster 1A'),
  ('Cluster 1B'),
  ('Cluster 2A'),
  ('Cluster 2B'),
  ('Cluster 3'),
  ('Cluster 4'),
  ('Cluster 5'),
  ('Cluster 6'),
  ('Cluster 7');
CREATE TABLE IF NOT EXISTS trip_crew (
  trip_id INT PRIMARY KEY,
  address VARCHAR(160) NOT NULL,
  contact VARCHAR(30) NOT NULL,
  crew TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE = InnoDB;