<?php
/**
 * Database Configuration & Auto-Migration
 * ABC School Nashik ERP System
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
            role TEXT CHECK(role IN ('admin', 'teacher', 'student')) NOT NULL,
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
    ");

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
}

// Auto-initialize tables
init_database($pdo);
?>
