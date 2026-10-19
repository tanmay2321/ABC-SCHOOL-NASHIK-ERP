# ABC School Nashik - Enterprise Resource Planning (ERP) System

A modern, responsive, full-featured web-based School ERP platform built with **PHP** and **SQLite (PDO)**. Engineered for **ABC Senior Secondary School, Nashik (CBSE Affiliated)**.

---

## 🌟 Key Features

### 1. Three Dedicated Dashboards
1. **Administrator Dashboard (`/admin/index.php`)**:
   - **Create & Manage Teachers**: Admin can create new teacher accounts with unique Teacher IDs (e.g. `TCH-201`), assign departments and designations, edit profile details, or remove accounts.
   - **Student Approvals Queue**: Students registering online are automatically placed in `Pending` status. Admin has exclusive authority to review and **Approve** or **Reject** registrations.
   - **Full Access Privileges**: Admin can view, edit all student attributes (Class, Roll No, Guardian, Contact, Address, Status), and reset passwords.
   - **Notice Board Management**: Create, view, and broadcast announcements categorized as Circulars, Exams, Events, or Holidays.
   - **System Metrics**: Live counts of enrolled students, qualified faculty, and pending admissions.

2. **Faculty / Teacher Dashboard (`/teacher/index.php`)**:
   - **Student Class Roster**: Filter students by class (Class 9, Class 10, Class 11, etc.) with guardian details and roll numbers.
   - **Attendance Register**: Daily roll call system (Present, Absent, Late) with one-click "Mark All Present" shortcut.
   - **Marks & Assessment Entry**: Enter test and exam marks (Mid-Term, Unit Tests). Automatically calculates score percentages and letter grades (`A+` to `F`).
   - **Staff Circulars**: Access school-wide and teacher-specific circulars.

3. **Student & Parent Dashboard (`/student/index.php`)**:
   - **Pending Approval Gate**: Unapproved students are blocked from logging in with a clear, informative message until verified by the School Admin.
   - **Live Attendance Meter**: View overall attendance percentage with CBSE 75% threshold compliance indicator and day-by-day logs.
   - **Official Digital Report Card**: Complete statement of marks with Grand Total, GPA, CBSE grading scale, and print-ready printable certificate.
   - **School Notices**: View circulars, upcoming holidays, and exam alerts.

---

## 🔐 Pre-Seeded Demo Credentials

The system comes pre-populated with ready-to-use demo accounts. On the **Login Page** (`/login.php`), there are **one-click buttons** to test each role instantly:

| Role | Username / Email | Password | Status | Notes |
|---|---|---|---|---|
| **Administrator** | `admin@abcschool.edu` | `admin123` | Approved | Full administrative access |
| **Teacher (Maths)** | `priya.kulkarni@abcschool.edu` | `teacher123` | Approved | HOD Mathematics |
| **Teacher (Science)** | `amit.deshmukh@abcschool.edu` | `teacher123` | Approved | PGT Physics & Science |
| **Student (Approved)** | `aarav.patil@student.abc.edu` | `student123` | Approved | Class 10-A, Roll 1001 (Can log in) |
| **Student (Pending)** | `rohan.shinde@student.abc.edu` | `student123` | **Pending** | Class 9-B (**Test blocked login!**) |

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
│   ├── notices.php             # Publish and manage school circulars
│   └── settings.php            # ERP institutional profile & system info
├── teacher/
│   ├── index.php               # Faculty dashboard overview & quick launchers
│   ├── students.php            # Class-wise student directory
│   ├── attendance.php          # Daily roll call register with bulk mark-present tool
│   ├── marks.php               # Gradebook and test scores evaluation
│   └── notices.php             # Teacher circulars
├── student/
│   ├── index.php               # Student portal overview & quick metrics
│   ├── attendance.php          # Detailed attendance history & 75% compliance meter
│   ├── report-card.php         # Official digital printable term report card
│   └── notices.php             # Student circulars feed
├── assets/
│   ├── css/custom.css          # Glassmorphism, fonts, badge animations, and print styling
│   └── js/app.js               # Modal controllers, dynamic table search, and UI interactions
├── index.php                   # Public ABC School Nashik landing page with quick portal cards
├── login.php                   # Unified login with pending approval blocker & 1-click demo logins
├── register.php                # Student self-registration form (sets status to 'pending')
├── logout.php                  # Secure session termination
├── run_server.bat              # Quick 1-click Windows runner
└── database.sqlite             # Auto-provisioned SQLite database file
```
