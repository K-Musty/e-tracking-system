<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header("Location: user_login.php");
    exit;
}

// ---- helpers ----
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf_token() {
  if (empty($_SESSION['csrf'])) {
    if (function_exists('random_bytes')) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    else $_SESSION['csrf'] = bin2hex(openssl_random_pseudo_bytes(32));
  }
  return $_SESSION['csrf'];
}
function csrf_ok($t){ return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$t); }

// Current user
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['username'] ?? ''; // username (handle)
$feedback_success = $feedback_error = $request_success = $request_error = "";
$account_success = $account_error = $pwd_success = $pwd_error = "";

// Fetch fresh user row (authoritative)
$u = mysqli_prepare($conn, "SELECT id, username, email, name, password FROM users WHERE id = ?");
mysqli_stmt_bind_param($u, "i", $user_id);
mysqli_stmt_execute($u);
$ures = mysqli_stmt_get_result($u);
$user = mysqli_fetch_assoc($ures);
mysqli_stmt_close($u);
if (!$user) {
  session_destroy(); header("Location: user_login.php"); exit;
}
// expose fields
$full_name = $user['name'] ?? '';
$email_db  = $user['email'] ?? '';

// -----------------------
// Handle Feedback Submit
// -----------------------
if(isset($_POST['submit_feedback']) && $_SERVER["REQUEST_METHOD"] == "POST"){
    if (!csrf_ok($_POST['csrf'] ?? '')) {
        $feedback_error = "Invalid session. Please refresh and try again.";
    } else {
        $name = $user['name']; // from DB
        $email = trim($_POST['email'] ?? '');
        $msg = trim($_POST['msg'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $feedback_error = "Please enter a valid email.";
        } elseif ($msg === '') {
            $feedback_error = "Message is required.";
        } else {
            if (!isset($_SESSION['last_feedback_time']) || (time() - ($_SESSION['last_feedback_time'] ?? 0)) > 2) {
                $sql = "INSERT INTO feedback (f_id, Cust_name, Cust_mail, Cust_msg) VALUES (?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "isss", $user_id, $name, $email, $msg);
                if (mysqli_stmt_execute($stmt)) {
                    $feedback_success = "Feedback submitted successfully!";
                    $_SESSION['last_feedback_time'] = time();
                } else {
                    $feedback_error = "Error submitting feedback.";
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// ---------------------------
// Handle New Shipping Request
// ---------------------------
if(isset($_POST['submit_request']) && $_SERVER["REQUEST_METHOD"] == "POST"){
    if (!csrf_ok($_POST['csrf'] ?? '')) {
        $request_error = "Invalid session. Please refresh and try again.";
    } else {
        $s_name    = trim($_POST['sender_name'] ?? '');
        $s_add     = trim($_POST['sender_address'] ?? '');
        $s_city    = trim($_POST['sender_city'] ?? '');
        $s_state   = trim($_POST['sender_state'] ?? '');
        $s_contact = trim($_POST['sender_contact'] ?? '');
        $r_name    = trim($_POST['receiver_name'] ?? '');
        $r_add     = trim($_POST['receiver_address'] ?? '');
        $r_city    = trim($_POST['receiver_city'] ?? '');
        $r_state   = trim($_POST['receiver_state'] ?? '');
        $r_contact = trim($_POST['receiver_contact'] ?? '');
        $weight    = (float)($_POST['weight'] ?? 0);

        if ($s_name===''||$s_add===''||$s_city===''||$s_state===''||$s_contact===''||$r_name===''||$r_add===''||$r_city===''||$r_state===''||$r_contact===''||$weight<=0){
            $request_error = "Please complete all fields with valid values.";
        } else {
            $sql_check = "SELECT Cost FROM pricing WHERE (State_1 = ? AND State_2 = ?) OR (State_1 = ? AND State_2 = ?) LIMIT 1";
            $stmt = mysqli_prepare($conn, $sql_check);
            mysqli_stmt_bind_param($stmt, "ssss", $s_state, $r_state, $r_state, $s_state);
            mysqli_stmt_execute($stmt);
            $result_check = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result_check);
            mysqli_stmt_close($stmt);

            if($row){
                $base_cost = (float)$row['Cost'];
                $price = $base_cost * $weight;

                if (!isset($_SESSION['last_request_time']) || (time() - ($_SESSION['last_request_time'] ?? 0)) > 2) {
                    $sql = "INSERT INTO online_request (user_id, S_Name, S_Add, S_City, S_State, S_Contact, 
                            R_Name, R_Add, R_City, R_State, R_Contact, Weight_Kg, Price) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "issssssssssdd", $user_id, $s_name, $s_add, $s_city, $s_state, $s_contact, $r_name, $r_add, $r_city, $r_state, $r_contact, $weight, $price);
                    if (mysqli_stmt_execute($stmt)) {
                        $request_success = "Shipping request submitted successfully! Estimated cost: ৳".number_format($price,2);
                        $_SESSION['last_request_time'] = time();
                        mysqli_stmt_close($stmt);
                        header("Location: " . $_SERVER['PHP_SELF'] . "#requests");
                        exit;
                    } else {
                        $request_error = "Error submitting request.";
                    }
                    mysqli_stmt_close($stmt);
                }
            } else {
                $request_error = "Sorry, delivery is not available between ".e($s_state)." and ".e($r_state).".";
            }
        }
    }
}

// ---------------------------
// Edit Display Name
// ---------------------------
if (isset($_POST['save_name'])) {
    if (!csrf_ok($_POST['csrf'] ?? '')) {
        $account_error = "Invalid session. Please refresh and try again.";
    } else {
        $new_name = trim($_POST['new_name'] ?? '');
        if ($new_name === '') {
            $account_error = "Name cannot be empty.";
        } else {
            $sn = mysqli_prepare($conn, "UPDATE users SET name = ? WHERE id = ?");
            mysqli_stmt_bind_param($sn, "si", $new_name, $user_id);
            if (mysqli_stmt_execute($sn)) {
                $account_success = "Name updated.";
                $_SESSION['name'] = $new_name; // keep session in sync
                $full_name = $new_name;
            } else {
                $account_error = "Could not update name.";
            }
            mysqli_stmt_close($sn);
        }
    }
}

// ---------------------------
// Change Password (secure)
// ---------------------------
if (isset($_POST['change_password'])) {
    if (!csrf_ok($_POST['csrf'] ?? '')) {
        $pwd_error = "Invalid session. Please refresh and try again.";
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new1    = (string)($_POST['new_password'] ?? '');
        $new2    = (string)($_POST['confirm_password'] ?? '');

        if ($current==='' || $new1==='' || $new2==='') {
            $pwd_error = "All password fields are required.";
        } elseif ($new1 !== $new2) {
            $pwd_error = "New passwords do not match.";
        } elseif (strlen($new1) < 6) {
            $pwd_error = "New password must be at least 6 characters.";
        } else {
            // fetch current hash
            $ps = mysqli_prepare($conn, "SELECT password FROM users WHERE id=? LIMIT 1");
            mysqli_stmt_bind_param($ps, "i", $user_id);
            mysqli_stmt_execute($ps);
            $pr = mysqli_stmt_get_result($ps);
            $prow = mysqli_fetch_assoc($pr);
            mysqli_stmt_close($ps);

            if (!$prow) {
                $pwd_error = "Account not found.";
            } elseif (!password_verify($current, $prow['password'])) {
                $pwd_error = "Current password is incorrect.";
            } else {
                $new_hash = password_hash($new1, PASSWORD_DEFAULT);
                $up = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
                mysqli_stmt_bind_param($up, "si", $new_hash, $user_id);
                if (mysqli_stmt_execute($up)) {
                    $pwd_success = "Password changed successfully.";
                } else {
                    $pwd_error = "Unable to change password right now.";
                }
                mysqli_stmt_close($up);
            }
        }
    }
}

// ---------------------------
// Fetch user's shipping requests
// ---------------------------
$sr = mysqli_prepare($conn, "SELECT * FROM online_request WHERE user_id = ? ORDER BY Dispatched_Time DESC");
mysqli_stmt_bind_param($sr, "i", $user_id);
mysqli_stmt_execute($sr);
$shipping_requests = mysqli_fetch_all(mysqli_stmt_get_result($sr), MYSQLI_ASSOC);
mysqli_stmt_close($sr);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DropEx ID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{ --bg:#F6F8FC; --ink:#111827; --muted:#6B7280; --brand:#0A3D62; }
        html,body{ font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .navbar{ background-color: rgba(255,255,255,0.85)!important; backdrop-filter: saturate(180%) blur(6px); }
        .hero{ background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%); color:#fff; border-radius: 16px; }
        .card-soft{ border: 0; border-radius: 16px; box-shadow: 0 12px 28px rgba(2,12,27,.06); }
        .badge-status{ border-radius:999px; padding:.25rem .6rem; font-weight:600; }
        .status-pending { color:#92400E; background:#FEF3C7; }
        .status-approved{ color:#065F46; background:#D1FAE5; }
        .status-rejected{ color:#991B1B; background:#FEE2E2; }
        .section-title{ font-weight:700; }
    </style>
</head>
<body>
    <!-- Navigation Bar (kept) -->
    <nav class="navbar navbar-expand-lg navbar-light mb-3 sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="Images/logo.png" id="logo" style="height: 50px; margin-top: 10px;">
            </a>
            <button class="navbar-toggler text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto" style="font-size: large;">
                    <li class="nav-item"><a class="nav-link" href="#feedback">Feedback</a></li>
                    <li class="nav-item"><a class="nav-link" href="#requests">Shipping Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="tracking.php">Tracking</a></li>
                    <li class="nav-item d-flex align-items-center px-2">Welcome, <?php echo e($user['username']); ?></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="user_logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <div class="container">
        <div class="hero p-4 p-md-5 mb-4">
            <h2 class="mb-1">Hello, <?php echo e($full_name ?: $user['username']); ?> 👋</h2>
            <div class="opacity-75">Manage your shipments, send feedback, and update your account.</div>
        </div>
    </div>

    <!-- Content -->
    <div class="container mb-5">
      <!-- Tabs -->
      <ul class="nav nav-tabs" id="dashTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="tab-feedback" data-bs-toggle="tab" data-bs-target="#pane-feedback" type="button">Feedback</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-new" data-bs-toggle="tab" data-bs-target="#pane-new" type="button">New Request</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-reqs" data-bs-toggle="tab" data-bs-target="#pane-reqs" type="button">My Requests</button></li>
        <li class="nav-item"><button class="nav-link" id="tab-account" data-bs-toggle="tab" data-bs-target="#pane-account" type="button">Account</button></li>
      </ul>

      <div class="tab-content mt-3">
        <!-- Feedback -->
        <div class="tab-pane fade show active" id="pane-feedback" role="tabpanel">
          <div class="card card-soft">
            <div class="card-header bg-white"><h5 class="mb-0 section-title">Submit Feedback</h5></div>
            <div class="card-body">
              <?php if($feedback_success): ?><div class="alert alert-success"><?php echo e($feedback_success); ?></div><?php endif; ?>
              <?php if($feedback_error): ?><div class="alert alert-danger"><?php echo e($feedback_error); ?></div><?php endif; ?>

              <form method="POST" action="">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" value="<?php echo e($full_name ?: $user['username']); ?>" disabled>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?php echo e($email_db); ?>" required>
                  </div>
                  <div class="col-12">
                    <label class="form-label">Message <small class="text-muted">(Include Tracking ID if any)</small></label>
                    <textarea class="form-control" name="msg" rows="4" required></textarea>
                  </div>
                </div>
                <button type="submit" name="submit_feedback" class="btn btn-primary mt-3">Submit Feedback</button>
              </form>
            </div>
          </div>
        </div>

        <!-- New Request -->
        <div class="tab-pane fade" id="pane-new" role="tabpanel">
          <div class="card card-soft">
            <div class="card-header bg-white"><h5 class="mb-0 section-title">New Shipping Request</h5></div>
            <div class="card-body">
              <?php if($request_success): ?><div class="alert alert-success"><?php echo e($request_success); ?></div><?php endif; ?>
              <?php if($request_error): ?><div class="alert alert-danger"><?php echo e($request_error); ?></div><?php endif; ?>

              <form method="POST" action="">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <h6 class="mt-2">Sender Details</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="sender_name" value="<?php echo e($full_name ?: $user['username']); ?>" readonly>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" name="sender_contact" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="sender_address" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="sender_city" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" name="sender_state" required>
                  </div>
                </div>

                <h6 class="mt-4">Receiver Details</h6>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">Name</label>
                    <input type="text" class="form-control" name="receiver_name" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" class="form-control" name="receiver_contact" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Address</label>
                    <input type="text" class="form-control" name="receiver_address" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">City</label>
                    <input type="text" class="form-control" name="receiver_city" required>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">State</label>
                    <input type="text" class="form-control" name="receiver_state" required>
                  </div>
                </div>

                <h6 class="mt-4">Package</h6>
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label">Weight (kg)</label>
                    <input type="number" step="0.01" class="form-control" name="weight" required>
                  </div>
                </div>

                <button type="submit" name="submit_request" class="btn btn-primary mt-3">Submit Request</button>
              </form>
            </div>
          </div>
        </div>

        <!-- My Requests -->
        <div class="tab-pane fade" id="pane-reqs" role="tabpanel">
          <div class="card card-soft">
            <div class="card-header bg-white"><h5 class="mb-0 section-title">Your Shipping Requests</h5></div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Request ID</th>
                      <th>Sender</th>
                      <th>Receiver</th>
                      <th>Weight</th>
                      <th>Price</th>
                      <th>Status</th>
                      <th>Created At</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if(!empty($shipping_requests)): ?>
                      <?php foreach($shipping_requests as $request): ?>
                      <tr>
                        <td><?php echo e($request['user_id']); ?></td>
                        <td><?php echo e($request['S_Name']); ?></td>
                        <td><?php echo e($request['R_Name']); ?></td>
                        <td><?php echo e($request['Weight_Kg']); ?> kg</td>
                        <td>৳<?php echo e($request['Price']); ?></td>
                        <?php
                          $st = strtolower($request['status'] ?? 'pending');
                          $cls = 'status-pending';
                          if ($st === 'approved') $cls = 'status-approved';
                          if ($st === 'rejected') $cls = 'status-rejected';
                        ?>
                        <td><span class="badge-status <?php echo $cls; ?>"><?php echo ucfirst($st); ?></span></td>
                        <td><?php echo e($request['Dispatched_Time']); ?></td>
                      </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr><td colspan="7" class="text-center text-muted py-4">No requests yet.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- Account -->
        <div class="tab-pane fade" id="pane-account" role="tabpanel">
          <div class="row g-3">
            <div class="col-lg-6">
              <div class="card card-soft h-100">
                <div class="card-header bg-white"><h5 class="mb-0 section-title">Edit Display Name</h5></div>
                <div class="card-body">
                  <?php if($account_success): ?><div class="alert alert-success"><?php echo e($account_success); ?></div><?php endif; ?>
                  <?php if($account_error): ?><div class="alert alert-danger"><?php echo e($account_error); ?></div><?php endif; ?>
                  <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                      <label class="form-label">Current Name</label>
                      <input type="text" class="form-control" value="<?php echo e($full_name); ?>" disabled>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">New Name</label>
                      <input type="text" class="form-control" name="new_name" required>
                    </div>
                    <button class="btn btn-primary" name="save_name" type="submit">Save</button>
                  </form>
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <div class="card card-soft h-100">
                <div class="card-header bg-white"><h5 class="mb-0 section-title">Change Password</h5></div>
                <div class="card-body">
                  <?php if($pwd_success): ?><div class="alert alert-success"><?php echo e($pwd_success); ?></div><?php endif; ?>
                  <?php if($pwd_error): ?><div class="alert alert-danger"><?php echo e($pwd_error); ?></div><?php endif; ?>
                  <form method="POST">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <div class="mb-3">
                      <label class="form-label">Current Password</label>
                      <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">New Password</label>
                      <input type="password" class="form-control" name="new_password" required>
                    </div>
                    <div class="mb-3">
                      <label class="form-label">Confirm New Password</label>
                      <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <button class="btn btn-warning" name="change_password" type="submit">Update Password</button>
                  </form>
                  <small class="text-muted d-block mt-2">Minimum 6 characters.</small>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- tab-content -->
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
      // Persist active tab
      document.querySelectorAll('#dashTabs button[data-bs-toggle="tab"]').forEach(btn=>{
        btn.addEventListener('shown.bs.tab', e=>{
          localStorage.setItem('userdash_lastTab', e.target.getAttribute('data-bs-target'));
        });
      });
      const last = localStorage.getItem('userdash_lastTab');
      if (last && document.querySelector(`#dashTabs button[data-bs-target="${last}"]`)) {
        new bootstrap.Tab(document.querySelector(`#dashTabs button[data-bs-target="${last}"]`)).show();
      }
    </script>
</body>
</html>
