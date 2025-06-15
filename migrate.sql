ALTER TABLE qlyx_analytics
ADD COLUMN session_id VARCHAR(32) AFTER visitor_type,
ADD COLUMN page_count INT DEFAULT 1 AFTER session_id,
ADD COLUMN last_activity TIMESTAMP AFTER page_count,
ADD INDEX idx_session (session_id),
ADD INDEX idx_created_at (created_at),
ADD INDEX idx_visitor_type (visitor_type),
ADD INDEX idx_country (user_country),
ADD INDEX idx_device (user_device_type),
ADD INDEX idx_browser (browser_name);
