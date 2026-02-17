-- db.sql: create database and users table for ShadowPay sample app
-- Run in phpMyAdmin or via mysql CLI: CREATE DATABASE shadowpay; USE shadowpay; then run the CREATE TABLE below.

CREATE DATABASE IF NOT EXISTS shadowpay CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shadowpay;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  plan VARCHAR(20) DEFAULT 'free',
  created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  card_name VARCHAR(100) NOT NULL,
  card_number VARCHAR(19) NOT NULL,
  cvv VARCHAR(3) DEFAULT NULL,
  card_color VARCHAR(7) NOT NULL DEFAULT '#8d8d8d',
  balance DECIMAL(10,2) DEFAULT 0.00,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS merchant_locks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  card_id INT NOT NULL,
  merchant_name VARCHAR(100) NOT NULL,
  merchant_category VARCHAR(50) NOT NULL,
  is_locked BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  card_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  merchant_name VARCHAR(100) NOT NULL,
  transaction_type ENUM('debit', 'credit') NOT NULL,
  description TEXT,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plan VARCHAR(20) NOT NULL,
  transaction_id VARCHAR(100) NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  created_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
