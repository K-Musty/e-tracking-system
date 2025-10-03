<?php
session_start();
include("db_connect.php");

/* ---------------------------------------------
   Redirect if already authenticated
--------------------------------------------- */
if (isset($_SESSION['id'])) {
  header("Location: staff.php");
  exit();
}

/* ---------------------------------------------
   Helpers
--------------------------------------------- */
function clean($v) { return trim($v ?? ''); }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_check($token) {
  return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
function verify_password_flexible(string $input, string $dbValue): bool {
  // If DB stores bcrypt/argon2, use password_verify; if plain text, fallback to strict compare
  if (preg_match('/^\$2y\$/', $dbValue) || preg_match('/^\$argon2/i', $dbValue)) {
    return password_verify($input, $dbValue);
  }
  return hash_equals($dbValue, $input);
}

/* ---------------------------------------------
   Basic rate limiting (per session)
--------------------------------------------- */
$LIMIT_MAX_ATTEMPTS = 5;
$LIMIT_WINDOW_SEC   = 10 * 60; // 10 minutes

if (!isset($_SESSION['login_attempts'])) {
  $_SESSION['login_attempts'] = 0;
  $_SESSION['first_attempt_ts'] = time();
}
$lockout = false;
if ($_SESSION['login_attempts'] >= $LIMIT_MAX_ATTEMPTS) {
  $elapsed = time() - ($_SESSION['first_attempt_ts'] ?? time());
  if ($elapsed < $LIMIT_WINDOW_SEC) {
    $lockout = true;
  } else {
    // Reset window
    $_SESSION['login_attempts'] = 0;
    $_SESSION['first_attempt_ts'] = time();
  }
}

/* ---------------------------------------------
   State
--------------------------------------------- */
$id = $pass = '';
$errors = ['id' => '', 'pass' => '', 'login' => ''];

/* ---------------------------------------------
   Handle submit
--------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($lockout) {
    $errors['login'] = 'Too many attempts. Please wait a few minutes and try again.';
  } else {
    // CSRF
    if (!csrf_check($_POST['csrf'] ?? '')) {
      $errors['login'] = 'Invalid session. Please refresh the page and try again.';
    } else {
      // Validate inputs
      if (empty($_POST['id'])) {
        $errors['id'] = '*Required';
      } else {
        $id = clean($_POST['id']);
      }
      if (empty($_POST['pass'])) {
        $errors['pass'] = '*Required';
      } else {
        $pass = clean($_POST['pass']);
      }

      if (!$errors['id'] && !$errors['pass']) {
        // Lookup by StaffID
        $sql  = "SELECT StaffID, pass FROM staff WHERE StaffID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) > 0) {
          $user = mysqli_fetch_assoc($result);

          if (verify_password_flexible($pass, (string)$user['pass'])) {
            // Success
            $_SESSION['id'] = $user['StaffID'];
            // (Optional) regenerate to prevent fixation
            session_regenerate_id(true);
            // Reset limiter
            $_SESSION['login_attempts'] = 0;
            $_SESSION['first_attempt_ts'] = time();
            header("Location: staff.php");
            exit();
          } else {
            $errors['login'] = 'Incorrect Password';
            // bump limiter
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
          }
        } else {
          $errors['login'] = 'Invalid Staff ID';
          // bump limiter
          $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        }
        mysqli_stmt_close($stmt);
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Ummi's tracking — Staff Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Logo Styles -->
  <link href="style/logo.css" rel="stylesheet">

  <!-- SweetAlert -->
  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

  <!-- Custom styles -->
  <style>
    :root{
      --brand:#0A3D62;
      --brand-2:#0F5CA8;
      --brand-3:#F8FAFC;
      --highlight:#FAD02C;
      --ink:#1F2937;
      --muted:#6B7280;
      --glass:rgba(255,255,255,0.7);
    }
    
    html, body { 
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; 
      color: var(--ink);
      min-height: 100vh;
    }
    
    .navbar { 
      backdrop-filter: saturate(180%) blur(6px); 
      background: rgba(255,255,255,.85)!important; 
    }
    
    .nav-link { 
      font-weight: 500; 
      color: var(--ink)!important; 
    }
    
    .nav-link.active, .nav-link:hover { 
      color: var(--brand-2)!important; 
    }

    /* Hero Background */
    .auth-hero {
      position: relative;
      background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 40%, #0D6EFD 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      overflow: hidden;
    }

    .auth-hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
      opacity: 0.3;
    }

    /* Auth Card */
    .auth-container {
      position: relative;
      z-index: 2;
    }

    .auth-card {
      background: rgba(255,255,255,0.95);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.2);
      overflow: hidden;
      max-width: 480px;
      margin: 0 auto;
    }

    .auth-header {
      background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
      padding: 2rem;
      text-align: center;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .auth-header h1 {
      color: #228B22;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .auth-header p {
      color: var(--muted);
      margin: 0;
    }

    .auth-body {
      padding: 2rem;
    }

    /* Form Styling */
    .form-label {
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 8px;
    }

    .form-control {
      border-radius: 12px;
      border: 2px solid #E5E7EB;
      padding: 12px 16px;
      font-size: 16px;
      transition: all 0.3s ease;
      background: white;
    }

    .form-control:focus {
      border-color: #228B22;
      box-shadow: 0 0 0 3px rgba(34, 139, 34, 0.1);
    }

    .btn-primary {
      background: linear-gradient(135deg, #228B22 0%, #32CD32 100%);
      border: none;
      border-radius: 12px;
      padding: 14px 24px;
      font-weight: 600;
      font-size: 16px;
      transition: all 0.3s ease;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(34, 139, 34, 0.3);
    }

    .btn-outline-secondary {
      border: 2px solid #E5E7EB;
      color: var(--muted);
      border-radius: 12px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
      border-color: #228B22;
      color: #228B22;
      background: rgba(34, 139, 34, 0.05);
    }

    /* Alternative Login Options */
    .alt-login {
      margin-top: 2rem;
      padding-top: 2rem;
      border-top: 1px solid #E5E7EB;
    }

    .alt-login .btn {
      border-radius: 12px;
      padding: 12px 20px;
      font-weight: 600;
      margin-bottom: 8px;
      transition: all 0.3s ease;
    }

    .alt-login .btn:hover {
      transform: translateY(-1px);
    }

    /* Alert Styling */
    .alert {
      border-radius: 12px;
      border: none;
      padding: 16px 20px;
      margin-bottom: 1.5rem;
    }

    .alert-danger {
      background: rgba(239, 68, 68, 0.1);
      color: #DC2626;
      border-left: 4px solid #DC2626;
    }

    .alert-success {
      background: rgba(34, 197, 94, 0.1);
      color: #059669;
      border-left: 4px solid #059669;
    }

    /* Floating Elements */
    .floating-element {
      position: absolute;
      border-radius: 50%;
      background: rgba(255,255,255,0.1);
      animation: float 6s ease-in-out infinite;
    }

    .floating-element:nth-child(1) {
      width: 80px;
      height: 80px;
      top: 20%;
      left: 10%;
      animation-delay: 0s;
    }

    .floating-element:nth-child(2) {
      width: 60px;
      height: 60px;
      top: 60%;
      right: 15%;
      animation-delay: 2s;
    }

    .floating-element:nth-child(3) {
      width: 40px;
      height: 40px;
      bottom: 20%;
      left: 20%;
      animation-delay: 4s;
    }

    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-20px); }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .auth-card {
        margin: 1rem;
        border-radius: 16px;
      }
      
      .auth-header, .auth-body {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand" href="index.php" aria-label="Ummi's tracking Home">
      <img src="Images/logo.svg" alt="Ummi's tracking" style="height: 50px; width: auto;">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="tracking.php">Tracking</a></li>
        <li class="nav-item"><a class="nav-link" href="branches.php">Branches</a></li>
        <li class="nav-item"><a class="nav-link active" href="login.php">Staff Portal</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- AUTH HERO -->
<div class="auth-hero">
  <!-- Floating Elements -->
  <div class="floating-element"></div>
  <div class="floating-element"></div>
  <div class="floating-element"></div>

  <div class="container auth-container">
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="auth-card">
          <div class="auth-header">
            <h1><i class='bx bx-briefcase me-2'></i>Staff Portal</h1>
            <p>Access your Ummi's tracking staff account</p>
          </div>

          <div class="auth-body">
            <?php if($errors['login']): ?>
              <div class="alert alert-danger">
                <i class='bx bx-error-circle me-2'></i><?php echo htmlspecialchars($errors['login']); ?>
              </div>
            <?php endif; ?>

            <!-- Staff Login Form -->
            <form method="POST" novalidate>
              <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>" />

              <?php if (!empty($_SESSION['login_attempts'])): ?>
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h6 class="mb-0">Staff Login</h6>
                  <small class="text-muted">Attempts: <?php echo (int)$_SESSION['login_attempts']; ?>/5</small>
                </div>
              <?php endif; ?>

              <div class="mb-3">
                <label class="form-label">
                  <i class='bx bx-id-card me-1'></i>Staff ID
                </label>
                <input type="text" name="id" class="form-control <?php echo $errors['id'] ? 'is-invalid' : ''; ?>" 
                       value="<?php echo e($id); ?>" placeholder="Enter your Staff ID" required>
                <?php if($errors['id']): ?><div class="text-danger mt-1 small"><?php echo e($errors['id']); ?></div><?php endif; ?>
              </div>

              <div class="mb-4">
                <label class="form-label">
                  <i class='bx bx-lock-alt me-1'></i>Password
                </label>
                <input type="password" name="pass" class="form-control <?php echo $errors['pass'] ? 'is-invalid' : ''; ?>" 
                       placeholder="Enter your password" required>
                <?php if($errors['pass']): ?><div class="text-danger mt-1 small"><?php echo e($errors['pass']); ?></div><?php endif; ?>
              </div>

              <button type="submit" name="submit" class="btn btn-primary w-100">
                <i class='bx bx-log-in me-2'></i>Login to Staff Portal
              </button>
            </form>

            <!-- Alternative Login Options -->
            <div class="alt-login">
              <p class="text-center text-muted mb-3">
                <small>Other login options</small>
              </p>
              <div class="d-grid gap-2">
                <a href="admin_login.php" class="btn btn-outline-danger">
                  <i class='bx bx-shield-alt-2 me-2'></i>Admin Portal
                </a>
                <a href="user_login.php" class="btn btn-outline-secondary">
                  <i class='bx bx-user me-2'></i>User Portal
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($lockout): ?>
<script>
  setTimeout(function(){
    swal("Too many attempts", "Please try again in a few minutes.", "warning");
  }, 200);
</script>
<?php endif; ?>
</body>
</html>
<?php mysqli_close($conn); ?>