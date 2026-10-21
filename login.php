<?php
/**
 * ERP Login Portal - ABC School Nashik
 */
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    $role = $_SESSION['role'];
    if (in_array($role, ['admin', 'principal', 'vice_principal'])) header("Location: /admin/index.php");
    elseif ($role === 'teacher') header("Location: /teacher/index.php");
    else header("Location: /student/index.php");
    exit();
}

$error_message = '';
$warning_message = '';
$flash = get_flash();

$school_logo = get_setting($pdo, 'school_logo', '');
$school_name = get_setting($pdo, 'school_name', 'ABC School Nashik');
$school_city = get_setting($pdo, 'school_city', 'Nashik');

// Status flags from redirect
if (isset($_GET['logged_out'])) {
    $flash = ['type' => 'success', 'message' => 'You have been securely logged out. Session destroyed.'];
} elseif (isset($_GET['timeout'])) {
    $warning_message = '<strong>Session Timed Out:</strong> For your security, you were automatically signed out after 30 minutes of inactivity. Please sign in again.';
} elseif (isset($_GET['hijack_detected'])) {
    $error_message = 'Security Alert: Browser environment change detected. Your session was terminated for your protection.';
}

// Brute Force Rate Limiting
$max_attempts = 5;
$lockout_seconds = 120; // 2 minutes

$attempts = $_SESSION['login_attempts'] ?? 0;
$last_attempt_time = $_SESSION['last_login_attempt_time'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($attempts >= $max_attempts && (time() - $last_attempt_time < $lockout_seconds)) {
        $remaining = $lockout_seconds - (time() - $last_attempt_time);
        $error_message = "Too many failed login attempts. For security, login is locked for {$remaining} seconds.";
    } else {
        if ($attempts >= $max_attempts && (time() - $last_attempt_time >= $lockout_seconds)) {
            $_SESSION['login_attempts'] = 0;
        }

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
                // Successful Login: Reset rate-limiting counter
                $_SESSION['login_attempts'] = 0;

                // Session Fixation Prevention: Regenerate session ID
                session_regenerate_id(true);
                $_SESSION['last_activity'] = time();
                $_SESSION['fingerprint'] = md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

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
                } elseif (in_array($user['role'], ['admin', 'principal', 'vice_principal'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['status'] = 'approved';
                    
                    if ($user['role'] === 'admin') {
                        $_SESSION['full_name'] = get_setting($pdo, 'admin_name', 'System Administrator');
                    } elseif ($user['role'] === 'principal') {
                        $_SESSION['full_name'] = get_setting($pdo, 'principal_name', 'Dr. R. K. Sharma (Principal)');
                    } else {
                        $_SESSION['full_name'] = get_setting($pdo, 'vice_principal_name', 'Prof. S. M. Deshmukh (Vice Principal)');
                    }

                    header("Location: /admin/index.php");
                    exit();
                }
            } else {
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
                $_SESSION['last_login_attempt_time'] = time();
                $rem = max(0, $max_attempts - $_SESSION['login_attempts']);
                if ($rem > 0) {
                    $error_message = "Invalid credentials. Please verify your email/username and password. ({$rem} attempts remaining before temporary lockout)";
                } else {
                    $error_message = "Too many failed login attempts. Login temporarily locked for {$lockout_seconds} seconds.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Login - <?= htmlspecialchars($school_name) ?></title>
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

    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4">
        <!-- Logo -->
        <a href="/" class="flex flex-col items-center group text-center">
            <?php if (!empty($school_logo) && file_exists(__DIR__ . '/' . ltrim($school_logo, '/'))): ?>
                <img src="<?= htmlspecialchars($school_logo) ?>" alt="School Logo" class="w-16 h-16 sm:w-20 sm:h-20 object-contain rounded-3xl shadow-xl shadow-blue-500/10 group-hover:scale-105 transition-transform mb-3 bg-white p-2 border border-slate-200">
            <?php else: ?>
                <div class="w-16 h-16 rounded-3xl bg-gradient-to-tr from-navy-950 to-navy-700 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 group-hover:scale-105 transition-transform mb-3">
                    <i class="fa-solid fa-school text-3xl text-amber-400"></i>
                </div>
            <?php endif; ?>
            <h2 class="text-xl sm:text-2xl font-extrabold text-navy-950 font-['Outfit'] tracking-tight break-words max-w-sm sm:max-w-md leading-tight"><?= htmlspecialchars(strtoupper($school_name)) ?></h2>
            <p class="text-xs font-bold tracking-widest text-amber-600 uppercase mt-1 truncate"><?= htmlspecialchars($school_city) ?> • ERP Portal</p>
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
                <?= csrf_field() ?>
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
                               placeholder="admin@edu.in" 
                               class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-navy-600 focus:border-navy-600 transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Password
                    </label>
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

            <!-- New Student Registration Link -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    Are you a new student without an account?
                    <a href="/register.php" class="font-bold text-navy-800 hover:text-navy-950 underline ml-1">Register for New ID &rarr;</a>
                </p>
                <div class="mt-3">
                    <a href="/" class="text-[11px] text-slate-400 hover:text-slate-600">&larr; Back to School Website</a>
                </div>
            </div>

        </div>
    </div>
</body>
</html>
