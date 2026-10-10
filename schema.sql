CREATE TABLE IF NOT EXISTS batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status ENUM('CREATED','RUNNING','STOPPED','COMPLETED') NOT NULL DEFAULT 'CREATED',
    interval_seconds INT NOT NULL,
    next_run_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    seq INT NOT NULL,
    value VARCHAR(1000) NOT NULL,
    status ENUM('PENDING','PROCESSING','SUCCESS','FAILED') NOT NULL DEFAULT 'PENDING',
    processed_at DATETIME NULL,
    http_status INT NULL,
    response TEXT NULL,
    error TEXT NULL,
    FOREIGN KEY (batch_id) REFERENCES batches(id),
    INDEX idx_batch_status_seq (batch_id, status, seq)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;