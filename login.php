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
  <meta charset="utf-8">
  <title>DropEx — Staff Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- SweetAlert -->
  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F8FAFC; --ink:#111827; --muted:#6B7280;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
    .navbar { backdrop-filter: saturate(180%) blur(6px); background: rgba(255,255,255,.9)!important; }
    .nav-link { font-weight: 500; color: var(--ink)!important; }
    .nav-link.active, .nav-link:hover { color: var(--brand-2)!important; }

    .auth-wrap {
      min-height: calc(100dvh - 72px);
      display: grid; place-items: center;
      padding: 32px 16px;
    }
    .auth-card {
      width: 100%;
      max-width: 420px;
      border: none; border-radius: 16px; background: #fff;
      box-shadow: 0 18px 40px rgba(2,12,27,0.08);
      overflow: hidden;
    }
    .auth-head {
      background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%);
      color: #fff; padding: 24px;
    }
    .auth-body { padding: 24px; }
    .form-control { border-radius: 12px; padding: .75rem .9rem; }
    .btn-pill { border-radius: 999px; padding: .75rem 1.2rem; font-weight: 600; }
    .invalid { color: #DC2626; font-size: .875rem; }
    .alt-links a { text-decoration: none; }
    .alt-links a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar navbar-expand-lg sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="DropEx Home">
      <img src="Images/logo.png" alt="DropEx" style="height:44px">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="tracking.php">Tracking</a></li>
        <li class="nav-item"><a class="nav-link" href="branches.php">Branches</a></li>
        <li class="nav-item"><a class="nav-link active" href="login.php">DropEx Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- AUTH CARD -->
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-head d-flex align-items-center gap-2">
      <i class='bx bx-lock-open-alt fs-3'></i>
      <div>
        <div class="h5 mb-0 fw-bold">DropEx Login</div>
        <small class="opacity-75">Please login to continue</small>
      </div>
    </div>

    <div class="auth-body">
      <!-- STAFF LOGIN -->
      <form method="POST" novalidate>
        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>" />

        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Staff Login</h6>
          <?php if (!empty($_SESSION['login_attempts'])): ?>
            <small class="text-muted">Attempts: <?php echo (int)$_SESSION['login_attempts']; ?>/5</small>
          <?php endif; ?>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold">Staff ID</label>
          <input type="text" class="form-control <?php echo $errors['id'] ? 'is-invalid' : ''; ?>" name="id" value="<?php echo e($id); ?>" placeholder="e.g. ST-00123">
          <?php if($errors['id']): ?><div class="invalid mt-1"><?php echo e($errors['id']); ?></div><?php endif; ?>
        </div>

        <div class="mb-2">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" class="form-control <?php echo $errors['pass'] ? 'is-invalid' : ''; ?>" name="pass" value="<?php echo e($pass); ?>" placeholder="••••••••">
          <?php if($errors['pass']): ?><div class="invalid mt-1"><?php echo e($errors['pass']); ?></div><?php endif; ?>
        </div>

        <?php if($errors['login']): ?>
          <div class="alert alert-danger mt-2" role="alert">
            <?php echo e($errors['login']); ?>
          </div>
        <?php endif; ?>

        <button type="submit" name="submit" class="btn btn-primary btn-pill w-100 mt-3">
          <i class='bx bx-log-in-circle me-1'></i> Staff Sign In
        </button>
      </form>

      <!-- ALT LOGINS -->
      <div class="alt-links mt-4">
        <a href="admin_login.php" class="btn btn-outline-danger btn-pill w-100 mb-2"><i class='bx bxs-cog me-1'></i> Admin Login</a>
        <a href="user_login.php" class="btn btn-outline-success btn-pill w-100"><i class='bx bxs-user me-1'></i> User Login</a>
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
