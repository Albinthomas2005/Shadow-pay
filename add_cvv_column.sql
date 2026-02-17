-- Add CVV column to cards table
ALTER TABLE cards ADD COLUMN cvv VARCHAR(3) DEFAULT NULL AFTER card_number;

-- Optional: Add comment to the column
ALTER TABLE cards MODIFY COLUMN cvv VARCHAR(3) DEFAULT NULL COMMENT 'Card Verification Value (3 digits)';

