<?php
session_start();
require_once 'config.php';

/* ----------------------------------
   Redirect if already logged in
---------------------------------- */
if (isset($_SESSION['user_id'])) {
  header("Location: user_dashboard.php");
  exit;
}

/* ----------------------------------
   State
---------------------------------- */
$error = '';
$success = '';

/* ----------------------------------
   Handle Login
---------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['login'])) {
  $email = trim($_POST['email']);
  $password = $_POST['password'];

  $stmt = mysqli_prepare($conn, "SELECT id, username, name, password FROM users WHERE email=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "s", $email);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  if ($result && mysqli_num_rows($result) === 1) {
    $row = mysqli_fetch_assoc($result);
    if (password_verify($password, $row['password'])) {
      $_SESSION['user_id'] = $row['id'];
      $_SESSION['username'] = $row['username'];
      $_SESSION['name'] = $row['name'];

      // Update last login
      $update_stmt = mysqli_prepare($conn, "UPDATE users SET last_login = NOW() WHERE id=?");
      mysqli_stmt_bind_param($update_stmt, "i", $row['id']);
      mysqli_stmt_execute($update_stmt);

      header("Location: user_dashboard.php");
      exit;
    } else {
      $error = "Invalid email or password";
    }
  } else {
    $error = "Invalid email or password";
  }
  mysqli_stmt_close($stmt);
}

/* ----------------------------------
   Handle Registration
---------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['register'])) {
  $username = trim($_POST['reg_username']);
  $email    = trim($_POST['reg_email']);
  $password = password_hash($_POST['reg_password'], PASSWORD_DEFAULT);
  $name     = trim($_POST['reg_name']);

  // Check for existing user
  $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? OR username=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "ss", $email, $username);
  mysqli_stmt_execute($stmt);
  $check = mysqli_stmt_get_result($stmt);

  if ($check && mysqli_num_rows($check) > 0) {
    $error = "Email or username already exists!";
  } else {
    $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, name) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ssss", $username, $email, $password, $name);
    if (mysqli_stmt_execute($stmt)) {
      $success = "Registration successful! Please login.";
    } else {
      $error = "Registration failed! Please try again.";
    }
  }
  mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>DropEx — User Login</title>

  <!-- Fonts & Bootstrap -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body {
      font-family: Inter, sans-serif;
      background: url('Images/DropExBack.jpg') no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      display: flex; flex-direction: column;
    }
    .auth-wrap {
      flex: 1;
      display: grid;
      place-items: center;
      padding: 2rem;
    }
    .auth-card {
      max-width: 420px; width: 100%;
      background: rgba(255,255,255,.95);
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,.15);
      overflow: hidden;
    }
    .auth-head {
      background: linear-gradient(135deg,#0A3D62 0%,#0F5CA8 100%);
      color: #fff; padding: 1.5rem;
    }
    .auth-body { padding: 1.5rem; }
    .form-control { border-radius: 10px; padding: .75rem 1rem; }
    .btn-pill { border-radius: 999px; padding: .75rem 1rem; font-weight: 600; }
    .toggle-btns { display:flex; gap:10px; margin-bottom:1rem; }
    .toggle-btns .btn { flex:1; }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar navbar-expand-lg sticky-top shadow-sm" style="background: rgba(255,255,255,.9)!important;">
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
        <li class="nav-item"><a class="nav-link active" href="user_login.php">User Login</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- CONTENT -->
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-head text-center">
      <h4 class="fw-bold mb-0">DropEx User Portal</h4>
      <small class="opacity-75">Login or Register to continue</small>
    </div>

    <div class="auth-body">
      <?php if($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
      <?php if($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

      <div class="toggle-btns">
        <button class="btn btn-primary" onclick="showLogin()">Login</button>
        <button class="btn btn-outline-secondary" onclick="showRegister()">Register</button>
      </div>

      <!-- Login -->
      <form method="POST" id="loginForm">
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" name="login" class="btn btn-primary btn-pill w-100">Login</button>

        <div class="mt-3 d-grid gap-2">
          <a href="admin_login.php" class="btn btn-outline-danger btn-pill">Admin Login</a>
          <a href="login.php" class="btn btn-outline-success btn-pill">Staff Login</a>
        </div>
      </form>

      <!-- Register -->
      <form method="POST" id="registerForm" style="display:none;">
        <div class="mb-3">
          <label class="form-label fw-semibold">Full Name</label>
          <input type="text" name="reg_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Username</label>
          <input type="text" name="reg_username" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Email</label>
          <input type="email" name="reg_email" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Password</label>
          <input type="password" name="reg_password" class="form-control" required>
        </div>
        <button type="submit" name="register" class="btn btn-primary btn-pill w-100">Register</button>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function showLogin() {
    document.getElementById('loginForm').style.display='block';
    document.getElementById('registerForm').style.display='none';
  }
  function showRegister() {
    document.getElementById('loginForm').style.display='none';
    document.getElementById('registerForm').style.display='block';
  }
</script>
</body>
</html>
