<?php
/**
 * ERP Login Portal - ABC School Nashik
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header("Location: /admin/index.php");
    elseif ($role === 'teacher') header("Location: /teacher/index.php");
    else header("Location: /student/index.php");
    exit();
}

$error_message = '';
$warning_message = '';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_input) || empty($password)) {
        $error_message = 'Please provide both username/email and password.';
    } else {
        // Query user by email OR username
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :input OR username = :input LIMIT 1");
        $stmt->execute(['input' => $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Verify status if role is student
            if ($user['role'] === 'student') {
                if ($user['status'] === 'pending') {
                    $warning_message = "<strong>Account Pending Approval:</strong> Your registration has been received, but you <strong>cannot log in</strong> until the School Admin reviews and approves your account.<br><br><span class='text-xs opacity-90'>Please contact ABC School administration or wait for approval.</span>";
                } elseif ($user['status'] === 'rejected') {
                    $error_message = "Your student registration was reviewed and <strong>rejected</strong> by the administration. Please visit the school office.";
                } elseif ($user['status'] === 'inactive') {
                    $error_message = "Your student account is currently deactivated. Please contact the administrator.";
                } else {
                    // Approved student - fetch student profile details
                    $sStmt = $pdo->prepare("SELECT * FROM students WHERE user_id = :uid LIMIT 1");
                    $sStmt->execute(['uid' => $user['id']]);
                    $student = $sStmt->fetch();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['status'] = $user['status'];
                    $_SESSION['full_name'] = $student['full_name'] ?? $user['username'];
                    $_SESSION['profile_id'] = $student['id'] ?? null;
                    $_SESSION['code'] = $student['student_code'] ?? '';
                    $_SESSION['class'] = $student['class'] ?? '';

                    header("Location: /student/index.php");
                    exit();
                }
            } elseif ($user['role'] === 'teacher') {
                if ($user['status'] === 'inactive') {
                    $error_message = "Your teacher account is deactivated. Please contact the school admin.";
                } else {
                    $tStmt = $pdo->prepare("SELECT * FROM teachers WHERE user_id = :uid LIMIT 1");
                    $tStmt->execute(['uid' => $user['id']]);
                    $teacher = $tStmt->fetch();

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['status'] = $user['status'];
                    $_SESSION['full_name'] = $teacher['full_name'] ?? $user['username'];
                    $_SESSION['profile_id'] = $teacher['id'] ?? null;
                    $_SESSION['code'] = $teacher['teacher_code'] ?? '';
                    $_SESSION['department'] = $teacher['department'] ?? '';

                    header("Location: /teacher/index.php");
                    exit();
                }
            } elseif ($user['role'] === 'admin') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = 'approved';
                $_SESSION['full_name'] = 'Principal / Head Admin';

                header("Location: /admin/index.php");
                exit();
            }
        } else {
            $error_message = 'Invalid credentials. Please verify your email/username and password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Login - ABC School Nashik</title>
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
<body class="h-full flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-gradient-to-tr from-slate-100 via-blue-50/50 to-slate-200">

    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <!-- Logo -->
        <a href="/" class="flex flex-col items-center group">
            <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-navy-950 to-navy-700 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 group-hover:scale-105 transition-transform mb-3">
                <i class="fa-solid fa-school text-3xl text-amber-400"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-navy-950 font-['Outfit'] tracking-tight">ABC SCHOOL NASHIK</h2>
            <p class="text-xs font-bold tracking-widest text-amber-600 uppercase mt-0.5">Enterprise Resource Planning (ERP)</p>
        </a>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-lg">
        <div class="bg-white py-8 px-6 shadow-xl shadow-slate-200/60 rounded-3xl sm:px-10 border border-slate-200/80">
            
            <div class="mb-6 text-center">
                <h3 class="text-lg font-bold text-slate-900 font-['Outfit']">Sign In to Your Account</h3>
                <p class="text-xs text-slate-500 mt-1">Select or enter your credentials for Admin, Teacher, or Student</p>
            </div>

            <!-- Flash & Error alerts -->
            <?php if ($flash): ?>
                <div class="mb-5 p-4 rounded-2xl text-xs font-medium bg-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-50 border border-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-200 text-<?= $flash['type'] === 'danger' ? 'rose' : 'emerald' ?>-800 flex items-start space-x-2.5">
                    <i class="fa-solid <?= $flash['type'] === 'danger' ? 'fa-circle-xmark' : 'fa-circle-check' ?> text-base mt-0.5"></i>
                    <div><?= $flash['message'] ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($warning_message)): ?>
                <div class="mb-5 p-4 rounded-2xl text-xs bg-amber-50 border border-amber-200 text-amber-900 flex items-start space-x-3 shadow-sm">
                    <div class="p-2 rounded-xl bg-amber-100 text-amber-700 shrink-0">
                        <i class="fa-solid fa-clock text-lg"></i>
                    </div>
                    <div class="leading-relaxed">
                        <?= $warning_message ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="mb-5 p-4 rounded-2xl text-xs bg-rose-50 border border-rose-200 text-rose-800 flex items-start space-x-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-base mt-0.5 shrink-0"></i>
                    <div><?= $error_message ?></div>
                </div>
            <?php endif; ?>

            <form class="space-y-4" method="POST" action="login.php">
                <div>
                    <label for="login_input" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Username or Email Address
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope text-sm"></i>
                        </div>
                        <input id="login_input" name="login_input" type="text" required 
                               value="<?= htmlspecialchars($_POST['login_input'] ?? '') ?>"
                               placeholder="e.g. admin@abcschool.edu or student roll" 
                               class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Password
                        </label>
                        <span class="text-xs text-slate-400">Default passwords provided below</span>
                    </div>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input id="password" name="password" type="password" required 
                               placeholder="••••••••" 
                               class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 transition">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-md text-sm font-bold text-white bg-navy-800 hover:bg-navy-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-navy-600 transition-all">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2 text-amber-400"></i>
                        Sign In to ERP
                    </button>
                </div>
            </form>

            <!-- Test Demo Accounts One-Click Fillers -->
            <div class="mt-8 pt-6 border-t border-slate-200">
                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider text-center mb-3">
                    <i class="fa-solid fa-flask-vial mr-1 text-amber-500"></i> Instant One-Click Demo Logins
                </p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <!-- Admin -->
                    <button type="button" onclick="fillCredentials('admin@abcschool.edu', 'admin123')" 
                            class="p-2.5 rounded-xl border border-purple-200 bg-purple-50 hover:bg-purple-100 text-purple-900 font-semibold text-left transition flex items-center space-x-2">
                        <i class="fa-solid fa-shield-halved text-purple-600"></i>
                        <div>
                            <div class="text-[11px] font-bold">Admin</div>
                            <div class="text-[10px] text-purple-700">Full Access</div>
                        </div>
                    </button>

                    <!-- Teacher -->
                    <button type="button" onclick="fillCredentials('priya.kulkarni@abcschool.edu', 'teacher123')" 
                            class="p-2.5 rounded-xl border border-blue-200 bg-blue-50 hover:bg-blue-100 text-blue-900 font-semibold text-left transition flex items-center space-x-2">
                        <i class="fa-solid fa-chalkboard-user text-blue-600"></i>
                        <div>
                            <div class="text-[11px] font-bold">Teacher</div>
                            <div class="text-[10px] text-blue-700">Mrs. Priya (Math)</div>
                        </div>
                    </button>

                    <!-- Approved Student -->
                    <button type="button" onclick="fillCredentials('aarav.patil@student.abc.edu', 'student123')" 
                            class="p-2.5 rounded-xl border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-emerald-900 font-semibold text-left transition flex items-center space-x-2">
                        <i class="fa-solid fa-user-check text-emerald-600"></i>
                        <div>
                            <div class="text-[11px] font-bold">Approved Student</div>
                            <div class="text-[10px] text-emerald-700">Aarav (Class 10)</div>
                        </div>
                    </button>

                    <!-- Pending Student -->
                    <button type="button" onclick="fillCredentials('rohan.shinde@student.abc.edu', 'student123')" 
                            class="p-2.5 rounded-xl border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-900 font-semibold text-left transition flex items-center space-x-2"
                            title="Click to test login blocked until Admin approves!">
                        <i class="fa-solid fa-user-clock text-amber-600"></i>
                        <div>
                            <div class="text-[11px] font-bold">Pending Student</div>
                            <div class="text-[10px] text-amber-800">Test Blocked Login</div>
                        </div>
                    </button>
                </div>
            </div>

            <!-- New Student Registration Link -->
            <div class="mt-6 pt-4 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    Are you a new student without an account?
                    <a href="/register.php" class="font-bold text-navy-800 hover:text-navy-950 underline ml-1">Register for New ID &rarr;</a>
                </p>
                <div class="mt-2">
                    <a href="/" class="text-[11px] text-slate-400 hover:text-slate-600">&larr; Back to School Website</a>
                </div>
            </div>

        </div>
    </div>

    <script>
        function fillCredentials(login, pass) {
            document.getElementById('login_input').value = login;
            document.getElementById('password').value = pass;
        }
    </script>
</body>
</html>
