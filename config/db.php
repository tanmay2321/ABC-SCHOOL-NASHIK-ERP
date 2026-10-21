<?php
/**
 * Database Configuration & Auto-Migration
 * Bellwether International School Nandurbar ERP System
 */

$db_file = __DIR__ . '/../database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Auto-initialize tables and seed data if not present
function init_database($pdo) {
    // 1. Create All Tables
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT CHECK(role IN ('admin', 'principal', 'vice_principal', 'teacher', 'student')) NOT NULL,
            status TEXT CHECK(status IN ('pending', 'approved', 'rejected', 'active', 'inactive')) DEFAULT 'approved',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS teachers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            teacher_code TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            gender TEXT,
            phone TEXT,
            department TEXT NOT NULL,
            designation TEXT,
            qualification TEXT,
            assigned_class TEXT DEFAULT NULL,
            joining_date TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER UNIQUE NOT NULL,
            student_code TEXT UNIQUE NOT NULL,
            full_name TEXT NOT NULL,
            gender TEXT,
            dob TEXT,
            class TEXT NOT NULL,
            roll_no TEXT,
            guardian_name TEXT,
            phone TEXT,
            address TEXT,
            blood_group TEXT,
            admission_date TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            date DATE NOT NULL,
            status TEXT CHECK(status IN ('Present', 'Absent', 'Late')) NOT NULL,
            remarks TEXT,
            marked_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS grades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            exam_name TEXT NOT NULL,
            marks_obtained REAL NOT NULL,
            max_marks REAL NOT NULL DEFAULT 100,
            grade TEXT,
            remarks TEXT,
            teacher_id INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            audience TEXT CHECK(audience IN ('all', 'teachers', 'students')) DEFAULT 'all',
            category TEXT DEFAULT 'Circular',
            posted_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ADVANCED ERP MODULE 1: Assignments & Homework
        CREATE TABLE IF NOT EXISTS assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            teacher_id INTEGER NOT NULL,
            class TEXT NOT NULL,
            subject TEXT NOT NULL,
            title TEXT NOT NULL,
            description TEXT NOT NULL,
            due_date DATE NOT NULL,
            max_marks REAL DEFAULT 100,
            attachment_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS assignment_submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            assignment_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            file_path TEXT NOT NULL,
            student_remarks TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            marks_obtained REAL DEFAULT NULL,
            teacher_feedback TEXT,
            status TEXT CHECK(status IN ('Submitted', 'Graded', 'Late')) DEFAULT 'Submitted',
            FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );

        -- ADVANCED ERP MODULE 2: Fee Accounting & Billing
        CREATE TABLE IF NOT EXISTS fee_invoices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_no TEXT UNIQUE NOT NULL,
            student_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            tuition_fee REAL DEFAULT 0,
            lab_fee REAL DEFAULT 0,
            library_fee REAL DEFAULT 0,
            sports_fee REAL DEFAULT 0,
            exam_fee REAL DEFAULT 0,
            total_amount REAL NOT NULL,
            due_date DATE NOT NULL,
            status TEXT CHECK(status IN ('Paid', 'Partial', 'Unpaid')) DEFAULT 'Unpaid',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS fee_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            receipt_no TEXT UNIQUE NOT NULL,
            invoice_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            amount_paid REAL NOT NULL,
            payment_mode TEXT CHECK(payment_mode IN ('UPI', 'Cash', 'Card', 'Net Banking')) DEFAULT 'UPI',
            transaction_ref TEXT,
            payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            received_by INTEGER,
            FOREIGN KEY (invoice_id) REFERENCES fee_invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );

        -- ADVANCED ERP MODULE 3: Class Timetable & Schedule
        CREATE TABLE IF NOT EXISTS timetables (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT NOT NULL,
            day_of_week TEXT NOT NULL,
            period_number INTEGER NOT NULL,
            time_slot TEXT NOT NULL,
            subject TEXT NOT NULL,
            teacher_name TEXT NOT NULL,
            room_no TEXT DEFAULT 'Room 101'
        );

        -- ADVANCED ERP MODULE 4: Leave Application & Management
        CREATE TABLE IF NOT EXISTS leave_applications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            role TEXT NOT NULL,
            leave_type TEXT NOT NULL,
            from_date DATE NOT NULL,
            to_date DATE NOT NULL,
            reason TEXT NOT NULL,
            status TEXT CHECK(status IN ('Pending', 'Approved', 'Rejected')) DEFAULT 'Pending',
            admin_remarks TEXT,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        -- SYSTEM SETTINGS & BRANDING
        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        -- ADVANCED ERP MODULE 5: Curriculum & Subjects per Class
        CREATE TABLE IF NOT EXISTS subjects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            class TEXT NOT NULL,
            name TEXT NOT NULL,
            code TEXT DEFAULT '',
            max_marks REAL DEFAULT 100,
            pass_marks REAL DEFAULT 35,
            syllabus_info TEXT DEFAULT '',
            academic_year TEXT DEFAULT '2026-2027',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        -- ADVANCED ERP MODULE 6: Academic Year Batch Promotion History
        CREATE TABLE IF NOT EXISTS promotion_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            from_class TEXT NOT NULL,
            to_class TEXT NOT NULL,
            academic_year TEXT NOT NULL,
            promoted_by INTEGER,
            promoted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        );
    ");

    // Migration: ensure assigned_class exists in teachers table
    $t_cols = $pdo->query("PRAGMA table_info(teachers)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('assigned_class', $t_cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN assigned_class TEXT DEFAULT NULL;");
    }

    // Migration: ensure users table supports principal & vice_principal roles
    $user_sql = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
    if ($user_sql && strpos($user_sql, "'principal'") === false) {
        $pdo->exec("PRAGMA foreign_keys = OFF;");
        $pdo->exec("
            CREATE TABLE users_migrated (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                role TEXT CHECK(role IN ('admin', 'principal', 'vice_principal', 'teacher', 'student')) NOT NULL,
                status TEXT CHECK(status IN ('pending', 'approved', 'rejected', 'active', 'inactive')) DEFAULT 'approved',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            INSERT INTO users_migrated SELECT id, username, email, password, role, status, created_at, updated_at FROM users;
            DROP TABLE users;
            ALTER TABLE users_migrated RENAME TO users;
        ");
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    // 2. Ensure Single Admin Account Exists (admin@edu.in / Admin@1234)
    $admin_email = 'admin@edu.in';
    $admin_check = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? OR role = 'admin' LIMIT 1");
    $admin_check->execute([$admin_email, $admin_email]);
    $admin_user = $admin_check->fetch();

    $adminPass = password_hash('Admin@1234', PASSWORD_BCRYPT);

    if ($admin_user) {
        $upd = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = 'admin', status = 'approved' WHERE id = ?");
        $upd->execute([$admin_email, $admin_email, $adminPass, $admin_user['id']]);
    } else {
        $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'admin', 'approved')");
        $ins->execute([$admin_email, $admin_email, $adminPass]);
    }

    // 3. Default Institution Settings
    $defaults = [
        'school_name' => 'BELLWETHER INTERNATIONAL SCHOOL NANDURBAR',
        'school_city' => 'Nandurbar',
        'school_affiliation' => 'CBSE Affiliation #113042',
        'school_address' => 'Old Nalwa Road, Nandurbar - 425412',
        'school_phone' => '+91 6262848446',
        'school_email' => 'bwis.nandurbar@gmail.com',
        'admin_name' => 'System Administrator',
        'admin_designation' => 'IT & Systems Head Administrator',
        'principal_name' => 'Dr. R. K. Sharma',
        'principal_designation' => 'Head Principal',
        'vice_principal_name' => 'Prof. S. M. Deshmukh',
        'vice_principal_designation' => 'Vice Principal (Academics)',
        'school_logo' => '',
        'current_academic_year' => '2026-2027'
    ];

    $check_setting = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
    $insert_setting = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $check_setting->execute([$k]);
        if ($check_setting->fetchColumn() == 0) {
            $insert_setting->execute([$k, $v]);
        }
    }

    // 4. Seed Standard Subjects if table is empty
    $subj_count = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    if ($subj_count == 0) {
        $common_classes = [
            'Class 10-A', 'Class 10-B', 'Class 9-A', 'Class 9-B', 'Class 8-A', 'Class 8-B',
            'Grade 10', 'Grade 9', 'Grade 8', 'Grade 7', 'Grade 6', 'Grade 5', 'Grade 4', 'Grade 3', 'Grade 2', 'Grade 1'
        ];
        $standard_subjects = [
            ['name' => 'English Literature & Lang', 'code' => 'ENG', 'max' => 100, 'pass' => 35],
            ['name' => 'Mathematics', 'code' => 'MATH', 'max' => 100, 'pass' => 35],
            ['name' => 'General Science', 'code' => 'SCI', 'max' => 100, 'pass' => 35],
            ['name' => 'Social Science', 'code' => 'SST', 'max' => 100, 'pass' => 35],
            ['name' => 'Hindi', 'code' => 'HIN', 'max' => 100, 'pass' => 35],
            ['name' => 'Computer Science & AI', 'code' => 'CS', 'max' => 100, 'pass' => 35]
        ];

        $ins_subj = $pdo->prepare("
            INSERT INTO subjects (class, name, code, max_marks, pass_marks, syllabus_info, academic_year)
            VALUES (?, ?, ?, ?, ?, 'CBSE Prescribed Curriculum', '2026-2027')
        ");

        foreach ($common_classes as $cls) {
            foreach ($standard_subjects as $s) {
                $ins_subj->execute([$cls, $s['name'], $s['code'], $s['max'], $s['pass']]);
            }
        }
    }
}

// Global settings helpers
function get_setting($pdo, $key, $default = '') {
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function set_setting($pdo, $key, $value) {
    try {
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

// Auto-initialize tables
init_database($pdo);
?>
