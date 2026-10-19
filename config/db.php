<?php
/**
 * Database Configuration & Auto-Migration
 * ABC School Nashik ERP System
 */

// Database configuration
$db_file = __DIR__ . '/../database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// Auto-initialize tables if not created
function init_database($pdo) {
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
    ");

    // Check if admin user exists, if not seed default accounts
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // 1. Seed Admin
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('admin', 'admin@abcschool.edu', '{$adminPass}', 'admin', 'approved')");

        // 2. Seed Teachers
        $teacherPass = password_hash('teacher123', PASSWORD_BCRYPT);
        
        // Teacher 1
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('priya.kulkarni', 'priya.kulkarni@abcschool.edu', '{$teacherPass}', 'teacher', 'approved')");
        $t1_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO teachers (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, joining_date)
                    VALUES ({$t1_user_id}, 'TCH-201', 'Mrs. Priya Kulkarni', 'Female', '+91 98220 12345', 'Mathematics', 'HOD Mathematics', 'M.Sc. B.Ed Mathematics', '2019-06-15')");

        // Teacher 2
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('amit.deshmukh', 'amit.deshmukh@abcschool.edu', '{$teacherPass}', 'teacher', 'approved')");
        $t2_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO teachers (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, joining_date)
                    VALUES ({$t2_user_id}, 'TCH-202', 'Mr. Amit Deshmukh', 'Male', '+91 98220 54321', 'Science', 'PGT Physics', 'M.Sc Physics, B.Ed', '2021-07-01')");

        // 3. Seed Students
        $studentPass = password_hash('student123', PASSWORD_BCRYPT);

        // Student 1: Approved
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('aarav.patil', 'aarav.patil@student.abc.edu', '{$studentPass}', 'student', 'approved')");
        $s1_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s1_user_id}, 'STU-1001', 'Aarav Patil', 'Male', '2010-04-12', 'Class 10-A', '1001', 'Suresh Patil', '+91 94230 11223', 'College Road, Nashik - 422005', 'O+', '2023-06-10')");
        $s1_id = $pdo->lastInsertId();

        // Student 2: Approved
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('ananya.joshi', 'ananya.joshi@student.abc.edu', '{$studentPass}', 'student', 'approved')");
        $s2_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s2_user_id}, 'STU-1002', 'Ananya Joshi', 'Female', '2010-09-21', 'Class 10-A', '1002', 'Milind Joshi', '+91 94230 44556', 'Gangapur Road, Nashik - 422013', 'B+', '2023-06-12')");
        $s2_id = $pdo->lastInsertId();

        // Student 3: Pending Approval (Awaiting Admin approval!)
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('rohan.shinde', 'rohan.shinde@student.abc.edu', '{$studentPass}', 'student', 'pending')");
        $s3_user_id = $pdo->lastInsertId();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s3_user_id}, 'STU-1003', 'Rohan Shinde', 'Male', '2011-02-18', 'Class 9-B', '9014', 'Ramesh Shinde', '+91 98900 88776', 'Indira Nagar, Nashik - 422009', 'A+', '2026-10-18')");

        // 4. Seed Attendance for Aarav & Ananya
        $dates = ['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'];
        foreach ($dates as $idx => $d) {
            $status1 = ($idx == 3) ? 'Absent' : (($idx == 6) ? 'Late' : 'Present');
            $status2 = ($idx == 5) ? 'Absent' : 'Present';
            $pdo->exec("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES ({$s1_id}, '{$d}', '{$status1}', 'Regular lecture', {$t1_user_id})");
            $pdo->exec("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES ({$s2_id}, '{$d}', '{$status2}', 'Regular lecture', {$t1_user_id})");
        }

        // 5. Seed Grades
        $grades_data = [
            [$s1_id, 'Mathematics', 'Mid-Term 2026', 92, 100, 'A+', 'Excellent performance', $t1_user_id],
            [$s1_id, 'Science', 'Mid-Term 2026', 88, 100, 'A', 'Strong grasp of concepts', $t2_user_id],
            [$s1_id, 'English', 'Mid-Term 2026', 84, 100, 'A', 'Good vocabulary and writing', $t1_user_id],
            [$s1_id, 'Social Science', 'Mid-Term 2026', 90, 100, 'A+', 'Well structured answers', $t2_user_id],
            [$s2_id, 'Mathematics', 'Mid-Term 2026', 95, 100, 'A+', 'Outstanding problem-solving', $t1_user_id],
            [$s2_id, 'Science', 'Mid-Term 2026', 94, 100, 'A+', 'Exceptional in practicals', $t2_user_id],
        ];

        foreach ($grades_data as $g) {
            $pdo->exec("INSERT INTO grades (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id) 
                        VALUES ({$g[0]}, '{$g[1]}', '{$g[2]}', {$g[3]}, {$g[4]}, '{$g[5]}', '{$g[6]}', {$g[7]})");
        }

        // 6. Seed Notices
        $notices = [
            ['ABC School Annual Science Exhibition 2026', 'We are pleased to announce the Annual Science & Robotics Exhibition will be held on November 15, 2026 at the Main Auditorium, Nashik Campus. All students are invited to register projects.', 'all', 'Event', 1],
            ['Mid-Term Examination Schedule & Guidelines', 'The upcoming semester mid-term examinations commence from November 20, 2026. Hall tickets will be issued through this ERP portal. Please verify your attendance threshold.', 'students', 'Exam', 1],
            ['Teachers Staff Council Meeting - Q4 Academic Review', 'Notice for all faculty members: The quarterly academic evaluation and CBSE compliance meeting is scheduled for Friday at 3:30 PM in the Conference Hall.', 'teachers', 'Academic', 1],
            ['Diwali Vacation Schedule 2026', 'ABC School Nashik will observe Diwali holidays starting from October 28 to November 5, 2026. The school office and administrative desks will reopen on November 6, 2026.', 'all', 'Holiday', 1]
        ];

        foreach ($notices as $n) {
            $pdo->exec("INSERT INTO notices (title, content, audience, category, posted_by) VALUES ('{$n[0]}', '{$n[1]}', '{$n[2]}', '{$n[3]}', {$n[4]})");
        }
    }
}

// Initialize tables and seed on load
init_database($pdo);
?>
