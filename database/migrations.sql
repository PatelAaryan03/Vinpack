-- Add sync tracking fields to inquiries table (if they don't exist)
-- This allows tracking which inquiries came from offline mode or different sources

ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS source VARCHAR(50) DEFAULT 'web3forms';
ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS is_synced BOOLEAN DEFAULT 1;
ALTER TABLE inquiries ADD COLUMN IF NOT EXISTS sync_status VARCHAR(50) DEFAULT 'synced';

-- source can be: 'web3forms', 'offline', 'direct'
-- is_synced: 1 = synced to database, 0 = pending sync
-- sync_status: 'synced', 'pending', 'failed'
