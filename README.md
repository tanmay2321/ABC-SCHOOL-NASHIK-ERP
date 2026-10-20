# ABC School Nashik - Enterprise Resource Planning (ERP) System

A modern, responsive, production-ready School ERP platform built with **PHP 8.2** and **SQLite (PDO)** with Tailwind CSS and FontAwesome icons. Specially engineered for **ABC Senior Secondary School, Nashik (CBSE Affiliated)**.

---

## 🌟 Core System Architecture

### 1. Three Dedicated Dashboards
1. **Administrator Dashboard (`/admin/index.php`)**:
   - **Create & Manage Teachers**: Admin can create new teacher accounts with unique Teacher IDs (e.g. `TCH-201`), assign departments and designations, edit profile details, or remove accounts.
   - **Student Approvals Queue**: Students registering online are automatically placed in `Pending` status. Admin has exclusive authority to review and **Approve** or **Reject** registrations.
   - **Full Access Privileges**: Admin can view, edit all student attributes (Class, Roll No, Guardian, Contact, Address, Status), and reset passwords.
   - **Notice Board Management**: Create, view, and broadcast announcements categorized as Circulars, Exams, Events, or Holidays.
   - **Fee Accounting & Invoicing**: Generate structured fee invoices, record UPI/Cash/Card payments, view real-time collections vs pending dues.
   - **Class Timetable Management**: Allocate weekly period schedules (Mon-Sat), assign subject teachers, and allocate rooms.
   - **Leave Approvals**: Review student and faculty leave requests with 1-click Approve or Reject and administrative remarks.

2. **Faculty / Teacher Dashboard (`/teacher/index.php`)**:
   - **Student Class Roster**: Filter students by class (Class 9, Class 10, etc.) with guardian details and roll numbers.
   - **Attendance Register**: Daily roll call system (Present, Absent, Late) with one-click "Mark All Present" shortcut.
   - **Marks & Assessment Entry**: Enter test and exam marks (Mid-Term, Unit Tests). Automatically calculates score percentages and letter grades (`A+` to `F`).
   - **Assignments & Homework Creator**: Post homework tasks with deadlines, maximum marks, and optional question sheet attachments.
   - **Evaluate Student Submissions**: View student-uploaded solution files, download them, and submit marks and personalized feedback.
   - **Teaching Timetable**: View weekly periods assigned to the teacher, daily lecture load, and classroom numbers.
   - **Leave Application**: Apply for Casual, Medical, or Academic leaves and track approval status.

3. **Student & Parent Dashboard (`/student/index.php`)**:
   - **Pending Approval Gate**: Unapproved students are strictly blocked from logging in until verified and approved by the School Admin.
   - **Fee Portal & Payment Receipts**: View total academic fee, amount paid, and live pending dues. View individual fee components (Tuition, Lab, Library, Sports, Exam fee), download/print official receipts, and simulate online payments.
   - **Homework & Assignments Submission**: Access assigned tasks, download teacher question sheets, upload homework solution files (PDF, DOCX, Images, ZIP) with notes, and receive grades and teacher feedback.
   - **Live Attendance Meter**: View overall attendance percentage with CBSE 75% threshold compliance indicator and day-by-day logs.
   - **Class Timetable**: View day-by-day schedule of periods (Monday to Saturday) with subjects, teacher names, and room numbers.
   - **Leave Applications**: Submit leave requests with reasons and date ranges, adhering to CBSE attendance policies.
   - **Official Digital Report Card**: Complete statement of marks with Grand Total, GPA, CBSE grading scale, and print-ready printable certificate.

---

## 🔐 Clean System Credentials

The system is delivered **100% fresh with zero test/demo data**. All tables are clean, empty, and ready for production use.

Log in using the master Administrator credentials:

| Role | Username / Email | Password | Access Level |
|---|---|---|---|
| **System Administrator** | `admin@edu.in` | `Admin@1234` | Full Master Control |

### 📋 Getting Started Flow:
1. **Log in as Admin** at `/login.php` with `admin@edu.in` / `Admin@1234`.
2. **Create Teachers**: Go to **Teachers Management** (`/admin/teachers.php`) &rarr; click **Add Teacher ID** to create faculty login accounts.
3. **Student Admissions**: Students register on the public portal via **Register for New ID** (`/register.php`).
4. **Approve Students**: Newly registered students enter the **Student Approvals Queue** in the Admin Dashboard (`/admin/students.php`). The Admin approves them with one click, after which the students can immediately log in and access their portal.

---

## 🚀 How to Run the Website

### Option 1: Double Click `run_server.bat`
Simply double-click `run_server.bat` in the project directory. It detects XAMPP's PHP installation and starts the server on `http://localhost:8000`.

### Option 2: Command Line (PowerShell / Terminal)
```powershell
& "C:\xampp\php\php.exe" -S localhost:8000 -t "C:\Users\USER\.gemini\antigravity\scratch\abc-school-erp"
```
Then visit:
👉 **http://localhost:8000**

---

## 📁 Project Structure

```
abc-school-erp/
├── config/
│   └── db.php                  # PDO connection to SQLite with auto-table initialization and seeders
├── includes/
│   ├── auth.php                # Authentication, role validation & session security helpers
│   ├── header.php              # Global responsive header with user chip & notification badges
│   ├── sidebar.php             # Role-specific sidebar navigation
│   └── footer.php              # Shared dashboard footer and scripts
├── admin/
│   ├── index.php               # Admin overview with pending student approvals queue & KPIs
│   ├── teachers.php            # Create Teacher IDs, edit faculty details, delete faculty
│   ├── students.php            # Approve/reject registrations, edit student details, full access
│   ├── fees.php                # Fee management, invoice creation, and payment recording
│   ├── timetable.php           # Class weekly schedule manager and period allocator
│   ├── leaves.php              # Review and approve/reject leave applications
│   ├── notices.php             # Publish and manage school circulars
│   └── settings.php            # ERP institutional profile & system info
├── teacher/
│   ├── index.php               # Faculty dashboard overview & quick launchers
│   ├── students.php            # Class-wise student directory
│   ├── attendance.php          # Daily roll call register with bulk mark-present tool
│   ├── marks.php               # Gradebook and test scores evaluation
│   ├── assignments.php         # Create homework, review submissions, and enter marks
│   ├── timetable.php           # Teacher weekly lecture schedule
│   ├── leaves.php              # Faculty leave applications
│   └── notices.php             # Teacher circulars
├── student/
│   ├── index.php               # Student portal overview & quick metrics
│   ├── fees.php                # Fee portal: dues, invoice breakdown, digital receipts, payment
│   ├── assignments.php         # Homework portal: upload solution files, view marks & feedback
│   ├── attendance.php          # Detailed attendance history & 75% compliance meter
│   ├── timetable.php           # Student class weekly schedule
│   ├── leaves.php              # Student leave application
│   ├── report-card.php         # Official digital printable term report card
│   └── notices.php             # Student circulars feed
├── uploads/
│   ├── assignments/            # Question sheets uploaded by teachers
│   └── submissions/            # Solution documents uploaded by students
├── assets/
│   ├── css/custom.css          # Glassmorphism, fonts, badge animations, and print styling
│   └── js/app.js               # Modal controllers, dynamic table search, and UI interactions
├── index.php                   # Public ABC School Nashik landing page with quick portal cards
├── login.php                   # Unified login with pending approval blocker & 1-click demo logins
├── register.php                # Student self-registration form (sets status to 'pending')
├── logout.php                  # Secure session termination
├── run_server.bat              # Quick 1-click Windows runner
├── push_to_github.bat          # Automated GitHub repository synchronization script
└── database.sqlite             # Auto-provisioned SQLite database file
```

---

## 🏫 Real-World Working Modules Detail

1. **Assignments & Homework**:
   - Teachers select class, subject, title, instructions, submission deadline, max marks, and optional attachment.
   - Students see all homework for their class, download question papers, upload solution files (PDF, DOCX, TXT, images, ZIP), and leave remarks.
   - Teachers review student submissions, download uploaded files, input marks out of max marks, and leave remarks that appear in the student's portal.

2. **Fee Accounting & Billing**:
   - Detailed breakdown into Tuition Fee, Computer/Lab Fee, Library Fee, Sports/Gymkhana, and Examination Fee.
   - Live balance calculation: `Total Invoiced - Total Paid = Pending Balance`.
   - Students can simulate online UPI/Net Banking payment, which instantly updates invoice status to `Paid` or `Partial`.
   - Official printable digital fee receipt with transaction reference number, student ID, and school seal.

3. **Timetables & Weekly Schedules**:
   - 6 periods per day (Monday to Saturday) with standard school timings (08:30 AM to 02:15 PM).
   - Admin manages slots per class and assigns subject faculty and room/lab numbers.
   - Teachers view their personalized schedule highlighting today's lectures.
   - Students view their class schedule with classroom locations.

4. **Leave Management & Approvals**:
   - Students apply for Sick Leave, Family Event, or Personal absence.
   - Faculty apply for Casual Leave, Medical Leave, Academic/Duty Leave, or Earned Leave.
   - Admin dashboard features a dedicated leave review queue with 1-click Approve or Reject and administrative feedback.
