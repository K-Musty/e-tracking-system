<?php
session_start();
include("db_connect.php");

// Check if staff is logged in
if(!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Get staff details including branch
$staff_id = $_SESSION['id'];
$sql = "SELECT * FROM staff WHERE StaffID=?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $staff_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$staff = mysqli_fetch_assoc($result);

if (!$staff) {
    $_SESSION['error_message'] = 'Staff details not found';
    header('Location: login.php');
    exit();
}

$staff_name   = $staff['Name'];
$staff_branch = $staff['branch'];

// Flash messages
$success_message = '';
$error_message   = '';
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Handle request approval/rejection
if(isset($_POST['update_request'])) {
    $serial = mysqli_real_escape_string($conn, $_POST['serial']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    // Check if still pending and belongs to this branch
    $check_sql  = "SELECT * FROM online_request WHERE serial = ? AND status = 'pending' AND S_State = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    mysqli_stmt_bind_param($check_stmt, "is", $serial, $staff_branch);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    
    if(mysqli_num_rows($check_result) > 0) {
        $request_data = mysqli_fetch_assoc($check_result);
        
        if($status === 'approved') {
            mysqli_begin_transaction($conn);
            try {
                $tracking_id = rand(100000, 999999);

                // Insert into parcel (types kept as-is to avoid breaking your DB)
                $sql = "INSERT INTO parcel (TrackingID, StaffID, S_Name, S_Add, S_City, S_State, S_Contact, 
                        R_Name, R_Add, R_City, R_State, R_Contact, Weight_Kg, Price, Dispatched_Time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param(
                    $stmt,
                    "isssssissssidds",
                    $tracking_id,
                    $staff_id,
                    $request_data['S_Name'],
                    $request_data['S_Add'],
                    $request_data['S_City'],
                    $request_data['S_State'],
                    $request_data['S_Contact'],
                    $request_data['R_Name'],
                    $request_data['R_Add'],
                    $request_data['R_City'],
                    $request_data['R_State'],
                    $request_data['R_Contact'],
                    $request_data['Weight_Kg'],
                    $request_data['Price'],
                    $request_data['Dispatched_Time']
                );
                mysqli_stmt_execute($stmt);
                
                $tid = mysqli_insert_id($conn);
                
                // Update online_request
                $update_sql  = "UPDATE online_request SET status = ? WHERE serial = ? AND status = 'pending' AND S_State = ?";
                $update_stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($update_stmt, "sis", $status, $serial, $staff_branch);
                mysqli_stmt_execute($update_stmt);
                
                mysqli_commit($conn);
                
                $_SESSION['tid'] = $tid;
                header("Location: receipt.php");
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $_SESSION['error_message'] = 'Error processing request: ' . mysqli_error($conn);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } elseif($status === 'rejected') {
            $sql  = "UPDATE online_request SET status = ? WHERE serial = ? AND status = 'pending' AND S_State = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sis", $status, $serial, $staff_branch);
            if(mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = 'Request rejected successfully!';
            } else {
                $_SESSION['error_message'] = 'Error rejecting request: ' . mysqli_error($conn);
            }
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    } else {
        $_SESSION['error_message'] = 'This request has already been processed or does not belong to your branch!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch pending requests only for staff's branch
$sql = "SELECT * FROM online_request WHERE status = 'pending' AND S_State = ? ORDER BY Dispatched_Time DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $staff_branch);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pending_requests = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Safe echo
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DropEx — Staff Request Approval</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Inter + Bootstrap 5 -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F6F8FC; --ink:#111827; --muted:#6B7280;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
    .navbar { backdrop-filter: saturate(180%) blur(6px); background: rgba(255,255,255,.9)!important; }
    .hero { background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%); border-radius: 16px; color:#fff; }
    .card-soft { border: 0; border-radius: 16px; box-shadow: 0 12px 28px rgba(2,12,27,.06); }
    .badge-soft { background:#EEF2FF; color:#3B82F6; border-radius:999px; padding:.25rem .55rem; font-weight:600; }
    table td, table th { vertical-align: middle; }
  </style>
</head>
<body>

<!-- Topbar -->
<nav class="navbar navbar-expand-lg shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="Images/logo.png" alt="DropEx" style="height:44px">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="topnav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
        <li class="nav-item"><a class="btn btn-outline-secondary" href="staff.php">&larr; Back to Dashboard</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero -->
<div class="container my-4">
  <div class="hero p-4 p-md-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div>
        <h2 class="mb-1">Pending Shipping Requests</h2>
        <div class="opacity-75">Approve or reject online requests for your branch.</div>
      </div>
      <div class="text-end">
        <div class="badge-soft d-block mb-2">Staff: <?php echo e($staff_id); ?></div>
        <div class="badge-soft d-block">Branch: <?php echo e($staff_branch); ?></div>
      </div>
    </div>
  </div>
</div>

<!-- Content -->
<div class="container mb-5">

  <?php if($success_message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?php echo e($success_message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <?php echo e($error_message); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card card-soft">
    <div class="card-header bg-white">
      <div class="d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Requests for <?php echo e($staff_branch); ?></h5>
        <div class="small text-muted">Staff: <?php echo e($staff_name); ?></div>
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Serial No.</th>
              <th>Request ID</th>
              <th>Sender</th>
              <th>Receiver</th>
              <th>Weight</th>
              <th>Price</th>
              <th>Created At</th>
              <th style="width:160px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($pending_requests)): ?>
              <?php foreach($pending_requests as $request): ?>
                <tr>
                  <td><?php echo e($request['serial']); ?></td>
                  <td><?php echo e($request['user_id']); ?></td>
                  <td>
                    <div class="fw-semibold"><?php echo e($request['S_Name']); ?></div>
                    <div class="small text-muted">
                      <?php echo e($request['S_Add']); ?><br>
                      <?php echo e($request['S_City']); ?>, <?php echo e($request['S_State']); ?><br>
                      <span class="text-nowrap">Contact: <?php echo e($request['S_Contact']); ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="fw-semibold"><?php echo e($request['R_Name']); ?></div>
                    <div class="small text-muted">
                      <?php echo e($request['R_Add']); ?><br>
                      <?php echo e($request['R_City']); ?>, <?php echo e($request['R_State']); ?><br>
                      <span class="text-nowrap">Contact: <?php echo e($request['R_Contact']); ?></span>
                    </div>
                  </td>
                  <td><?php echo e($request['Weight_Kg']); ?> kg</td>
                  <td>৳<?php echo e($request['Price']); ?></td>
                  <td><?php echo e($request['Dispatched_Time']); ?></td>
                  <td>
                    <div class="d-flex gap-2">
                      <form method="POST" action="" class="d-inline">
                        <input type="hidden" name="serial" value="<?php echo e($request['serial']); ?>">
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" name="update_request" class="btn btn-success btn-sm w-100">Approve</button>
                      </form>
                      <form method="POST" action="" class="d-inline" onsubmit="return confirm('Reject this request?');">
                        <input type="hidden" name="serial" value="<?php echo e($request['serial']); ?>">
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" name="update_request" class="btn btn-danger btn-sm w-100">Reject</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center text-muted py-4">No pending requests for your branch.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<footer class="mt-4">
  <div class="container">
    <div class="text-center small text-muted py-3">
      &copy; 2025 DropEx. All Rights Reserved. | Delivering Beyond Borders
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
