<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once "db_connect.php";

// Guard: must be logged in
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
  header("Location: login.php");
  exit();
}

// ---------- helpers ----------
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    if (function_exists('random_bytes')) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    else $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }

// ---------- fetch staff ----------
$id = $_SESSION['id'];
$stmt = mysqli_prepare($conn, "SELECT * FROM staff WHERE StaffID = ?");
mysqli_stmt_bind_param($stmt, "s", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if (!$res || mysqli_num_rows($res) === 0) {
  session_destroy(); header("Location: login.php?error=invalid_staff"); exit();
}
$staff = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// ---------- form handling ----------
$success = $error = "";

// Update profile (email/mobile/branch)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $error = "Invalid session token. Please refresh and try again.";
  } else {
    $email  = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $branch = trim($_POST['branch'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = "Please provide a valid email address.";
    } elseif ($mobile === '') {
      $error = "Mobile is required.";
    } else {
      $u = mysqli_prepare($conn, "UPDATE staff SET Email=?, Mobile=?, branch=? WHERE StaffID=?");
      if ($u) {
        mysqli_stmt_bind_param($u, "ssss", $email, $mobile, $branch, $id);
        if (mysqli_stmt_execute($u)) {
          $success = "Profile updated successfully.";
          // refresh $staff
          $stmt = mysqli_prepare($conn, "SELECT * FROM staff WHERE StaffID = ?");
          mysqli_stmt_bind_param($stmt, "s", $id);
          mysqli_stmt_execute($stmt);
          $res = mysqli_stmt_get_result($stmt);
          $staff = mysqli_fetch_assoc($res);
          mysqli_stmt_close($stmt);
        } else { $error = "Could not update profile. Try again."; }
        mysqli_stmt_close($u);
      } else { $error = "Server error preparing update."; }
    }
  }
}

// Change password (PLAIN TEXT, per your request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  if (!csrf_ok($_POST['csrf'] ?? '')) {
    $error = "Invalid session token. Please refresh and try again.";
  } else {
    $current = (string)($_POST['current_password'] ?? '');
    $new1    = (string)($_POST['new_password'] ?? '');
    $new2    = (string)($_POST['confirm_password'] ?? '');

    if ($new1 === '' || $new2 === '' || $current === '') {
      $error = "All password fields are required.";
    } elseif ($new1 !== $new2) {
      $error = "New passwords do not match.";
    } else {
      // Fetch current stored password (plain text)
      $pstmt = mysqli_prepare($conn, "SELECT pass FROM staff WHERE StaffID=? LIMIT 1");
      mysqli_stmt_bind_param($pstmt, "s", $id);
      mysqli_stmt_execute($pstmt);
      $pres = mysqli_stmt_get_result($pstmt);
      $prow = mysqli_fetch_assoc($pres);
      mysqli_stmt_close($pstmt);

      if (!$prow) {
        $error = "Account not found.";
      } else {
        $stored = (string)$prow['pass'];
        if (!hash_equals($stored, $current)) {
          $error = "Current password is incorrect.";
        } else {
          // Save new password AS-IS (plain text)
          $ustmt = mysqli_prepare($conn, "UPDATE staff SET pass=? WHERE StaffID=?");
          mysqli_stmt_bind_param($ustmt, "ss", $new1, $id);
          if (mysqli_stmt_execute($ustmt)) {
            $success = "Password changed successfully.";
          } else {
            $error = "Unable to change password right now.";
          }
          mysqli_stmt_close($ustmt);
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Ummi's tracking — Account</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Inter + Bootstrap 5 -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style/logo.css" rel="stylesheet">

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F6F8FC; --ink:#111827; --muted:#6B7280;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
    .navbar { backdrop-filter: saturate(180%) blur(6px); background: rgba(255,255,255,.9)!important; }
    .hero { background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%); border-radius: 16px; color:#fff; }
    .card-soft { border: none; border-radius: 16px; box-shadow: 0 12px 28px rgba(2,12,27,.06); }
    .avatar { width:88px; height:88px; border-radius:50%; object-fit:cover; border:3px solid rgba(255,255,255,.7); }
    .kv { display:grid; grid-template-columns: 180px 1fr; gap:.5rem 1rem; }
    .kv .k { color: var(--muted); font-weight:600; }
    .kv .v { font-weight:500; }
    @media (max-width: 576px) { .kv { grid-template-columns: 1fr; } }
  </style>
</head>
<body>

<!-- Top brand bar -->
<nav class="navbar navbar-expand-lg shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <div class="navbar-brand-logo" style="display: inline-flex;">
      <div class="logo-icon">
        <div class="package-icon"></div>
        <div class="tracking-dots">
          <div class="tracking-dot"></div>
          <div class="tracking-dot"></div>
          <div class="tracking-dot"></div>
        </div>
      </div>
      <div class="logo-text">
        <div class="logo-main-text">Ummi's</div>
        <div class="logo-sub-text">tracking</div>
        <div class="logo-decorative-line"></div>
      </div>
    </div>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
        <li class="nav-item"><a class="nav-link" href="staff.php">Back</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Header / Hero -->
<div class="container my-4">
  <div class="hero p-4 p-md-5">
    <div class="d-flex flex-wrap align-items-center gap-3">
      <img class="avatar" src="Images/pp2.png" alt="Profile">
      <div class="text-white">
        <h3 class="mb-1"><?php echo e($staff['Name'] ?? ''); ?></h3>
        <div class="opacity-75">Staff ID: <?php echo e($staff['StaffID'] ?? ''); ?></div>
      </div>
    </div>
  </div>
</div>

<div class="container mb-5">
  <?php if($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?php echo e($error); ?></div><?php endif; ?>

  <ul class="nav nav-tabs" id="acctTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">Details</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="edit-tab" data-bs-toggle="tab" data-bs-target="#edit" type="button" role="tab">Edit Profile</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="pwd-tab" data-bs-toggle="tab" data-bs-target="#pwd" type="button" role="tab">Change Password</button>
    </li>
  </ul>

  <div class="tab-content mt-3">
    <!-- Details -->
    <div class="tab-pane fade show active" id="details" role="tabpanel" aria-labelledby="details-tab">
      <div class="card card-soft">
        <div class="card-header bg-white"><h5 class="mb-0">Account Details</h5></div>
        <div class="card-body">
          <div class="kv">
            <div class="k">Name</div>         <div class="v"><?php echo e($staff['Name'] ?? ''); ?></div>
            <div class="k">Staff ID</div>     <div class="v"><?php echo e($staff['StaffID'] ?? ''); ?></div>
            <div class="k">Designation</div>  <div class="v"><?php echo e($staff['Designation'] ?? ''); ?></div>
            <div class="k">Branch</div>       <div class="v"><?php echo e($staff['branch'] ?? ''); ?></div>
            <div class="k">Gender</div>       <div class="v"><?php echo e($staff['Gender'] ?? ''); ?></div>
            <div class="k">DOB</div>          <div class="v"><?php echo e($staff['DOB'] ?? ''); ?></div>
            <div class="k">DOJ</div>          <div class="v"><?php echo e($staff['DOJ'] ?? ''); ?></div>
            <div class="k">Email</div>        <div class="v"><?php echo e($staff['Email'] ?? ''); ?></div>
            <div class="k">Mobile</div>       <div class="v"><?php echo e($staff['Mobile'] ?? ''); ?></div>
            <div class="k">Credits</div>      <div class="v"><?php echo e($staff['Credits'] ?? '0'); ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Profile -->
    <div class="tab-pane fade" id="edit" role="tabpanel" aria-labelledby="edit-tab">
      <div class="card card-soft">
        <div class="card-header bg-white"><h5 class="mb-0">Edit Profile</h5></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo e($staff['Email'] ?? ''); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Mobile</label>
                <input type="text" class="form-control" name="mobile" value="<?php echo e($staff['Mobile'] ?? ''); ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Branch</label>
                <input type="text" class="form-control" name="branch" value="<?php echo e($staff['branch'] ?? ''); ?>">
              </div>
            </div>
            <button type="submit" name="save_profile" class="btn btn-primary mt-3">Save Changes</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Change Password (PLAIN TEXT) -->
    <div class="tab-pane fade" id="pwd" role="tabpanel" aria-labelledby="pwd-tab">
      <div class="card card-soft">
        <div class="card-header bg-white"><h5 class="mb-0">Change Password</h5></div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
            </div>
            <button type="submit" name="change_password" class="btn btn-warning mt-3">Update Password</button>
          </form>
          <small class="text-muted d-block mt-2">Note: Passwords are stored in plain text per your requirement.</small>
        </div>
      </div>
    </div>

  </div><!-- tab-content -->
</div>

<footer class="mt-4">
  <div class="container">
    <div class="text-center small text-muted py-3">
      &copy; 2025 Ummi's tracking. All Rights Reserved. | Delivering Beyond Borders
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Persist active tab
document.querySelectorAll('#acctTabs button[data-bs-toggle="tab"]').forEach(btn=>{
  btn.addEventListener('shown.bs.tab', e=>{
    localStorage.setItem('acct_lastTab', e.target.getAttribute('data-bs-target'));
  });
});
const last = localStorage.getItem('acct_lastTab');
if (last && document.querySelector(`#acctTabs button[data-bs-target="${last}"]`)) {
  new bootstrap.Tab(document.querySelector(`#acctTabs button[data-bs-target="${last}"]`)).show();
}
</script>
</body>
</html>