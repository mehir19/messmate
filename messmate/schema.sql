CREATE DATABASE IF NOT EXISTS messmate_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE messmate_db;

CREATE TABLE messes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_code VARCHAR(12) UNIQUE NOT NULL, -- e.g., MESS-4821 (used by members to login)
    mess_name VARCHAR(120) NOT NULL,
    currency_symbol VARCHAR(10) DEFAULT '৳',
    subscription_plan ENUM('free_trial', 'starter', 'pro') DEFAULT 'free_trial',
    subscription_status ENUM('active', 'expired') DEFAULT 'active',
    trial_ends_at DATETIME NULL,
    subscription_ends_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    member_code VARCHAR(20) NOT NULL, -- e.g., M-101
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(120) UNIQUE NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('manager', 'member') DEFAULT 'member',
    previous_balance DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_per_mess (mess_id, member_code)
);

CREATE TABLE deposits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL, -- Positive = deposit, +/- = correction adjustment
    entry_type ENUM('deposit', 'correction') DEFAULT 'deposit',
    note VARCHAR(255) NOT NULL,
    recorded_by INT NOT NULL,
    billing_month VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    paid_by_user_id INT NOT NULL,
    category ENUM('grocery', 'misc') NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    billing_month VARCHAR(7) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE,
    FOREIGN KEY (paid_by_user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE meals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    user_id INT NOT NULL,
    meal_date DATE NOT NULL,
    breakfast DECIMAL(3,1) DEFAULT 0.0,
    lunch DECIMAL(3,1) DEFAULT 0.0,
    dinner DECIMAL(3,1) DEFAULT 0.0,
    UNIQUE KEY unique_daily_meal (mess_id, user_id, meal_date),
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE shopping_roster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    assigned_user_id INT NOT NULL,
    duty_date DATE NOT NULL,
    items_note VARCHAR(255),
    status ENUM('upcoming', 'completed') DEFAULT 'upcoming',
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE
);

CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mess_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    request_type ENUM('chat', 'meal_request') DEFAULT 'chat',
    requested_meals DECIMAL(3,1) NULL,
    target_date DATE NULL,
    is_processed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mess_id) REFERENCES messes(id) ON DELETE CASCADE
);