<?php
/**
 * Student Registration Portal
 * ABC School Nashik ERP System
 */
require_once __DIR__ . '/includes/auth.php';

$success = false;
$error = '';
$new_student_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Male');
    $dob = trim($_POST['dob'] ?? '');
    $guardian_name = trim($_POST['guardian_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $blood_group = trim($_POST['blood_group'] ?? 'B+');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($full_name) || empty($email) || empty($username) || empty($class) || empty($password)) {
        $error = 'Please fill in all mandatory fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirmation password do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check if username or email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
        $check->execute(['u' => $username, 'e' => $email]);
        if ($check->fetch()) {
            $error = 'A user with this username or email already exists. Please choose another or login.';
        } else {
            try {
                $pdo->beginTransaction();

                // 1. Create User with role 'student' and status 'pending'
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $uStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:u, :e, :p, 'student', 'pending')");
                $uStmt->execute([
                    'u' => $username,
                    'e' => $email,
                    'p' => $hash
                ]);
                $user_id = $pdo->lastInsertId();

                // 2. Generate Student Code (e.g. STU-2026-XXXX)
                $student_code = 'STU-2026-' . rand(1000, 9999);
                $roll_no = rand(101, 999);

                // 3. Create Student Profile
                $sStmt = $pdo->prepare("INSERT INTO students (user_id, student_code, full_name, gender, dob, class, roll_no, guardian_name, phone, address, blood_group, admission_date) 
                                        VALUES (:uid, :code, :fname, :gender, :dob, :class, :roll, :gname, :phone, :addr, :bg, :adm_date)");
                $sStmt->execute([
                    'uid' => $user_id,
                    'code' => $student_code,
                    'fname' => $full_name,
                    'gender' => $gender,
                    'dob' => $dob,
                    'class' => $class,
                    'roll' => (string)$roll_no,
                    'gname' => $guardian_name,
                    'phone' => $phone,
                    'addr' => $address,
                    'bg' => $blood_group,
                    'adm_date' => date('Y-m-d')
                ]);

                $pdo->commit();

                $success = true;
                $new_student_data = [
                    'code' => $student_code,
                    'name' => $full_name,
                    'email' => $email,
                    'class' => $class,
                    'roll_no' => $roll_no
                ];
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Registration failed due to a server error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Student Registration - ABC School Nashik</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy: {
                            50: '#f0f5ff',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="min-h-full flex flex-col justify-center py-10 px-4 sm:px-6 lg:px-8 bg-slate-100">

    <div class="sm:mx-auto sm:w-full sm:max-w-2xl">
        <!-- Logo -->
        <a href="/" class="flex flex-col items-center mb-6">
            <div class="w-14 h-14 rounded-2xl bg-navy-950 flex items-center justify-center text-white shadow-md mb-2">
                <i class="fa-solid fa-graduation-cap text-2xl text-amber-400"></i>
            </div>
            <h1 class="text-2xl font-black text-navy-950 font-['Outfit']">ABC SCHOOL NASHIK</h1>
            <p class="text-xs font-bold text-amber-600 tracking-wider uppercase">Online Student Admission &amp; ID Portal</p>
        </a>

        <?php if ($success): ?>
            <!-- Success Screen: Clear Notice of Pending Approval -->
            <div class="bg-white rounded-3xl p-8 shadow-xl border border-emerald-200 text-center animate-fade-in">
                <div class="w-16 h-16 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                
                <h2 class="text-2xl font-bold text-slate-900 font-['Outfit']">Application Submitted Successfully!</h2>
                
                <div class="my-5 p-4 rounded-2xl bg-amber-50 border border-amber-300 text-amber-950 text-left text-xs leading-relaxed space-y-2">
                    <div class="flex items-center space-x-2 text-amber-800 font-bold text-sm">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Admin Approval Required Before Login</span>
                    </div>
                    <p>
                        Your student account has been created with status <span class="px-2 py-0.5 rounded bg-amber-200 font-bold text-amber-900">PENDING APPROVAL</span>.
                    </p>
                    <p>
                        In accordance with ABC School Nashik policy, you <strong>will NOT be able to log in</strong> to the student dashboard until the School Administrator reviews your registration and grants approval.
                    </p>
                </div>

                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 text-left text-xs space-y-2 mb-6">
                    <p class="font-bold text-slate-700 uppercase tracking-wider text-[11px] mb-2 border-b border-slate-200 pb-1">Application Summary</p>
                    <div class="grid grid-cols-2 gap-2 text-slate-600">
                        <div>Student Name: <strong class="text-slate-900"><?= htmlspecialchars($new_student_data['name']) ?></strong></div>
                        <div>Generated ID: <strong class="text-blue-700"><?= htmlspecialchars($new_student_data['code']) ?></strong></div>
                        <div>Assigned Class: <strong class="text-slate-900"><?= htmlspecialchars($new_student_data['class']) ?></strong></div>
                        <div>Registered Email: <strong class="text-slate-900"><?= htmlspecialchars($new_student_data['email']) ?></strong></div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/login.php" class="px-6 py-3 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-xs transition shadow-md">
                        <i class="fa-solid fa-right-to-bracket mr-1.5 text-amber-400"></i> Try Login Portal (Test Pending Status)
                    </a>
                    <a href="/" class="px-6 py-3 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold text-xs transition">
                        Return to Homepage
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Registration Form Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-10 shadow-xl border border-slate-200">
                <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900 font-['Outfit']">Student Registration Form</h2>
                        <p class="text-xs text-slate-500">Academic Year 2026 - 2027 Admission</p>
                    </div>
                    <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold">
                        <i class="fa-solid fa-user-lock mr-1"></i> Admin Verification Required
                    </span>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-center space-x-2">
                        <i class="fa-solid fa-circle-exclamation text-base"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <div class="mb-6 p-3.5 rounded-xl bg-blue-50/70 border border-blue-200 text-blue-900 text-xs flex items-start space-x-2.5">
                    <i class="fa-solid fa-circle-info text-blue-600 text-sm mt-0.5 shrink-0"></i>
                    <span><strong>Please Note:</strong> After completing registration, your account status will be set to <em>Pending</em>. The School Admin will verify your information before your login is activated.</span>
                </div>

                <form method="POST" action="register.php" class="space-y-4">
                    <?= csrf_field() ?>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Full Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Full Name of Student <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                   placeholder="e.g. Atharva Deshmukh"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <!-- Class -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Class / Grade Applying For <span class="text-rose-500">*</span>
                            </label>
                            <select name="class" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                                <option value="">Select Grade</option>
                                <option value="Class 8-A" <?= (($_POST['class'] ?? '') === 'Class 8-A') ? 'selected' : '' ?>>Class 8-A</option>
                                <option value="Class 9-A" <?= (($_POST['class'] ?? '') === 'Class 9-A') ? 'selected' : '' ?>>Class 9-A</option>
                                <option value="Class 9-B" <?= (($_POST['class'] ?? '') === 'Class 9-B') ? 'selected' : '' ?>>Class 9-B</option>
                                <option value="Class 10-A" <?= (($_POST['class'] ?? '') === 'Class 10-A') ? 'selected' : '' ?>>Class 10-A</option>
                                <option value="Class 10-B" <?= (($_POST['class'] ?? '') === 'Class 10-B') ? 'selected' : '' ?>>Class 10-B</option>
                                <option value="Class 11-Science" <?= (($_POST['class'] ?? '') === 'Class 11-Science') ? 'selected' : '' ?>>Class 11 - Science</option>
                                <option value="Class 11-Commerce" <?= (($_POST['class'] ?? '') === 'Class 11-Commerce') ? 'selected' : '' ?>>Class 11 - Commerce</option>
                                <option value="Class 12-Science" <?= (($_POST['class'] ?? '') === 'Class 12-Science') ? 'selected' : '' ?>>Class 12 - Science</option>
                                <option value="Class 12-Commerce" <?= (($_POST['class'] ?? '') === 'Class 12-Commerce') ? 'selected' : '' ?>>Class 12 - Commerce</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Email Address <span class="text-rose-500">*</span>
                            </label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   placeholder="student@example.com"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <!-- Desired Username -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Desired Username <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   placeholder="e.g. atharva.deshmukh"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Gender -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Gender</label>
                            <select name="gender" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Date of Birth -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Date of Birth</label>
                            <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '2010-05-15') ?>"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        </div>

                        <!-- Blood Group -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Blood Group</label>
                            <select name="blood_group" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                                <option value="A+">A+</option>
                                <option value="B+" selected>B+</option>
                                <option value="O+">O+</option>
                                <option value="AB+">AB+</option>
                                <option value="A-">A-</option>
                                <option value="B-">B-</option>
                                <option value="O-">O-</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Guardian Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Father / Mother / Guardian Name</label>
                            <input type="text" name="guardian_name" value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>"
                                   placeholder="e.g. Ramesh Deshmukh"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Emergency Contact Phone</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   placeholder="+91 98XXX XXXXX"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none">
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Residential Address (Nashik)</label>
                        <textarea name="address" rows="2" placeholder="e.g. Plot No. 12, Suyojit Garden, Gangapur Road, Nashik"
                                  class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <!-- Password -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Create Account Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="password" required placeholder="Min 6 characters"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>

                        <!-- Confirm Password -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                                Confirm Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="confirm_password" required placeholder="Re-type password"
                                   class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-slate-50 text-sm focus:outline-none focus:ring-2 focus:ring-navy-600 transition">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-navy-800 hover:bg-navy-900 text-white font-bold text-sm shadow-lg shadow-navy-900/20 transition flex items-center justify-center space-x-2">
                            <i class="fa-solid fa-user-check text-amber-400"></i>
                            <span>Register &amp; Submit for Admin Approval</span>
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-4 border-t border-slate-200 text-center text-xs text-slate-500">
                    Already registered and approved?
                    <a href="/login.php" class="font-bold text-navy-800 hover:text-navy-950 underline ml-1">Go to Login &rarr;</a>
                </div>
            </div>

        <?php endif; ?>

    </div>

</body>
</html>
