ALTER TABLE valuations
  ADD COLUMN valuator VARCHAR(191) NULL AFTER status;

UPDATE valuations
SET valuator = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(notes, '\n', 1), ':', -1))
WHERE (valuator IS NULL OR valuator = '')
  AND LOWER(TRIM(notes)) LIKE 'valuador:%';
