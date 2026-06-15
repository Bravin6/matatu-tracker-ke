CREATE TABLE IF NOT EXISTS mpesa_transactions (
    id                  INT             NOT NULL AUTO_INCREMENT,
    user_id             INT             NOT NULL,
    phone               VARCHAR(20)     NOT NULL,
    amount              DECIMAL(10,2)   NOT NULL,
    amount_paid         DECIMAL(10,2)   NULL,
    checkout_request_id VARCHAR(100)    NOT NULL UNIQUE,
    merchant_request_id VARCHAR(100)    NOT NULL,
    mpesa_code          VARCHAR(30)     NULL,
    status              ENUM('pending','complete','failed') NOT NULL DEFAULT 'pending',
    result_desc         VARCHAR(255)    NULL,
    callback_payload    JSON            NULL,
    initiated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at        DATETIME        NULL,

    PRIMARY KEY (id),
    KEY idx_user_id    (user_id),
    KEY idx_status     (status),
    KEY idx_mpesa_code (mpesa_code),

    CONSTRAINT fk_mpesa_tx_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;