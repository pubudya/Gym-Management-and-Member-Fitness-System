<?php
declare(strict_types=1);

final class SchemaUpgrade
{
    public static function apply(): void
    {
        try {
            Database::pdo()->query('SELECT 1 FROM users LIMIT 1');
        } catch (Throwable $e) {
            return;
        }

        self::tryExec("ALTER TABLE users MODIFY role ENUM('ADMIN','MEMBER','COACH','PLATFORM') NOT NULL");

        if (!self::column('trainers', 'user_id')) {
            self::tryExec('ALTER TABLE trainers ADD COLUMN user_id INT UNSIGNED NULL UNIQUE AFTER trainer_id');
            self::tryExec('ALTER TABLE trainers ADD CONSTRAINT fk_trainer_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL');
        }
        if (!self::column('workout_plans', 'created_by')) {
            self::tryExec('ALTER TABLE workout_plans ADD COLUMN created_by INT UNSIGNED NULL AFTER trainer_id');
        }
        if (!self::column('workout_plans', 'cover_image')) {
            self::tryExec('ALTER TABLE workout_plans ADD COLUMN cover_image VARCHAR(255) NULL AFTER description');
        }
        if (!self::column('workout_plans', 'workout_style')) {
            self::tryExec("ALTER TABLE workout_plans ADD COLUMN workout_style VARCHAR(50) NOT NULL DEFAULT 'STRENGTH' AFTER goal_type");
        }

        self::tryExec('ALTER TABLE workout_plans DROP FOREIGN KEY fk_workout_trainer');
        self::tryExec('ALTER TABLE workout_plans MODIFY trainer_id INT UNSIGNED NULL');
        self::tryExec('ALTER TABLE workout_plans ADD CONSTRAINT fk_workout_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE SET NULL');

        if (!self::column('workout_exercises', 'exercise_type')) {
            self::tryExec("ALTER TABLE workout_exercises ADD COLUMN exercise_type VARCHAR(50) NOT NULL DEFAULT 'STRENGTH' AFTER exercise_name");
        }
        if (!self::column('workout_exercises', 'tracking_type')) {
            self::tryExec("ALTER TABLE workout_exercises ADD COLUMN tracking_type ENUM('SETS_REPS','DURATION','HOLD') NOT NULL DEFAULT 'SETS_REPS' AFTER exercise_type");
        }
        if (!self::column('workout_exercises', 'duration_seconds')) {
            self::tryExec('ALTER TABLE workout_exercises ADD COLUMN duration_seconds INT UNSIGNED NULL AFTER repetitions');
        }
        if (!self::column('workout_exercises', 'image_path')) {
            self::tryExec('ALTER TABLE workout_exercises ADD COLUMN image_path VARCHAR(255) NULL AFTER instructions');
        }

        if (!self::table('meal_plans')) {
            self::tryExec("CREATE TABLE meal_plans (
                meal_plan_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                trainer_id INT UNSIGNED NULL,
                created_by INT UNSIGNED NULL,
                plan_name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                goal_type ENUM('WEIGHT_LOSS','MUSCLE_GAIN','STRENGTH','ENDURANCE','FLEXIBILITY','GENERAL_FITNESS') NOT NULL DEFAULT 'GENERAL_FITNESS',
                cover_image VARCHAR(255) NULL,
                daily_calories INT UNSIGNED NULL,
                status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_meal_trainer FOREIGN KEY (trainer_id) REFERENCES trainers(trainer_id) ON DELETE SET NULL,
                CONSTRAINT fk_meal_creator FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB");
        }
        if (!self::table('meal_items')) {
            self::tryExec("CREATE TABLE meal_items (
                meal_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                meal_plan_id INT UNSIGNED NOT NULL,
                meal_slot ENUM('BREAKFAST','LUNCH','DINNER','SNACK') NOT NULL,
                food_name VARCHAR(150) NOT NULL,
                portion VARCHAR(80) NULL,
                calories INT UNSIGNED NULL,
                protein_g DECIMAL(6,1) NULL,
                carbs_g DECIMAL(6,1) NULL,
                fat_g DECIMAL(6,1) NULL,
                image_path VARCHAR(255) NULL,
                notes TEXT NULL,
                item_order INT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_meal_item_plan FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(meal_plan_id) ON DELETE CASCADE,
                INDEX idx_meal_items_plan (meal_plan_id)
            ) ENGINE=InnoDB");
        }
        if (!self::table('member_meal_plans')) {
            self::tryExec("CREATE TABLE member_meal_plans (
                member_meal_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                member_id INT UNSIGNED NOT NULL,
                meal_plan_id INT UNSIGNED NOT NULL,
                assigned_date DATE NOT NULL,
                end_date DATE NULL,
                status ENUM('ACTIVE','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
                assigned_by INT UNSIGNED NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_mmp_member FOREIGN KEY (member_id) REFERENCES members(member_id) ON DELETE CASCADE,
                CONSTRAINT fk_mmp_plan FOREIGN KEY (meal_plan_id) REFERENCES meal_plans(meal_plan_id) ON DELETE CASCADE,
                CONSTRAINT fk_mmp_user FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
                INDEX idx_mmp_member (member_id)
            ) ENGINE=InnoDB");
        }

        self::ensureSubscriptionSchema();
        self::simplifyMemberships();
        self::purgeDemoData();

        try {
            self::seedCoachLogins();
            self::seedSubscriptionModule();
        } catch (Throwable $e) {
        }
    }

    private static function ensureSubscriptionSchema(): void
    {
        if (!self::table('gyms')) {
            self::tryExec("CREATE TABLE gyms (
                gym_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                gym_name VARCHAR(150) NOT NULL,
                status ENUM('ACTIVE','INACTIVE','SUSPENDED') NOT NULL DEFAULT 'ACTIVE',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        }
        if (!self::table('subscription_plans')) {
            self::tryExec("CREATE TABLE subscription_plans (
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
            ) ENGINE=InnoDB");
        }
        if (!self::table('gym_subscriptions')) {
            self::tryExec("CREATE TABLE gym_subscriptions (
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
            ) ENGINE=InnoDB");
        }
        if (!self::table('subscription_payments')) {
            self::tryExec("CREATE TABLE subscription_payments (
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
            ) ENGINE=InnoDB");
        }
        if (!self::table('subscription_payment_logs')) {
            self::tryExec("CREATE TABLE subscription_payment_logs (
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
            ) ENGINE=InnoDB");
        }
    }

    private static function seedSubscriptionModule(): void
    {
        if (!self::table('gyms') || !self::table('subscription_plans')) {
            return;
        }

        if (!Database::fetch('SELECT gym_id FROM gyms LIMIT 1')) {
            Database::insert('gyms', [
                'gym_name' => setting('gym_name', APP_NAME),
                'status' => 'ACTIVE',
            ]);
        }

        $starter = json_encode([
            'member_management', 'coach_management', 'attendance', 'basic_payments',
        ]);
        $professional = json_encode([
            'member_management', 'coach_management', 'attendance', 'basic_payments',
            'workout_management', 'progress_tracking', 'sms_notifications', 'reports',
            'equipment_management', 'meal_plans', 'classes', 'appointments', 'inventory', 'expenses', 'feedback',
        ]);
        $premium = json_encode([
            'member_management', 'coach_management', 'attendance', 'basic_payments',
            'workout_management', 'progress_tracking', 'sms_notifications', 'reports',
            'equipment_management', 'meal_plans', 'classes', 'appointments', 'inventory', 'expenses', 'feedback',
            'advanced_analytics', 'multiple_branches', 'advanced_reporting',
        ]);

        $plans = [
            ['STARTER', 'Starter', 5000.00, 30, $starter, 100, 2, 100, 'INACTIVE'],
            ['PROFESSIONAL', 'Professional', 10000.00, 30, $professional, null, null, null, 'ACTIVE'],
            ['PREMIUM', 'Premium', 18000.00, 30, $premium, null, null, null, 'INACTIVE'],
        ];
        foreach ($plans as $p) {
            if (Database::fetch('SELECT plan_id FROM subscription_plans WHERE plan_code = ?', [$p[0]])) {
                continue;
            }
            Database::insert('subscription_plans', [
                'plan_code' => $p[0],
                'plan_name' => $p[1],
                'monthly_price' => $p[2],
                'duration_days' => $p[3],
                'features_json' => $p[4],
                'member_limit' => $p[5],
                'coach_limit' => $p[6],
                'notification_limit' => $p[7],
                'status' => $p[8],
            ]);
        }

        $gym = Database::fetch('SELECT gym_id FROM gyms ORDER BY gym_id ASC LIMIT 1');
        $pro = Database::fetch("SELECT * FROM subscription_plans WHERE plan_code='PROFESSIONAL' LIMIT 1");
        if ($gym && $pro && !Database::fetch('SELECT subscription_id FROM gym_subscriptions WHERE gym_id = ?', [$gym['gym_id']])) {
            $start = date('Y-m-d', strtotime('-20 days'));
            $expiry = date('Y-m-d', strtotime('+10 days'));
            Database::insert('gym_subscriptions', [
                'gym_id' => $gym['gym_id'],
                'plan_id' => $pro['plan_id'],
                'amount' => $pro['monthly_price'],
                'start_date' => $start,
                'expiry_date' => $expiry,
                'status' => 'EXPIRING_SOON',
                'next_payment_due_date' => $expiry,
            ]);
        }

        $settings = [
            'billing_company_name' => 'Volt Gym Software',
            'billing_account_name' => 'Volt Gym (Pvt) Ltd',
            'billing_bank_name' => 'Commercial Bank of Ceylon',
            'billing_account_number' => '8001234567',
            'billing_branch' => 'Colombo 03',
            'billing_support_email' => 'billing@voltgym.local',
            'billing_support_phone' => '0112345678',
            'billing_instructions' => 'Transfer the exact subscription amount to the company bank account. Use your gym name as the payment narrative, then upload a clear photo or PDF of the bank slip or transfer confirmation.',
            'monthly_fee' => '5000.00',
        ];
        foreach ($settings as $key => $value) {
            if (setting($key) === null || setting($key) === '') {
                Database::execute(
                    'INSERT INTO system_settings (setting_key, setting_value, description)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                    [$key, $value, 'SaaS billing setting']
                );
            }
        }

        if (!Database::fetch("SELECT user_id FROM users WHERE role='PLATFORM' LIMIT 1")) {
            $email = 'reviewer@voltgym.local';
            if (!Database::fetch('SELECT user_id FROM users WHERE username = ? OR email = ?', ['reviewer', $email])) {
                Database::insert('users', [
                    'username' => 'reviewer',
                    'email' => $email,
                    'password_hash' => password_hash('Review@123', PASSWORD_DEFAULT),
                    'role' => 'PLATFORM',
                    'status' => 'ACTIVE',
                ]);
            }
        }
    }

    private static function seedCoachLogins(): void
    {
        if (!self::column('trainers', 'user_id')) {
            return;
        }
        foreach (Database::fetchAll('SELECT * FROM trainers WHERE user_id IS NULL') as $t) {
            $first = explode(' ', (string) $t['full_name'])[0] ?? 'coach';
            $user = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $first));
            if ($user === '') {
                $user = 'coach' . $t['trainer_id'];
            }
            $email = $t['email'] ?: ($user . '@voltgym.local');
            $existing = Database::fetch('SELECT user_id FROM users WHERE username = ? OR email = ?', [$user, $email]);
            if ($existing) {
                Database::update('users', ['role' => 'COACH'], 'user_id=?', [$existing['user_id']]);
                Database::update('trainers', ['user_id' => $existing['user_id']], 'trainer_id=?', [$t['trainer_id']]);
                continue;
            }
            $uid = Database::insert('users', [
                'username' => $user,
                'email' => $email,
                'password_hash' => password_hash('Coach@123', PASSWORD_DEFAULT),
                'role' => 'COACH',
                'status' => 'ACTIVE',
            ]);
            Database::update('trainers', ['user_id' => $uid], 'trainer_id=?', [$t['trainer_id']]);
        }
    }

    private static function simplifyMemberships(): void
    {
        if (self::table('memberships') && self::column('memberships', 'plan_id')) {
            self::tryExec('ALTER TABLE memberships DROP FOREIGN KEY fk_membership_plan');
            self::tryExec('ALTER TABLE memberships DROP COLUMN plan_id');
        }
        if (self::table('membership_plans')) {
            self::tryExec('DROP TABLE membership_plans');
        }
        self::tryExec("CREATE OR REPLACE VIEW active_members_view AS
            SELECT m.member_id, m.member_code, m.full_name, m.phone, m.email, ms.membership_id, ms.start_date, ms.end_date, ms.final_amount, ms.paid_amount, ms.balance, ms.status AS membership_status
            FROM members m
            INNER JOIN memberships ms ON m.member_id = ms.member_id
            WHERE ms.status = 'ACTIVE'");
    }

    private static function purgeDemoData(): void
    {
        try {
            $flag = Database::fetch("SELECT setting_value FROM system_settings WHERE setting_key = 'demo_data_purged'");
            if (($flag['setting_value'] ?? '') === '1') {
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        $memberIds = [];
        try {
            foreach (Database::fetchAll(
                "SELECT member_id FROM members
                 WHERE member_code IN ('GYM-0001','GYM-0002','GYM-0003')
                    OR email IN ('kasun@voltgym.local','nimesha@voltgym.local','ruwan@voltgym.local')"
            ) as $row) {
                $memberIds[] = (int) $row['member_id'];
            }
        } catch (Throwable $e) {
        }

        foreach ($memberIds as $id) {
            self::tryExec('DELETE FROM payment_transactions WHERE payment_id IN (SELECT payment_id FROM payments WHERE member_id = ' . $id . ')');
            foreach ([
                'class_bookings',
                'appointments',
                'feedback',
                'complaints',
                'workout_progress',
                'fitness_measurements',
                'fitness_goals',
                'member_workout_plans',
                'member_meal_plans',
                'attendance',
                'notifications',
                'payments',
                'memberships',
                'trainer_members',
            ] as $table) {
                if (self::table($table)) {
                    self::tryExec('DELETE FROM `' . $table . '` WHERE member_id = ' . $id);
                }
            }
            self::tryExec('DELETE FROM members WHERE member_id = ' . $id);
        }

        foreach (['kasun', 'nimesha', 'ruwan', 'isuru', 'tharushi'] as $username) {
            self::tryExec("DELETE FROM users WHERE username = '" . $username . "' AND role IN ('MEMBER','COACH')");
        }

        self::tryExec("DELETE FROM class_schedules WHERE class_id IN (SELECT class_id FROM gym_classes WHERE class_name IN ('Morning HIIT','Yoga Flow'))");
        self::tryExec("DELETE FROM gym_classes WHERE class_name IN ('Morning HIIT','Yoga Flow')");
        self::tryExec("DELETE FROM equipment_maintenance WHERE equipment_id IN (SELECT equipment_id FROM equipment WHERE equipment_code IN ('EQ-0001','EQ-0002'))");
        self::tryExec("DELETE FROM stock_transactions WHERE inventory_id IN (SELECT inventory_id FROM inventory WHERE item_code IN ('INV-0001','INV-0002','INV-0003'))");
        self::tryExec("DELETE FROM trainers WHERE email IN ('isuru@voltgym.local','tharushi@voltgym.local')");
        self::tryExec("DELETE FROM meal_plans WHERE plan_name = 'Balanced training plate'");
        self::tryExec("DELETE FROM workout_plans WHERE plan_name IN ('Beginner Strength Builder','Fat Loss Circuit')");
        self::tryExec("DELETE FROM equipment WHERE equipment_code IN ('EQ-0001','EQ-0002')");
        self::tryExec("DELETE FROM inventory WHERE item_code IN ('INV-0001','INV-0002','INV-0003')");
        self::tryExec("DELETE FROM payments WHERE transaction_reference IN ('PAY-DEMO-1001','PAY-DEMO-1002')");
        self::tryExec("DELETE FROM suppliers WHERE email = 'sales@fitsupply.local'");
        self::tryExec("DELETE FROM expenses WHERE description IN ('Monthly rent','Electricity bill') AND created_by = 1");

        self::tryExec("INSERT INTO system_settings (setting_key, setting_value, description)
            VALUES ('demo_data_purged', '1', 'Demo seed rows were removed')
            ON DUPLICATE KEY UPDATE setting_value = '1'");
    }

    private static function table(string $name): bool
    {
        $name = preg_replace('/[^a-z0-9_]/i', '', $name);
        $stmt = Database::pdo()->query("SHOW TABLES LIKE '{$name}'");
        return (bool) $stmt->fetch();
    }

    private static function column(string $table, string $column): bool
    {
        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $column = preg_replace('/[^a-z0-9_]/i', '', $column);
        $stmt = Database::pdo()->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (bool) $stmt->fetch();
    }

    private static function tryExec(string $sql): void
    {
        try {
            Database::pdo()->exec($sql);
        } catch (Throwable $e) {
        }
    }
}
