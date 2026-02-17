-- Add plan column to existing users table if it doesn't exist
-- Run this in phpMyAdmin or via mysql CLI

USE shadowpay;

-- Add plan column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS plan VARCHAR(20) DEFAULT 'free';

-- Update existing users to have 'free' plan if they don't have one
UPDATE users SET plan = 'free' WHERE plan IS NULL OR plan = '';

-- Create payments table for tracking payment history
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
