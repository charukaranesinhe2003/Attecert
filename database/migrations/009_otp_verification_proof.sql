ALTER TABLE users
    ADD COLUMN otp_verified_at DATETIME NULL,
    ADD COLUMN otp_verified_channel VARCHAR(10) NULL,
    ADD COLUMN otp_verified_destination VARCHAR(190) NULL;
