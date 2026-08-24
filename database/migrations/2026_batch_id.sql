-- Link all members of a group QR self-registration into one batch.
ALTER TABLE registrations
  ADD COLUMN batch_id CHAR(36) NULL AFTER registered_by;

CREATE INDEX idx_registrations_batch ON registrations (batch_id);
