-- Add balance column to existing cards table if it doesn't exist
-- Run this in phpMyAdmin or via mysql CLI

USE shadowpay;

-- Add balance column if it doesn't exist
ALTER TABLE cards ADD COLUMN IF NOT EXISTS balance DECIMAL(10,2) DEFAULT 0.00;

-- Update existing cards to have 0 balance if they don't have one
UPDATE cards SET balance = 0.00 WHERE balance IS NULL;
