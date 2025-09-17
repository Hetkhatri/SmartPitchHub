-- Add views and likes columns to pitches table
ALTER TABLE pitches ADD views INT DEFAULT 0;
ALTER TABLE pitches ADD likes INT DEFAULT 0;

-- Update existing records to have default values
UPDATE pitches SET views = 0, likes = 0 WHERE views IS NULL OR likes IS NULL;
