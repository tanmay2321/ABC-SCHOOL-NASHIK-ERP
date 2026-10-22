<?php
/**
 * Database Demo Seeder for Web & Mobile App Testing
 * Bellwether International School Nandurbar ERP
 */
require_once __DIR__ . '/../config/db.php';

echo "=== Seeding Demo Data for Bellwether International School Nandurbar ERP ===\n";

// 1. Ensure Principal Account
$check = $pdo->prepare("SELECT id FROM users WHERE email = 'principal@edu.in' LIMIT 1");
$check->execute();
if (!$check->fetch()) {
    $hash = password_hash('Principal@123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('principal', 'principal@edu.in', :p, 'principal', 'approved')")
        ->execute(['p' => $hash]);
    echo "[+] Created Principal: principal@edu.in / Principal@123\n";
}

// 2. Ensure Teacher Account
$check = $pdo->prepare("SELECT id FROM users WHERE email = 'teacher@edu.in' LIMIT 1");
$check->execute();
$teacher_user = $check->fetch();
if (!$teacher_user) {
    $hash = password_hash('Teacher@123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('teacher.patil', 'teacher@edu.in', :p, 'teacher', 'approved')")
        ->execute(['p' => $hash]);
    $t_uid = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO teachers (user_id, teacher_code, full_name, gender, department, designation, qualification, phone, assigned_class) 
        VALUES (:uid, 'TCH-1001', 'Prof. Arvind Patil', 'Male', 'Science & Mathematics', 'Senior Class Educator', 'M.Sc., B.Ed.', '+91 94222 84461', 'Class 10')")
        ->execute(['uid' => $t_uid]);
    echo "[+] Created Teacher: teacher@edu.in / Teacher@123 (Class Teacher of Class 10)\n";
} else {
    $t_uid = $teacher_user['id'];
}

// 3. Ensure Student Account
$check = $pdo->prepare("SELECT id FROM users WHERE email = 'student@edu.in' LIMIT 1");
$check->execute();
$student_user = $check->fetch();
if (!$student_user) {
    $hash = password_hash('Student@123', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES ('atharva.sharma', 'student@edu.in', :p, 'student', 'approved')")
        ->execute(['p' => $hash]);
    $s_uid = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date)
        VALUES (:uid, 'STU-2026-1025', 'Atharva Sharma', 'Male', '2010-04-15', 'Class 10', '101', 'Rajesh Sharma', '+91 98231 65432', 'Flat 402, Shiv Plaza, Old Nalwa Road, Nandurbar', 'O+', '2026-06-15')")
        ->execute(['uid' => $s_uid]);
    $student_id = $pdo->lastInsertId();
    echo "[+] Created Student: student@edu.in / Student@123 (Atharva Sharma, Class 10)\n";
} else {
    $s_uid = $student_user['id'];
    $sRow = $pdo->query("SELECT id FROM students WHERE user_id = {$s_uid}")->fetch();
    $student_id = $sRow['id'] ?? 1;
}

// 4. Ensure Teacher profile ID
$tRow = $pdo->query("SELECT id FROM teachers WHERE user_id = {$t_uid}")->fetch();
$teacher_id = $tRow['id'] ?? 1;

// 5. Seed Attendance for this student if empty
$attCount = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = :sid");
$attCount->execute(['sid' => $student_id]);
if ($attCount->fetchColumn() == 0) {
    $ins = $pdo->prepare("INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES (:sid, :dt, :st, :rem, :mby)");
    for ($i = 0; $i < 24; $i++) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dayName = date('N', strtotime($date));
        if ($dayName == 7) continue; // Skip Sunday
        $status = ($i == 4 || $i == 17) ? 'Absent' : (($i == 9) ? 'Late' : 'Present');
        $remarks = ($status == 'Absent') ? 'Medical Leave' : (($status == 'Late') ? 'Bus delayed' : 'Regular Attendance');
        $ins->execute([
            'sid' => $student_id,
            'dt' => $date,
            'st' => $status,
            'rem' => $remarks,
            'mby' => $t_uid
        ]);
    }
    echo "[+] Seeded 20+ Attendance days for student (Present, Absent, Late)\n";
}

// 6. Seed Timetable for Class 10 if empty
$ttCount = $pdo->query("SELECT COUNT(*) FROM timetables WHERE class = 'Class 10'")->fetchColumn();
if ($ttCount == 0) {
    $schedule = [
        ['Monday', 1, '08:00 AM - 08:45 AM', 'Mathematics', 'Prof. Arvind Patil', 'Room 101'],
        ['Monday', 2, '08:45 AM - 09:30 AM', 'English Literature', 'Mrs. Sunita Rao', 'Room 101'],
        ['Monday', 3, '09:45 AM - 10:30 AM', 'Science & Physics', 'Prof. Arvind Patil', 'Lab 2'],
        ['Monday', 4, '10:30 AM - 11:15 AM', 'Social Studies & Civics', 'Mr. D. S. More', 'Room 101'],
        ['Monday', 5, '11:45 AM - 12:30 PM', 'Computer Applications', 'Er. Vivek Kulkarni', 'Computer Lab'],
        ['Monday', 6, '12:30 PM - 01:15 PM', 'Physical Education', 'Coach S. Jadhav', 'Playground'],
        ['Tuesday', 1, '08:00 AM - 08:45 AM', 'Science & Chemistry', 'Prof. Arvind Patil', 'Lab 1'],
        ['Tuesday', 2, '08:45 AM - 09:30 AM', 'Mathematics', 'Prof. Arvind Patil', 'Room 101'],
        ['Tuesday', 3, '09:45 AM - 10:30 AM', 'Hindi Language', 'Mrs. M. V. Joshi', 'Room 101'],
        ['Tuesday', 4, '10:30 AM - 11:15 AM', 'English Grammar', 'Mrs. Sunita Rao', 'Room 101'],
        ['Wednesday', 1, '08:00 AM - 08:45 AM', 'Mathematics', 'Prof. Arvind Patil', 'Room 101'],
        ['Wednesday', 2, '08:45 AM - 09:30 AM', 'Science & Biology', 'Prof. Arvind Patil', 'Lab 2'],
        ['Thursday', 1, '08:00 AM - 08:45 AM', 'Social Studies & History', 'Mr. D. S. More', 'Room 101'],
        ['Thursday', 2, '08:45 AM - 09:30 AM', 'Mathematics', 'Prof. Arvind Patil', 'Room 101'],
        ['Friday', 1, '08:00 AM - 08:45 AM', 'Science Practical', 'Prof. Arvind Patil', 'Lab 1'],
        ['Friday', 2, '08:45 AM - 09:30 AM', 'Library & Reading', 'Mrs. Sunita Rao', 'Library'],
        ['Saturday', 1, '08:00 AM - 09:00 AM', 'Art & Craft Club', 'Mr. K. R. Salunke', 'Art Studio'],
        ['Saturday', 2, '09:00 AM - 10:00 AM', 'Sports & Yoga Drill', 'Coach S. Jadhav', 'Main Grounds']
    ];
    $ttIns = $pdo->prepare("INSERT INTO timetables (class, day_of_week, period_number, time_slot, subject, teacher_name, room_no) VALUES ('Class 10', :day, :pnum, :tslot, :sub, :tname, :room)");
    foreach ($schedule as $s) {
        $ttIns->execute([
            'day' => $s[0],
            'pnum' => $s[1],
            'tslot' => $s[2],
            'sub' => $s[3],
            'tname' => $s[4],
            'room' => $s[5]
        ]);
    }
    echo "[+] Seeded Class 10 Timetable (Monday to Saturday)\n";
}

// 7. Seed Fee Invoices if empty
$feeCount = $pdo->prepare("SELECT COUNT(*) FROM fee_invoices WHERE student_id = :sid");
$feeCount->execute(['sid' => $student_id]);
if ($feeCount->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
        VALUES ('INV-2026-101', :sid, 'First Term Academic Fee 2026-27', 18000, 3500, 1500, 2000, 1000, 26000, '2026-11-15', 'Unpaid')")
        ->execute(['sid' => $student_id]);

    $pdo->prepare("INSERT INTO fee_invoices (invoice_no, student_id, title, tuition_fee, lab_fee, library_fee, sports_fee, exam_fee, total_amount, due_date, status)
        VALUES ('INV-2026-088', :sid, 'Admission & Annual Registration Fee', 10000, 0, 1000, 1500, 500, 13000, '2026-06-30', 'Paid')")
        ->execute(['sid' => $student_id]);
    $paid_id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO fee_payments (receipt_no, invoice_id, student_id, amount_paid, payment_mode, transaction_ref, payment_date, received_by)
        VALUES ('REC-2026-0982', :iid, :sid, 13000, 'UPI', 'TXN998471203', '2026-06-28 11:20:00', 1)")
        ->execute(['iid' => $paid_id, 'sid' => $student_id]);

    echo "[+] Seeded Fee Invoices (Unpaid Term 1 & Paid Admission Receipt)\n";
}

// 8. Seed Assignments if empty
$asgnCount = $pdo->query("SELECT COUNT(*) FROM assignments WHERE class = 'Class 10'")->fetchColumn();
if ($asgnCount == 0) {
    $pdo->prepare("INSERT INTO assignments (teacher_id, class, subject, title, description, due_date, max_marks) 
        VALUES (:tid, 'Class 10', 'Mathematics', 'Quadratic Equations & Arithmetic Progressions Exercise', 'Solve NCERT Chapter 4 Questions 1 to 15 with complete step-by-step proofs.', '2026-10-30', 25)")
        ->execute(['tid' => $teacher_id]);
    $a1 = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO assignments (teacher_id, class, subject, title, description, due_date, max_marks) 
        VALUES (:tid, 'Class 10', 'Science & Physics', 'Light Reflection and Refraction Ray Diagrams', 'Draw spherical mirror ray diagrams and compute focal length problems.', '2026-11-05', 20)")
        ->execute(['tid' => $teacher_id]);

    // Student has already submitted assignment 1
    $pdo->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, student_remarks, status, marks_obtained, teacher_feedback)
        VALUES (:aid, :sid, '/uploads/submissions/sample_math_solution.pdf', 'All questions solved with diagram proofs.', 'Graded', 24, 'Excellent mathematical presentation and clarity.')")
        ->execute(['aid' => $a1, 'sid' => $student_id]);

    echo "[+] Seeded Assignments & Solutions\n";
}

// 9. Seed Study Notes if empty
$notesCount = $pdo->query("SELECT COUNT(*) FROM study_notes WHERE class = 'Class 10'")->fetchColumn();
if ($notesCount == 0) {
    $notes = [
        ['Class 10', 'Mathematics', 'Real Numbers & Polynomials Formula Handbook', 'Concise summary of Euclid division lemma, theorem proofs, and quick tricks.', 'pdf'],
        ['Class 10', 'Science & Physics', 'Electricity & Magnetic Effects Study Notes', 'Ohms law, circuit diagrams, resistance formulas, and numerical practice set.', 'pdf'],
        ['Class 10', 'English Literature', 'First Flight Poetry Analysis & Question Bank', 'Summary, poetic devices, character sketches, and previous year board questions.', 'pdf']
    ];
    $nIns = $pdo->prepare("INSERT INTO study_notes (teacher_id, class, subject, title, description, file_path, file_name, file_size, file_type, academic_year)
        VALUES (:tid, :cls, :sub, :title, :desc, :fpath, :fname, :fsize, :ftype, '2026-2027')");
    foreach ($notes as $n) {
        $nIns->execute([
            'tid' => $teacher_id,
            'cls' => $n[0],
            'sub' => $n[1],
            'title' => $n[2],
            'desc' => $n[3],
            'fpath' => '/uploads/notes/bwis_sample_' . strtolower(substr($n[1], 0, 4)) . '.pdf',
            'fname' => 'BWIS_' . str_replace(' ', '_', $n[1]) . '_Notes.pdf',
            'fsize' => 2048500,
            'ftype' => $n[4]
        ]);
    }
    echo "[+] Seeded Study Notes for Class 10\n";
}

// 10. Seed Grades & Marksheet if empty
$gradeCount = $pdo->prepare("SELECT COUNT(*) FROM grades WHERE student_id = :sid");
$gradeCount->execute(['sid' => $student_id]);
if ($gradeCount->fetchColumn() == 0) {
    $gradesData = [
        ['Mid-Term Examination 2026', 'Mathematics', 94, 100, 'A+', 'Outstanding problem-solving skills'],
        ['Mid-Term Examination 2026', 'Science & Physics', 91, 100, 'A+', 'Very good in practical and concepts'],
        ['Mid-Term Examination 2026', 'English Literature', 87, 100, 'A', 'Strong vocabulary and composition'],
        ['Mid-Term Examination 2026', 'Social Studies', 89, 100, 'A', 'Well structured map work'],
        ['Mid-Term Examination 2026', 'Computer Applications', 96, 100, 'A+', 'Mastery in logic and algorithms'],
        ['Unit Test 1', 'Mathematics', 24, 25, 'A+', 'Top performer in class'],
        ['Unit Test 1', 'Science & Physics', 23, 25, 'A+', 'Precise formula applications']
    ];
    $gIns = $pdo->prepare("INSERT INTO grades (student_id, subject, exam_name, marks_obtained, max_marks, grade, remarks, teacher_id)
        VALUES (:sid, :sub, :exam, :marks, :maxm, :grd, :rem, :tid)");
    foreach ($gradesData as $g) {
        $gIns->execute([
            'sid' => $student_id,
            'sub' => $g[1],
            'exam' => $g[0],
            'marks' => $g[2],
            'maxm' => $g[3],
            'grd' => $g[4],
            'rem' => $g[5],
            'tid' => $teacher_id
        ]);
    }
    echo "[+] Seeded Mid-Term & Unit Test Marksheet (Average 91.4%)\n";
}

// 11. Seed Notices if empty
$noticesCount = $pdo->query("SELECT COUNT(*) FROM notices")->fetchColumn();
if ($noticesCount == 0) {
    $notices = [
        ['Annual Sports Meet & Inter-House Championship 2026', 'The Annual Athletics and Inter-House Sports Championship will be held on 18th November 2026 on the main campus sports ground. Parents are cordially invited.', 'all', 'Sports'],
        ['Mid-Term Examination Marksheets & Parent-Teacher Meeting (PTM)', 'Parent-Teacher Meeting for Class 1 to 12 will take place on Saturday from 09:00 AM to 01:00 PM. Digital marksheets are available on the mobile app.', 'all', 'Academic'],
        ['Diwali Vacation Schedule & Campus Re-opening Circular', 'The school will observe Diwali vacations from 28th October to 10th November 2026. All academic classes will resume in full uniform on 11th November.', 'all', 'Holidays']
    ];
    $notIns = $pdo->prepare("INSERT INTO notices (title, content, audience, category, posted_by) VALUES (:t, :c, :a, :cat, 1)");
    foreach ($notices as $nt) {
        $notIns->execute(['t' => $nt[0], 'c' => $nt[1], 'a' => $nt[2], 'cat' => $nt[3]]);
    }
    echo "[+] Seeded School Notices & Circulars\n";
}

echo "=== Seeding Complete Successfully! ===\n";
