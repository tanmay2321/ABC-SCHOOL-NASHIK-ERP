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

    // 2. Ensure Admin Exists
    $admin_check = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if (!$admin_check) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (username, email, password, role, status) 
                    VALUES ('admin', 'admin@abcschool.edu', '{$adminPass}', 'admin', 'approved')");
    }

    // 3. Ensure Teachers Exist
    $teacher_count = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    if ($teacher_count == 0) {
        $teacherPass = password_hash('teacher123', PASSWORD_BCRYPT);
        
        // Teacher 1
        $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, role, status) 
                    VALUES ('priya.kulkarni', 'priya.kulkarni@abcschool.edu', '{$teacherPass}', 'teacher', 'approved')");
        $t1_user_id = $pdo->query("SELECT id FROM users WHERE username = 'priya.kulkarni'")->fetchColumn();
        $pdo->exec("INSERT INTO teachers (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, joining_date)
                    VALUES ({$t1_user_id}, 'TCH-201', 'Mrs. Priya Kulkarni', 'Female', '+91 98220 12345', 'Mathematics', 'HOD Mathematics', 'M.Sc. B.Ed Mathematics', '2019-06-15')");

        // Teacher 2
        $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, role, status) 
                    VALUES ('amit.deshmukh', 'amit.deshmukh@abcschool.edu', '{$teacherPass}', 'teacher', 'approved')");
        $t2_user_id = $pdo->query("SELECT id FROM users WHERE username = 'amit.deshmukh'")->fetchColumn();
        $pdo->exec("INSERT INTO teachers (user_id, teacher_code, full_name, gender, phone, department, designation, qualification, joining_date)
                    VALUES ({$t2_user_id}, 'TCH-202', 'Mr. Amit Deshmukh', 'Male', '+91 98220 54321', 'Science', 'PGT Physics', 'M.Sc Physics, B.Ed', '2021-07-01')");
    }

    // 4. Ensure Students Exist
    $student_count = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    if ($student_count == 0) {
        $studentPass = password_hash('student123', PASSWORD_BCRYPT);

        // Student 1: Approved (Aarav Patil)
        $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, role, status) 
                    VALUES ('aarav.patil', 'aarav.patil@student.abc.edu', '{$studentPass}', 'student', 'approved')");
        $s1_user_id = $pdo->query("SELECT id FROM users WHERE username = 'aarav.patil'")->fetchColumn();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s1_user_id}, 'STU-1001', 'Aarav Patil', 'Male', '2010-04-12', 'Class 10-A', '1001', 'Suresh Patil', '+91 94230 11223', 'College Road, Nashik - 422005', 'O+', '2023-06-10')");

        // Student 2: Approved (Ananya Joshi)
        $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, role, status) 
                    VALUES ('ananya.joshi', 'ananya.joshi@student.abc.edu', '{$studentPass}', 'student', 'approved')");
        $s2_user_id = $pdo->query("SELECT id FROM users WHERE username = 'ananya.joshi'")->fetchColumn();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s2_user_id}, 'STU-1002', 'Ananya Joshi', 'Female', '2010-09-21', 'Class 10-A', '1002', 'Milind Joshi', '+91 94230 44556', 'Gangapur Road, Nashik - 422013', 'B+', '2023-06-12')");

        // Student 3: Pending Approval (Rohan Shinde)
        $pdo->exec("INSERT OR IGNORE INTO users (username, email, password, role, status) 
                    VALUES ('rohan.shinde', 'rohan.shinde@student.abc.edu', '{$studentPass}', 'student', 'pending')");
        $s3_user_id = $pdo->query("SELECT id FROM users WHERE username = 'rohan.shinde'")->fetchColumn();
        $pdo->exec("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
                    VALUES ({$s3_user_id}, 'STU-1003', 'Rohan Shinde', 'Male', '2011-02-18', 'Class 9-B', '9014', 'Ramesh Shinde', '+91 98900 88776', 'Indira Nagar, Nashik - 422009', 'A+', '2026-10-18')");
    }

    // 5. Ensure Attendance Exists
    $att_count = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    if ($att_count == 0) {
        $s1_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1001'")->fetchColumn();
        $s2_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1002'")->fetchColumn();
        $t1_user_id = $pdo->query("SELECT user_id FROM teachers LIMIT 1")->fetchColumn() ?: 1;

        if ($s1_id && $s2_id) {
            $dates = ['2026-10-10', '2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15', '2026-10-16', '2026-10-17'];
            foreach ($dates as $idx => $d) {
                $status1 = ($idx == 3) ? 'Absent' : (($idx == 6) ? 'Late' : 'Present');
                $status2 = ($idx == 5) ? 'Absent' : 'Present';
                $pdo->exec("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES ({$s1_id}, '{$d}', '{$status1}', 'Regular lecture', {$t1_user_id})");
                $pdo->exec("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES ({$s2_id}, '{$d}', '{$status2}', 'Regular lecture', {$t1_user_id})");
            }
        }
    }

    // 6. Ensure Grades Exist
    $grades_count = $pdo->query("SELECT COUNT(*) FROM grades")->fetchColumn();
    if ($grades_count == 0) {
        $s1_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1001'")->fetchColumn();
        $s2_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1002'")->fetchColumn();
        $t1_user_id = $pdo->query("SELECT user_id FROM teachers WHERE teacher_code = 'TCH-201'")->fetchColumn() ?: 1;
        $t2_user_id = $pdo->query("SELECT user_id FROM teachers WHERE teacher_code = 'TCH-202'")->fetchColumn() ?: 1;

        if ($s1_id && $s2_id) {
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
        }
    }

    // 7. Ensure Notices Exist
    $notice_count = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
    if ($notice_count == 0) {
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

    // 8. Ensure Fee Invoices & Payments Exist
    $feeCount = $pdo->query("SELECT COUNT(*) FROM fee_invoices")->fetchColumn();
    if ($feeCount == 0) {
        $s1_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1001'")->fetchColumn();
        $s2_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1002'")->fetchColumn();
        $s3_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1003'")->fetchColumn();

        if ($s1_id) {
            // Invoice 1: Aarav Patil (Partial payment: Rs. 25,000 paid, Rs. 15,000 pending)
            $pdo->exec("INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
                        VALUES ('INV-2026-001', {$s1_id}, 'Annual Academic Session Fee 2026-27 (Term 1)', 28000, 4500, 2000, 2500, 3000, 40000, '2026-11-30', 'Partial')");
            $inv1_id = $pdo->lastInsertId();

            $pdo->exec("INSERT INTO fee_payments (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, payment_date, received_by)
                        VALUES ('REC-2026-1088', {$inv1_id}, {$s1_id}, 25000, 'UPI', 'UPI/261019/98230491', '2026-09-15 11:30:00', 1)");
        }

        if ($s2_id) {
            // Invoice 2: Ananya Joshi (Fully Paid: Rs. 40,000 paid)
            $pdo->exec("INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
                        VALUES ('INV-2026-002', {$s2_id}, 'Annual Academic Session Fee 2026-27 (Term 1)', 28000, 4500, 2000, 2500, 3000, 40000, '2026-11-30', 'Paid')");
            $inv2_id = $pdo->lastInsertId();

            $pdo->exec("INSERT INTO fee_payments (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, payment_date, received_by)
                        VALUES ('REC-2026-1042', {$inv2_id}, {$s2_id}, 40000, 'Net Banking', 'HDFC/NET/8726194', '2026-08-20 14:15:00', 1)");
        }

        if ($s3_id) {
            // Invoice 3: Rohan Shinde (Unpaid: Rs. 38,000 pending)
            $pdo->exec("INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
                        VALUES ('INV-2026-003', {$s3_id}, 'Class 9 Enrollment & Term 1 Academic Fee', 26000, 4000, 2000, 3000, 3000, 38000, '2026-11-15', 'Unpaid')");
        }
    }

    // 9. Ensure Assignments & Submissions Exist
    $asgnCount = $pdo->query("SELECT COUNT(*) FROM assignments")->fetchColumn();
    if ($asgnCount == 0) {
        $t1_user_id = $pdo->query("SELECT user_id FROM teachers WHERE teacher_code = 'TCH-201'")->fetchColumn() ?: 1;
        $t2_user_id = $pdo->query("SELECT user_id FROM teachers WHERE teacher_code = 'TCH-202'")->fetchColumn() ?: 1;
        $s1_id = $pdo->query("SELECT id FROM students WHERE student_code = 'STU-1001'")->fetchColumn();

        // Assignment 1: Mathematics by Mrs. Priya Kulkarni
        $asgnStmt = $pdo->prepare("INSERT INTO assignments (teacher_id, class, subject, title, description, due_date, max_marks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $asgnStmt->execute([
            $t1_user_id, 'Class 10-A', 'Mathematics', 'Trigonometry & Quadratic Equations Exercise 4.2',
            'Solve all textbook exercise problems from Chapter 4 and Chapter 8. Clearly show step-by-step derivations for trigonometric identities and quadratic factorizations. Submit scanned PDF or document file.',
            '2026-10-28', 50
        ]);
        $asgn1_id = $pdo->lastInsertId();

        // Assignment 2: Science by Mr. Amit Deshmukh
        $asgnStmt->execute([
            $t2_user_id, 'Class 10-A', 'Science', 'Electromagnetic Induction & Optics Experiment Report',
            "Prepare a detailed laboratory report on Faraday's Law of Electromagnetic Induction with ray diagrams for concave and convex mirrors. Include observational tables and percentage error calculations.",
            '2026-11-05', 40
        ]);

        // Assignment 3: Science for Class 9-B
        $asgnStmt->execute([
            $t2_user_id, 'Class 9-B', 'Science', 'Gravitation & Laws of Motion Worksheet',
            'Complete the 15 numerical problems on Universal Gravitation Constant, free fall, and momentum conservation.',
            '2026-11-02', 25
        ]);

        if ($s1_id) {
            $sample_file = 'uploads/submissions/sample_math_aarav.txt';
            $full_sample_path = __DIR__ . '/../' . $sample_file;
            if (!file_exists($full_sample_path)) {
                @file_put_contents($full_sample_path, "ABC SCHOOL NASHIK\nStudent: Aarav Patil (Roll 1001, Class 10-A)\nSubject: Mathematics Assignment\nSolutions for Chapter 4 & 8 exercises attached.\n- Derivation 1: sin^2(theta) + cos^2(theta) = 1 [Verified]\n- Quadratic Roots: x = (-b +/- sqrt(b^2 - 4ac)) / (2a) [Completed]");
            }

            $subStmt = $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, student_remarks, submitted_at, marks_obtained, teacher_feedback, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $subStmt->execute([
                $asgn1_id, $s1_id, $sample_file,
                "Respected Ma'am, please find attached my solved exercise problems with all proof steps.",
                '2026-10-18 16:45:00', 48,
                'Brilliant work, Aarav! Neat handwriting and accurate proofs.', 'Graded'
            ]);
        }
    }

    // 10. Ensure Timetable Exists
    $ttCount = $pdo->query("SELECT COUNT(*) FROM timetables")->fetchColumn();
    if ($ttCount == 0) {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $slots = [
            [1, '08:30 AM - 09:20 AM', 'Mathematics', 'Mrs. Priya Kulkarni', 'Room 101'],
            [2, '09:20 AM - 10:10 AM', 'Science (Physics)', 'Mr. Amit Deshmukh', 'Physics Lab'],
            [3, '10:25 AM - 11:15 AM', 'English Literature', 'Ms. Sneha Varma', 'Room 101'],
            [4, '11:15 AM - 12:05 PM', 'Social Science', 'Mr. Vivek Joshi', 'Room 101'],
            [5, '12:45 PM - 01:30 PM', 'Computer Science', 'Mr. Rohan Kapse', 'Computer Lab 2'],
            [6, '01:30 PM - 02:15 PM', 'Physical Education', 'Coach Shinde', 'Sports Ground']
        ];

        foreach ($days as $d) {
            foreach ($slots as $s) {
                $subj = $s[2];
                $teach = $s[3];
                if ($d === 'Friday' && $s[0] == 2) { $subj = 'Science (Chemistry Lab)'; }
                if ($d === 'Saturday' && $s[0] >= 5) { continue; }
                $pdo->exec("INSERT INTO timetables (class, day_of_week, period_number, time_slot, subject, teacher_name, room_no)
                            VALUES ('Class 10-A', '{$d}', {$s[0]}, '{$s[1]}', '{$subj}', '{$teach}', '{$s[4]}')");
            }
        }
    }

    // 11. Ensure Leave Applications Exist
    $leaveCount = $pdo->query("SELECT COUNT(*) FROM leave_applications")->fetchColumn();
    if ($leaveCount == 0) {
        $s1_user_id = $pdo->query("SELECT user_id FROM students WHERE student_code = 'STU-1001'")->fetchColumn();
        $t2_user_id = $pdo->query("SELECT user_id FROM teachers WHERE teacher_code = 'TCH-202'")->fetchColumn();

        if ($s1_user_id) {
            $pdo->exec("INSERT INTO leave_applications (user_id, role, leave_type, from_date, to_date, reason, status, admin_remarks, applied_at)
                        VALUES ({$s1_user_id}, 'student', 'Sick Leave', '2026-10-13', '2026-10-13', 'Severe viral fever and doctor recommended 1 day bed rest.', 'Approved', 'Leave granted. Medical certificate acknowledged.', '2026-10-12 18:20:00')");
        }

        if ($t2_user_id) {
            $pdo->exec("INSERT INTO leave_applications (user_id, role, leave_type, from_date, to_date, reason, status, admin_remarks, applied_at)
                        VALUES ({$t2_user_id}, 'teacher', 'Casual Leave', '2026-11-04', '2026-11-05', 'Attending national physics conference at Pune University.', 'Pending', NULL, '2026-10-19 10:15:00')");
        }
    }
}

// Initialize tables and seed on load
init_database($pdo);
?>
