<?php
session_start();
require_once 'config.php';

/* ---------------------------------------------
   Redirect if already logged in
--------------------------------------------- */
if (isset($_SESSION['admin_id'])) {
  header("Location: adminDashboard.php");
  exit;
}

/* ---------------------------------------------
   Helpers
--------------------------------------------- */
function clean($v){ return trim($v ?? ''); }
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function verify_password_flexible(string $input, string $dbValue): bool {
  // Support hashed (bcrypt/argon2) or legacy plaintext
  if (preg_match('/^\$2y\$/', $dbValue) || preg_match('/^\$argon2/i', $dbValue)) {
    return password_verify($input, $dbValue);
  }
  return hash_equals($dbValue, $input);
}

/* ---------------------------------------------
   Simple rate limit (per session)
--------------------------------------------- */
$MAX_ATTEMPTS   = 5;
$WINDOW_SECONDS = 10 * 60; // 10 minutes
if (!isset($_SESSION['a_first_ts'])) $_SESSION['a_first_ts'] = time();
if (!isset($_SESSION['a_attempts'])) $_SESSION['a_attempts'] = 0;

$locked = false;
if ($_SESSION['a_attempts'] >= $MAX_ATTEMPTS) {
  $elapsed = time() - $_SESSION['a_first_ts'];
  if ($elapsed < $WINDOW_SECONDS) {
    $locked = true;
  } else {
    $_SESSION['a_attempts'] = 0;
    $_SESSION['a_first_ts'] = time();
  }
}

$error = '';

/* ---------------------------------------------
   Handle POST
--------------------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if ($locked) {
    $error = "Too many attempts. Please try again later.";
  } else {
    $admin_id = clean($_POST['admin_id'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($admin_id === '' || $password === '') {
      $error = "Admin ID and password are required.";
    } else {
      // Fetch admin by id
      $stmt = mysqli_prepare($conn, "SELECT admin_id, name, password FROM admin_credentials WHERE admin_id = ? LIMIT 1");
      mysqli_stmt_bind_param($stmt, "s", $admin_id);
      mysqli_stmt_execute($stmt);
      $res = mysqli_stmt_get_result($stmt);

      if ($res && mysqli_num_rows($res) === 1) {
        $row = mysqli_fetch_assoc($res);
        if (verify_password_flexible($password, (string)$row['password'])) {
          // Success: set session & regenerate
          $_SESSION['admin_id'] = $row['admin_id'];
          $_SESSION['admin_name'] = $row['name'] ?? 'Admin';
          session_regenerate_id(true);

          // Update last login
          $u = mysqli_prepare($conn, "UPDATE admin_credentials SET last_login = NOW() WHERE admin_id = ?");
          mysqli_stmt_bind_param($u, "s", $row['admin_id']);
          mysqli_stmt_execute($u);
          mysqli_stmt_close($u);

          // Reset limiter
          $_SESSION['a_attempts'] = 0;
          $_SESSION['a_first_ts'] = time();

          header("Location: adminDashboard.php");
          exit;
        } else {
          $error = "Invalid admin ID or password";
          $_SESSION['a_attempts']++;
        }
      } else {
        $error = "Invalid admin ID or password";
        $_SESSION['a_attempts']++;
      }
      mysqli_stmt_close($stmt);
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DropEx — Admin Login</title>

  <!-- Inter + Bootstrap 5 -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F8FAFC; --ink:#111827; --muted:#6B7280;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: url('Images/admin.jpg') no-repeat center center fixed; background-size: cover; min-height:100vh; }
    .overlay { background: rgba(0,0,0,.35); min-height:100vh; }
    .auth-wrap { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
    .auth-card { max-width: 420px; width: 100%; background: rgba(255,255,255,.95); border-radius: 16px; box-shadow: 0 18px 40px rgba(2,12,27,.15); overflow: hidden; }
    .auth-head { background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%); color:#fff; padding: 24px; }
    .auth-body { padding: 24px; }
    .form-control { border-radius: 12px; padding: .75rem .95rem; }
    .btn-pill { border-radius: 999px; padding: .75rem 1rem; font-weight: 600; }
    .alt-links a { text-decoration: none; }
    .alt-links a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="overlay">
    <!-- Optional top nav (keeps brand consistent) -->
    <nav class="navbar navbar-expand-lg shadow-sm" style="background: rgba(255,255,255,.9)!important;">
      <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="index.php">
          <img src="Images/logo.png" alt="DropEx" style="height:44px">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
          <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
            <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
            <li class="nav-item"><a class="nav-link" href="tracking.php">Tracking</a></li>
            <li class="nav-item"><a class="nav-link" href="branches.php">Branches</a></li>
            <li class="nav-item"><a class="nav-link active" href="admin_login.php">Admin Login</a></li>
          </ul>
        </div>
      </div>
    </nav>

    <div class="auth-wrap">
      <div class="auth-card">
        <div class="auth-head text-center">
          <h5 class="fw-bold mb-0">DropEx Admin Portal</h5>
          <small class="opacity-75">Please login to continue</small>
        </div>
        <div class="auth-body">
          <?php if($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>
          <?php if($locked): ?><div class="alert alert-warning">Too many attempts. Please try again later.</div><?php endif; ?>

          <form method="POST" novalidate>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="admin_id">Admin ID</label>
              <input type="text" class="form-control" id="admin_id" name="admin_id" placeholder="e.g. AD-0001" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold" for="password">Password</label>
              <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
            </div>
            <?php if(!empty($_SESSION['a_attempts'])): ?>
              <small class="text-muted d-block mb-2">Attempts: <?php echo (int)$_SESSION['a_attempts']; ?>/<?php echo $MAX_ATTEMPTS; ?></small>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary btn-pill w-100">Login</button>
          </form>

          <div class="mt-3 d-grid gap-2">
            <a href="login.php" class="btn btn-outline-danger btn-pill">Staff Login</a>
            <a href="user_login.php" class="btn btn-outline-success btn-pill">User Login</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
