CREATE DATABASE IF NOT EXISTS gym_management
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE gym_management;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS expense_categories;
DROP TABLE IF EXISTS stock_transactions;
DROP TABLE IF EXISTS inventory;
DROP TABLE IF EXISTS inventory_categories;
DROP TABLE IF EXISTS suppliers;
DROP TABLE IF EXISTS equipment_maintenance;
DROP TABLE IF EXISTS equipment;
DROP TABLE IF EXISTS equipment_categories;
DROP TABLE IF EXISTS class_bookings;
DROP TABLE IF EXISTS class_schedules;
DROP TABLE IF EXISTS gym_classes;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS workout_progress;
DROP TABLE IF EXISTS member_workout_plans;
DROP TABLE IF EXISTS workout_exercises;
DROP TABLE IF EXISTS workout_plans;
DROP TABLE IF EXISTS fitness_measurements;
DROP TABLE IF EXISTS fitness_goals;
DROP TABLE IF EXISTS trainer_members;
DROP TABLE IF EXISTS trainers;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS payment_transactions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS membership_plans;
DROP TABLE IF EXISTS members;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS notification_templates;
DROP TABLE IF EXISTS subscription_payment_logs;
DROP TABLE IF EXISTS subscription_payments;
DROP TABLE IF EXISTS gym_subscriptions;
DROP TABLE IF EXISTS subscription_plans;
DROP TABLE IF EXISTS gyms;
DROP TABLE IF EXISTS system_settings;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN','MEMBER','COACH','PLATFORM') NOT NULL,
    status ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role),
    INDEX idx_users_status (status)
) ENGINE=InnoDB;

CREATE TABLE admins (
    admin_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    profile_image VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admin_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_admin_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE members (
    member_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    member_code VARCHAR(30) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    nic VARCHAR(20) NOT NULL UNIQUE,
    date_of_birth DATE NULL,
    gender ENUM('MALE','FEMALE','OTHER') NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL,
    address TEXT NULL,
    emergency_contact_name VARCHAR(150) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    profile_image VARCHAR(255) NULL,
    registration_date DATE NOT NULL,
    status ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_member_name (full_name),
    INDEX idx_member_nic (nic),
    INDEX idx_member_phone (phone),
    INDEX idx_member_status (status)
) ENGINE=InnoDB;

CREATE TABLE memberships (
    membership_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    final_amount DECIMAL(12,2) NOT NULL,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('PENDING','ACTIVE','EXPIRED','FROZEN','CANCELLED') NOT NULL DEFAULT 'PENDING',
    freeze_start DATE NULL,
    freeze_end DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_membership_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT fk_membership_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_membership_dates CHECK (end_date >= start_date),
    CONSTRAINT chk_membership_amount CHECK (amount >= 0 AND discount >= 0 AND final_amount >= 0 AND paid_amount >= 0 AND balance >= 0),
    INDEX idx_membership_member (member_id),
    INDEX idx_membership_status (status),
    INDEX idx_membership_dates (start_date, end_date)
) ENGINE=InnoDB;

CREATE TABLE payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    membership_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('CASH','CARD','BANK_TRANSFER','ONLINE') NOT NULL,
    payment_status ENUM('PENDING','SUCCESS','FAILED','REFUNDED','CANCELLED') NOT NULL DEFAULT 'PENDING',
    transaction_reference VARCHAR(150) NULL UNIQUE,
    paid_by ENUM('MEMBER','ADMIN') NOT NULL,
    payment_date DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE RESTRICT,
    CONSTRAINT fk_payment_membership FOREIGN KEY (membership_id) REFERENCES memberships(membership_id) ON DELETE SET NULL,
    CONSTRAINT chk_payment_amount CHECK (amount > 0),
    INDEX idx_payment_member (member_id),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_date (payment_date)
) ENGINE=InnoDB;

CREATE TABLE payment_transactions (
    transaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED NOT NULL,
    gateway_name VARCHAR(100) NULL,
    gateway_transaction_id VARCHAR(150) NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('INITIATED','PROCESSING','SUCCESS','FAILED','CANCELLED') NOT NULL DEFAULT 'INITIATED',
    response_code VARCHAR(100) NULL,
    response_message TEXT NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaction_payment FOREIGN KEY (payment_id) REFERENCES payments(payment_id) ON DELETE CASCADE,
    CONSTRAINT chk_transaction_amount CHECK (amount > 0),
    INDEX idx_gateway_transaction (gateway_transaction_id),
    INDEX idx_transaction_status (status)
) ENGINE=InnoDB;

CREATE TABLE attendance (
    attendance_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    attendance_date DATE NOT NULL,
    check_in DATETIME NOT NULL,
    check_out DATETIME NULL,
    duration_minutes INT UNSIGNED NULL,
    attendance_method ENUM('QR','MEMBER_CODE','ADMIN') NOT NULL DEFAULT 'QR',
    checked_in_by INT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_admin FOREIGN KEY (checked_in_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_attendance_duration CHECK (duration_minutes IS NULL OR duration_minutes >= 0),
    INDEX idx_attendance_member (member_id),
    INDEX idx_attendance_date (attendance_date),
    INDEX idx_attendance_checkin (check_in)
) ENGINE=InnoDB;

CREATE TABLE trainers (
    trainer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    specialization VARCHAR(150) NULL,
    experience_years DECIMAL(4,1) DEFAULT 0,
    certification TEXT NULL,
    profile_image VARCHAR(255) NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_trainer_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_trainer_name (full_name),
    INDEX idx_trainer_status (status)
) ENGINE=InnoDB;

CREATE TABLE trainer_members (
    assignment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('ACTIVE','ENDED') NOT NULL DEFAULT 'ACTIVE',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tm_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE CASCADE,
    CONSTRAINT fk_tm_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT chk_tm_dates CHECK (end_date IS NULL OR end_date >= assigned_date),
    INDEX idx_tm_trainer (trainer_id),
    INDEX idx_tm_member (member_id),
    INDEX idx_tm_status (status)
) ENGINE=InnoDB;

CREATE TABLE fitness_goals (
    goal_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    goal_type ENUM('WEIGHT_LOSS','MUSCLE_GAIN','STRENGTH','ENDURANCE','FLEXIBILITY','GENERAL_FITNESS') NOT NULL,
    description TEXT NULL,
    target_value DECIMAL(10,2) NULL,
    target_unit VARCHAR(30) NULL,
    start_date DATE NOT NULL,
    target_date DATE NULL,
    status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_goal_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_goal_member (member_id),
    INDEX idx_goal_status (status)
) ENGINE=InnoDB;

CREATE TABLE fitness_measurements (
    measurement_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    weight_kg DECIMAL(6,2) NULL,
    height_cm DECIMAL(6,2) NULL,
    chest_cm DECIMAL(6,2) NULL,
    waist_cm DECIMAL(6,2) NULL,
    hip_cm DECIMAL(6,2) NULL,
    arm_cm DECIMAL(6,2) NULL,
    leg_cm DECIMAL(6,2) NULL,
    body_fat_percentage DECIMAL(5,2) NULL,
    measurement_date DATE NOT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_measurement_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_measurement_member (member_id),
    INDEX idx_measurement_date (measurement_date)
) ENGINE=InnoDB;

CREATE TABLE workout_plans (
    workout_plan_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trainer_id INT UNSIGNED NOT NULL,
    plan_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    goal_type ENUM('WEIGHT_LOSS','MUSCLE_GAIN','STRENGTH','ENDURANCE','FLEXIBILITY','GENERAL_FITNESS') NOT NULL,
    difficulty ENUM('BEGINNER','INTERMEDIATE','ADVANCED') NOT NULL DEFAULT 'BEGINNER',
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_workout_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE RESTRICT,
    INDEX idx_workout_trainer (trainer_id),
    INDEX idx_workout_status (status)
) ENGINE=InnoDB;

CREATE TABLE workout_exercises (
    exercise_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workout_plan_id INT UNSIGNED NOT NULL,
    exercise_name VARCHAR(150) NOT NULL,
    workout_day ENUM('MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY','SUNDAY') NOT NULL,
    sets INT UNSIGNED NULL,
    repetitions INT UNSIGNED NULL,
    target_weight_kg DECIMAL(7,2) NULL,
    rest_seconds INT UNSIGNED NULL,
    instructions TEXT NULL,
    exercise_order INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exercise_plan FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(workout_plan_id) ON DELETE CASCADE,
    INDEX idx_exercise_plan (workout_plan_id),
    INDEX idx_exercise_day (workout_day)
) ENGINE=InnoDB;

CREATE TABLE member_workout_plans (
    member_workout_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    workout_plan_id INT UNSIGNED NOT NULL,
    assigned_date DATE NOT NULL,
    end_date DATE NULL,
    status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
    assigned_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mwp_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT fk_mwp_plan FOREIGN KEY (workout_plan_id) REFERENCES workout_plans(workout_plan_id) ON DELETE CASCADE,
    CONSTRAINT fk_mwp_admin FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_mwp_member (member_id),
    INDEX idx_mwp_plan (workout_plan_id)
) ENGINE=InnoDB;

CREATE TABLE workout_progress (
    progress_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    exercise_id INT UNSIGNED NOT NULL,
    progress_date DATE NOT NULL,
    completed_sets INT UNSIGNED DEFAULT 0,
    completed_repetitions INT UNSIGNED DEFAULT 0,
    weight_used_kg DECIMAL(7,2) NULL,
    duration_minutes INT UNSIGNED NULL,
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_progress_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_exercise FOREIGN KEY (exercise_id) REFERENCES workout_exercises(exercise_id) ON DELETE CASCADE,
    CONSTRAINT chk_progress_percentage CHECK (completion_percentage >= 0 AND completion_percentage <= 100),
    INDEX idx_progress_member (member_id),
    INDEX idx_progress_date (progress_date)
) ENGINE=InnoDB;

CREATE TABLE gym_classes (
    class_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    capacity INT UNSIGNED NOT NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_class_capacity CHECK (capacity > 0)
) ENGINE=InnoDB;

CREATE TABLE class_schedules (
    schedule_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    trainer_id INT UNSIGNED NOT NULL,
    class_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(100) NULL,
    status ENUM('SCHEDULED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_schedule_class FOREIGN KEY (class_id) REFERENCES gym_classes(class_id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE RESTRICT,
    CONSTRAINT chk_schedule_time CHECK (end_time > start_time),
    INDEX idx_schedule_date (class_date),
    INDEX idx_schedule_trainer (trainer_id)
) ENGINE=InnoDB;

CREATE TABLE class_bookings (
    booking_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NOT NULL,
    booking_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('BOOKED','ATTENDED','CANCELLED','NO_SHOW','WAITING') NOT NULL DEFAULT 'BOOKED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_booking_schedule FOREIGN KEY (schedule_id) REFERENCES class_schedules(schedule_id) ON DELETE CASCADE,
    CONSTRAINT fk_booking_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    UNIQUE KEY unique_member_schedule (schedule_id, member_id),
    INDEX idx_booking_member (member_id),
    INDEX idx_booking_status (status)
) ENGINE=InnoDB;

CREATE TABLE appointments (
    appointment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    trainer_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    appointment_type ENUM('PERSONAL_TRAINING','FITNESS_CONSULTATION','PROGRESS_REVIEW') NOT NULL,
    status ENUM('BOOKED','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'BOOKED',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_appointment_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE RESTRICT,
    CONSTRAINT chk_appointment_time CHECK (end_time > start_time),
    INDEX idx_appointment_member (member_id),
    INDEX idx_appointment_trainer (trainer_id),
    INDEX idx_appointment_date (appointment_date)
) ENGINE=InnoDB;

CREATE TABLE equipment_categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE equipment (
    equipment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    equipment_code VARCHAR(50) NOT NULL UNIQUE,
    equipment_name VARCHAR(150) NOT NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    serial_number VARCHAR(150) NULL UNIQUE,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    purchase_date DATE NULL,
    purchase_price DECIMAL(12,2) NULL,
    warranty_expiry DATE NULL,
    location VARCHAR(100) NULL,
    condition_status ENUM('EXCELLENT','GOOD','FAIR','POOR','CRITICAL') NOT NULL DEFAULT 'GOOD',
    status ENUM('AVAILABLE','IN_USE','DAMAGED','UNDER_MAINTENANCE','RETIRED','LOST') NOT NULL DEFAULT 'AVAILABLE',
    health_score DECIMAL(5,2) DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_equipment_category FOREIGN KEY (category_id) REFERENCES equipment_categories(category_id) ON DELETE RESTRICT,
    CONSTRAINT chk_equipment_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_equipment_health CHECK (health_score >= 0 AND health_score <= 100),
    INDEX idx_equipment_status (status),
    INDEX idx_equipment_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE equipment_maintenance (
    maintenance_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT UNSIGNED NOT NULL,
    maintenance_date DATE NOT NULL,
    problem TEXT NULL,
    description TEXT NULL,
    technician_name VARCHAR(150) NULL,
    cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    next_maintenance_date DATE NULL,
    status ENUM('SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED') NOT NULL DEFAULT 'SCHEDULED',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_equipment FOREIGN KEY (equipment_id) REFERENCES equipment(equipment_id) ON DELETE CASCADE,
    CONSTRAINT fk_maintenance_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_maintenance_cost CHECK (cost >= 0),
    INDEX idx_maintenance_equipment (equipment_id),
    INDEX idx_maintenance_date (maintenance_date),
    INDEX idx_maintenance_status (status)
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    supplier_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    company_name VARCHAR(150) NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    address TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (supplier_name)
) ENGINE=InnoDB;

CREATE TABLE inventory_categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE inventory (
    inventory_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    item_code VARCHAR(50) NOT NULL UNIQUE,
    item_name VARCHAR(150) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit VARCHAR(30) DEFAULT 'UNIT',
    status ENUM('AVAILABLE','LOW_STOCK','OUT_OF_STOCK','INACTIVE') DEFAULT 'AVAILABLE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inventory_category FOREIGN KEY (category_id) REFERENCES inventory_categories(category_id) ON DELETE RESTRICT,
    CONSTRAINT fk_inventory_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id) ON DELETE SET NULL,
    CONSTRAINT chk_inventory_quantity CHECK (quantity >= 0),
    CONSTRAINT chk_inventory_minimum CHECK (minimum_stock >= 0),
    CONSTRAINT chk_inventory_price CHECK (unit_price >= 0),
    INDEX idx_inventory_status (status),
    INDEX idx_inventory_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE stock_transactions (
    transaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT UNSIGNED NOT NULL,
    transaction_type ENUM('STOCK_IN','STOCK_OUT','ADJUSTMENT') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NULL,
    reference_number VARCHAR(100) NULL,
    transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    CONSTRAINT fk_stock_inventory FOREIGN KEY (inventory_id) REFERENCES inventory(inventory_id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_stock_quantity CHECK (quantity > 0),
    INDEX idx_stock_inventory (inventory_id),
    INDEX idx_stock_type (transaction_type),
    INDEX idx_stock_date (transaction_date)
) ENGINE=InnoDB;

CREATE TABLE expense_categories (
    category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE expenses (
    expense_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    expense_date DATE NOT NULL,
    payment_method ENUM('CASH','CARD','BANK_TRANSFER','ONLINE') NOT NULL DEFAULT 'CASH',
    reference_number VARCHAR(100) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES expense_categories(category_id) ON DELETE RESTRICT,
    CONSTRAINT fk_expense_user FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT chk_expense_amount CHECK (amount > 0),
    INDEX idx_expense_date (expense_date),
    INDEX idx_expense_category (category_id)
) ENGINE=InnoDB;

CREATE TABLE feedback (
    feedback_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NULL,
    subject VARCHAR(200) NULL,
    message TEXT NOT NULL,
    status ENUM('NEW','IN_REVIEW','RESOLVED','CLOSED') DEFAULT 'NEW',
    admin_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    CONSTRAINT fk_feedback_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    CONSTRAINT chk_feedback_rating CHECK (rating IS NULL OR (rating BETWEEN 1 AND 5)),
    INDEX idx_feedback_member (member_id),
    INDEX idx_feedback_status (status)
) ENGINE=InnoDB;

CREATE TABLE complaints (
    complaint_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    priority ENUM('LOW','MEDIUM','HIGH','URGENT') DEFAULT 'MEDIUM',
    status ENUM('NEW','IN_PROGRESS','RESOLVED','CLOSED') DEFAULT 'NEW',
    admin_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    CONSTRAINT fk_complaint_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
    INDEX idx_complaint_member (member_id),
    INDEX idx_complaint_status (status),
    INDEX idx_complaint_priority (priority)
) ENGINE=InnoDB;

CREATE TABLE notification_templates (
    template_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(100) NOT NULL UNIQUE,
    template_name VARCHAR(150) NOT NULL,
    message_template TEXT NOT NULL,
    channel ENUM('SMS','SYSTEM','EMAIL') NOT NULL DEFAULT 'SMS',
    recipient_type ENUM('MEMBER','ADMIN','BOTH') NOT NULL,
    status ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    member_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    recipient_type ENUM('MEMBER','ADMIN') NOT NULL,
    recipient_name VARCHAR(150) NULL,
    phone_number VARCHAR(20) NOT NULL,
    notification_type VARCHAR(100) NOT NULL,
    title VARCHAR(200) NULL,
    message TEXT NOT NULL,
    channel ENUM('SMS','SYSTEM','EMAIL') NOT NULL DEFAULT 'SMS',
    delivery_status ENUM('PENDING','SENT','FAILED','READ') NOT NULL DEFAULT 'PENDING',
    provider_name VARCHAR(100) NULL,
    provider_message_id VARCHAR(150) NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    retry_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_admin FOREIGN KEY (admin_id) REFERENCES admins(admin_id) ON DELETE SET NULL,
    INDEX idx_notification_member (member_id),
    INDEX idx_notification_admin (admin_id),
    INDEX idx_notification_status (delivery_status),
    INDEX idx_notification_type (notification_type),
    INDEX idx_notification_phone (phone_number),
    INDEX idx_notification_scheduled (scheduled_at)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    log_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(100) NULL,
    description TEXT NULL,
    record_id VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_module (module),
    INDEX idx_audit_date (created_at)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
    setting_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    description TEXT NULL,
    updated_by INT UNSIGNED NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_setting_user FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE gyms (
    gym_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_name VARCHAR(150) NOT NULL,
    status ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE subscription_plans (
    plan_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_code VARCHAR(40) NOT NULL UNIQUE,
    plan_name VARCHAR(100) NOT NULL,
    monthly_price DECIMAL(12,2) NOT NULL,
    duration_days INT UNSIGNED NOT NULL DEFAULT 30,
    features_json TEXT NOT NULL,
    member_limit INT UNSIGNED NULL,
    coach_limit INT UNSIGNED NULL,
    notification_limit INT UNSIGNED NULL,
    status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE gym_subscriptions (
    subscription_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    start_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    status ENUM('ACTIVE','EXPIRING_SOON','EXPIRED','PAYMENT_PENDING','PAYMENT_REVIEW','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
    last_payment_id INT UNSIGNED NULL,
    next_payment_due_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_gs_gym FOREIGN KEY (gym_id) REFERENCES gyms(gym_id) ON DELETE CASCADE,
    CONSTRAINT fk_gs_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id),
    INDEX idx_gs_gym (gym_id),
    INDEX idx_gs_status (status),
    INDEX idx_gs_expiry (expiry_date)
) ENGINE=InnoDB;

CREATE TABLE subscription_payments (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gym_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(10) NOT NULL DEFAULT 'LKR',
    payment_reference VARCHAR(80) NULL,
    payment_date DATE NULL,
    receipt_path VARCHAR(255) NOT NULL,
    receipt_hash CHAR(64) NOT NULL,
    receipt_mime VARCHAR(80) NULL,
    original_filename VARCHAR(255) NULL,
    verification_result ENUM('PENDING','VERIFIED','REVIEW_REQUIRED','REJECTED') NOT NULL DEFAULT 'PENDING',
    payment_status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
    ocr_json LONGTEXT NULL,
    extracted_amount DECIMAL(12,2) NULL,
    extracted_date DATE NULL,
    extracted_reference VARCHAR(80) NULL,
    extracted_bank VARCHAR(150) NULL,
    extracted_sender VARCHAR(150) NULL,
    extracted_recipient VARCHAR(150) NULL,
    verification_notes TEXT NULL,
    confidence_score INT NULL,
    submitted_by INT UNSIGNED NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sp_gym FOREIGN KEY (gym_id) REFERENCES gyms(gym_id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_sub FOREIGN KEY (subscription_id) REFERENCES gym_subscriptions(subscription_id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(plan_id),
    CONSTRAINT fk_sp_submit FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
    CONSTRAINT fk_sp_review FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_sp_gym (gym_id),
    INDEX idx_sp_status (payment_status),
    INDEX idx_sp_hash (receipt_hash),
    INDEX idx_sp_ref (payment_reference)
) ENGINE=InnoDB;

CREATE TABLE subscription_payment_logs (
    log_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    result VARCHAR(40) NULL,
    details TEXT NULL,
    payload_json LONGTEXT NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_spl_pay FOREIGN KEY (payment_id) REFERENCES subscription_payments(payment_id) ON DELETE CASCADE,
    CONSTRAINT fk_spl_user FOREIGN KEY (actor_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_spl_pay (payment_id)
) ENGINE=InnoDB;

INSERT INTO equipment_categories (category_name, description) VALUES
('Cardio', 'Cardiovascular training equipment'),
('Strength', 'Strength training machines'),
('Free Weights', 'Dumbbells, barbells and plates'),
('Functional Training', 'Functional fitness equipment'),
('Accessories', 'Gym accessories');

INSERT INTO inventory_categories (category_name, description) VALUES
('Cleaning Supplies', 'Cleaning and hygiene products'),
('Gym Accessories', 'Mats, bands and accessories'),
('Towels', 'Gym towels'),
('Drinks', 'Water and other drinks'),
('Merchandise', 'Gym merchandise');

INSERT INTO expense_categories (category_name, description) VALUES
('Electricity', 'Electricity bills'),
('Water', 'Water bills'),
('Rent', 'Gym rental expenses'),
('Equipment', 'Equipment purchases'),
('Maintenance', 'Equipment repair and maintenance'),
('Cleaning', 'Cleaning expenses'),
('Marketing', 'Advertising and marketing'),
('Salaries', 'Staff salaries'),
('Other', 'Other expenses');

INSERT INTO notification_templates (template_code, template_name, message_template, channel, recipient_type) VALUES
('MEMBER_REGISTERED', 'Member Registration', 'Hi {{name}}, welcome to Volt Gym. Your member code is {{code}}.', 'SMS', 'MEMBER'),
('MEMBERSHIP_ACTIVATED', 'Membership Activation', 'Hi {{name}}, your monthly gym membership is now active until {{end_date}}.', 'SMS', 'MEMBER'),
('MEMBERSHIP_EXPIRY', 'Membership Expiry Reminder', 'Hi {{name}}, your gym membership expires on {{end_date}}. Please renew soon.', 'SMS', 'MEMBER'),
('MEMBERSHIP_EXPIRED', 'Membership Expired', 'Hi {{name}}, your gym membership has expired. Please renew to continue access.', 'SMS', 'MEMBER'),
('PAYMENT_DUE', 'Payment Due', 'Hi {{name}}, your gym payment of {{balance}} is due. Please complete your payment.', 'SMS', 'MEMBER'),
('PAYMENT_OVERDUE', 'Payment Overdue', 'Hi {{name}}, your gym payment of {{balance}} is overdue.', 'SMS', 'MEMBER'),
('PAYMENT_SUCCESS', 'Payment Successful', 'Your gym payment of {{amount}} has been successfully received.', 'SMS', 'MEMBER'),
('PAYMENT_FAILED', 'Payment Failed', 'Your gym payment could not be completed. Please try again.', 'SMS', 'MEMBER'),
('CHECK_IN', 'Check In', 'Hi {{name}}, you have successfully checked in to the gym.', 'SMS', 'MEMBER'),
('CHECK_OUT', 'Check Out', 'Hi {{name}}, you have successfully checked out from the gym.', 'SMS', 'MEMBER'),
('CLASS_REMINDER', 'Class Reminder', 'Reminder: your gym class {{class}} is scheduled at {{time}}.', 'SMS', 'MEMBER'),
('APPOINTMENT_REMINDER', 'Appointment Reminder', 'Reminder: you have a trainer appointment on {{date}} at {{time}}.', 'SMS', 'MEMBER'),
('ANNOUNCEMENT', 'Announcement', '{{message}}', 'SMS', 'MEMBER'),
('NEW_MEMBER', 'New Member Registration', 'A new member has registered: {{name}} ({{code}}).', 'SMS', 'ADMIN'),
('ADMIN_PAYMENT_SUCCESS', 'Member Payment Received', 'A member payment has been successfully received.', 'SMS', 'ADMIN'),
('ADMIN_PAYMENT_FAILED', 'Member Payment Failed', 'An online member payment has failed.', 'SMS', 'ADMIN'),
('ADMIN_PAYMENT_OVERDUE', 'Overdue Payment Alert', 'A member has an overdue gym payment.', 'SMS', 'ADMIN'),
('LOW_STOCK', 'Low Stock Alert', 'Gym inventory has reached the minimum stock level: {{item}}.', 'SMS', 'ADMIN'),
('MAINTENANCE_DUE', 'Equipment Maintenance', 'Gym equipment maintenance is due: {{equipment}}.', 'SMS', 'ADMIN'),
('GYM_CAPACITY', 'Gym Capacity Alert', 'The gym is currently at or over capacity.', 'SMS', 'ADMIN'),
('INACTIVE_MEMBER', 'Inactive Member Alert', 'A member has been inactive and may need follow-up.', 'SMS', 'ADMIN');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('gym_name', 'Volt Gym', 'Gym name'),
('gym_phone', '0112345678', 'Gym contact number'),
('gym_email', 'hello@voltgym.local', 'Gym email address'),
('gym_address', 'Colombo, Sri Lanka', 'Gym address'),
('gym_capacity', '80', 'Maximum members allowed inside at once'),
('membership_expiry_reminder_days', '7', 'Number of days before expiry to notify member'),
('inactive_member_days', '14', 'Number of days without attendance before inactive alert'),
('sms_notifications_enabled', '1', 'Enable or disable SMS notifications'),
('online_payments_enabled', '1', 'Enable or disable online payments'),
('currency', 'LKR', 'Display currency'),
('monthly_fee', '5000.00', 'Monthly gym fee charged to members'),
('billing_company_name', 'Volt Gym Software', 'SaaS billing company name'),
('billing_account_name', 'Volt Gym (Pvt) Ltd', 'SaaS billing account name'),
('billing_bank_name', 'Commercial Bank of Ceylon', 'SaaS billing bank'),
('billing_account_number', '8001234567', 'SaaS billing account number'),
('billing_branch', 'Colombo 03', 'SaaS billing branch'),
('billing_support_email', 'billing@voltgym.local', 'SaaS billing support email'),
('billing_support_phone', '0112345678', 'SaaS billing support phone'),
('billing_instructions', 'Transfer the exact subscription amount to the company bank account. Use your gym name as the payment narrative, then upload a clear photo or PDF of the bank slip or transfer confirmation.', 'SaaS payment instructions');

CREATE OR REPLACE VIEW active_members_view AS
SELECT m.member_id, m.member_code, m.full_name, m.phone, m.email, ms.membership_id, ms.start_date, ms.end_date, ms.final_amount, ms.paid_amount, ms.balance, ms.status AS membership_status
FROM members m
INNER JOIN memberships ms ON m.member_id = ms.member_id
WHERE ms.status = 'ACTIVE';

CREATE OR REPLACE VIEW pending_payments_view AS
SELECT m.member_id, m.member_code, m.full_name, m.phone, ms.membership_id, ms.final_amount, ms.paid_amount, ms.balance, ms.end_date
FROM members m
INNER JOIN memberships ms ON m.member_id = ms.member_id
WHERE ms.balance > 0 AND ms.status IN ('ACTIVE','PENDING');

CREATE OR REPLACE VIEW attendance_summary_view AS
SELECT m.member_id, m.member_code, m.full_name, COUNT(a.attendance_id) AS total_visits, MAX(a.check_in) AS last_check_in, AVG(a.duration_minutes) AS average_duration
FROM members m
LEFT JOIN attendance a ON m.member_id = a.member_id
GROUP BY m.member_id, m.member_code, m.full_name;

CREATE OR REPLACE VIEW inventory_status_view AS
SELECT inventory_id, item_code, item_name, quantity, minimum_stock, unit,
CASE WHEN quantity = 0 THEN 'OUT_OF_STOCK' WHEN quantity <= minimum_stock THEN 'LOW_STOCK' ELSE 'AVAILABLE' END AS calculated_status
FROM inventory;

CREATE OR REPLACE VIEW monthly_revenue_view AS
SELECT YEAR(payment_date) AS payment_year, MONTH(payment_date) AS payment_month, SUM(amount) AS total_revenue
FROM payments
WHERE payment_status = 'SUCCESS'
GROUP BY YEAR(payment_date), MONTH(payment_date);
