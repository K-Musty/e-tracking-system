<?php
session_start();
include("db_connect.php");

/* -------------------------------------------------
   Helpers
------------------------------------------------- */
function clean($v) { return trim($v ?? ''); }
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function fmt_date($v) {
  if (!$v) return '';
  // accept either DATETIME/TIMESTAMP or date-like string
  $t = strtotime($v);
  return $t ? date('d M Y, H:i', $t) : e($v);
}

/* -------------------------------------------------
   State
------------------------------------------------- */
$tid = '';
$error = '';
$hide   = 'hidden';   // hide "update address" panel until a valid track id is found
$hidden = 'hidden';   // hide friend form until "Update Delivery Address" is clicked
$trackid = '';
$user_name = $_SESSION['user_name'] ?? 'User';

// Defaults to avoid undefined index notices
$status = [
  'Dispatched' => null,
  'Shipped' => null,
  'Out_for_delivery' => null,
  'Delivered' => null
];
$active = [
  'Received' => '', 'Shipped' => '', 'Out_for_delivery' => '', 'Delivered' => ''
];

// Form validation for friend-dropoff
$name = $add = $contact = '';
$errors = ['name'=>'', 'add'=>'', 'cont'=>''];

/* -------------------------------------------------
   Track submit
------------------------------------------------- */
if (isset($_POST['track'])) {
  if (empty($_POST['tid'])) {
    $error = '*Required';
  } else {
    $tid = clean($_POST['tid']);
    $_SESSION['track_tid'] = $tid;

    // Look up status by tracking id (prepared)
    $stmt = mysqli_prepare($conn, "SELECT Dispatched, Shipped, Out_for_delivery, Delivered FROM status WHERE TrackingID = ?");
    mysqli_stmt_bind_param($stmt, "s", $tid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($res && mysqli_num_rows($res) > 0) {
      $hide = ''; // reveal address update prompt
      $trackid = $tid;
      $status = mysqli_fetch_assoc($res);

      // Compute active steps
      if (!empty($status['Delivered'])) {
        $active = ['Received'=>'active', 'Shipped'=>'active', 'Out_for_delivery'=>'active', 'Delivered'=>'active'];
      } elseif (!empty($status['Out_for_delivery'])) {
        $active = ['Received'=>'active', 'Shipped'=>'active', 'Out_for_delivery'=>'active', 'Delivered'=>''];
      } elseif (!empty($status['Shipped'])) {
        $active = ['Received'=>'active', 'Shipped'=>'active', 'Out_for_delivery'=>'', 'Delivered'=>''];
      } elseif (!empty($status['Dispatched'])) {
        $active = ['Received'=>'active', 'Shipped'=>'', 'Out_for_delivery'=>'', 'Delivered'=>''];
      }
    } else {
      $error = 'Invalid Tracking ID';
    }
    mysqli_stmt_close($stmt);
  }
}

/* -------------------------------------------------
   Reveal friend-dropoff form
------------------------------------------------- */
if (isset($_POST['view'])) {
  $trackid = $_SESSION['track_tid'] ?? '';
  if ($trackid !== '') {
    $hidden = $hide = '';
  } else {
    $error = 'Enter a Tracking ID first.';
  }
}

/* -------------------------------------------------
   Update friend-dropoff
------------------------------------------------- */
if (isset($_POST['update'])) {
  $trackid = $_SESSION['track_tid'] ?? '';
  $hidden = $hide = '';

  $name = clean($_POST['fname'] ?? '');
  $add = clean($_POST['fadd'] ?? '');
  $contact = clean($_POST['fcontact'] ?? '');

  if ($name === '')   $errors['name'] = '*Required';
  if ($add === '')    $errors['add']  = '*Required';
  if ($contact === '')$errors['cont'] = '*Required';

  if (!array_filter($errors)) {
    // Prepared UPDATE
    $stmt = mysqli_prepare($conn, "UPDATE parcel SET R_Name = ?, R_Add = ?, R_Contact = ? WHERE TrackingID = ?");
    mysqli_stmt_bind_param($stmt, "ssss", $name, $add, $contact, $trackid);
    if (mysqli_stmt_execute($stmt)) {
      echo '<script type="text/javascript">
        setTimeout(function () {
          swal("Address Updated", "Receiver address updated successfully!", "success");
        }, 300);
      </script>';
      $hide   = 'hidden';
      $hidden = 'hidden';
      $trackid = '';
      $name = $add = $contact = '';
    } else {
      echo '<div class="alert alert-danger m-3">Update Error: '. e(mysqli_error($conn)) .'</div>';
    }
    mysqli_stmt_close($stmt);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Ummi's tracking — Track Shipment</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style/logo.css" rel="stylesheet">

  <!-- SweetAlert -->
  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F8FAFC; --ink:#111827; --muted:#6B7280; --ok:#16A34A; --warn:#F59E0B;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
    .navbar { backdrop-filter: saturate(180%) blur(6px); background: rgba(255,255,255,.9)!important; }
    .nav-link { font-weight: 500; color: var(--ink)!important; }
    .nav-link.active, .nav-link:hover { color: var(--brand-2)!important; }

    .section { padding: 48px 0; }
    .card-soft { border: none; border-radius: 16px; background: #fff; box-shadow: 0 10px 30px rgba(2,12,27,0.06); }

    .track-steps { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; }
    .step { position: relative; padding: 16px; border-radius: 14px; background: #F1F5F9; text-align: center; }
    .step .icon { font-size: 24px; display: inline-grid; place-items: center; width: 44px; height: 44px; border-radius: 50%; margin-bottom: 8px; background: #E5F0FF; color: var(--brand-2); }
    .step.active { background: #ECFDF5; }
    .step.active .icon { background: #DCFCE7; color: var(--ok); }
    .step .label { font-weight: 700; }
    .step .date { font-size: .9rem; color: var(--muted); margin-top: 4px; }

    .btn-pill { border-radius: 999px; padding: .7rem 1.2rem; font-weight: 600; }
    .footer { background: var(--brand); color: #fff; padding: 24px 0; margin-top: 48px; }
    .footer a { color: #E5E7EB; text-decoration: none; }
    .footer a:hover { color: #fff; text-decoration: underline; }

    .invalid { color: #DC2626; font-size: .875rem; }
  </style>
</head>
<body>

<!-- NAV -->
<?php if(isset($_SESSION['user_id'])): ?>
  <nav class="navbar navbar-expand-lg sticky-top shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="#"><div class="navbar-brand-logo" style="display: inline-flex;">
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
    </div></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
          <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
          <li class="nav-item"><span class="nav-link">Welcome, <?php echo e($user_name); ?></span></li>
          <li class="nav-item"><a class="nav-link text-danger" href="user_logout.php"><i class='bx bx-log-out-circle me-1'></i> Logout</a></li>
        </ul>
      </div>
    </div>
  </nav>
<?php else: ?>
  <nav class="navbar navbar-expand-lg sticky-top shadow-sm">
    <div class="container">
      <a class="navbar-brand" href="index.php"><div class="navbar-brand-logo" style="display: inline-flex;">
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
    </div></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
          <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
          <li class="nav-item"><a class="nav-link active" href="tracking.php">Tracking</a></li>
          <li class="nav-item"><a class="nav-link" href="branches.php">Branches</a></li>
          <?php if(isset($_SESSION['id'])): ?>
            <li class="nav-item"><a class="nav-link" href="staff.php">Dashboard</a></li>
            <li class="nav-item"><a class="nav-link text-danger" href="logout.php"><i class='bx bx-log-out-circle me-1'></i> Logout</a></li>
          <?php else: ?>
            <li class="nav-item"><a class="btn btn-sm btn-outline-primary px-3" href="login.php">Ummi's tracking Login</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
<?php endif; ?>

<!-- CONTENT -->
<section class="section">
  <div class="container">
    <div class="row g-4">
      <!-- LEFT: Track form -->
      <div class="col-lg-4">
        <div class="card-soft p-4 text-center">
          <img src="Images/track.png" class="img-fluid rounded-4 mb-3" alt="Track shipment" style="max-height:220px; object-fit:cover;">
          <form method="POST" class="text-start">
            <label class="form-label fw-semibold">Tracking ID</label>
            <input type="text" class="form-control form-control-lg" name="tid" value="<?php echo e($tid); ?>" placeholder="e.g. DX-93820-AB">
            <?php if($error): ?><div class="invalid mt-1"><?php echo e($error); ?></div><?php endif; ?>
            <button type="submit" name="track" class="btn btn-primary btn-pill mt-3 w-100"><i class='bx bx-search-alt-2 me-1'></i> Track</button>
          </form>
        </div>
      </div>

      <!-- RIGHT: Status & optional redirection -->
      <div class="col-lg-8">
        <div class="card-soft p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="mb-0">Delivery Status</h3>
            <div class="text-muted">Tracking ID: <strong><?php echo e($trackid); ?></strong></div>
          </div>
          <hr>

          <!-- Steps -->
          <div class="track-steps">
            <div class="step <?php echo $active['Received']; ?>">
              <div class="icon"><i class='bx bx-map-pin'></i></div>
              <div class="label">Received / Dispatched</div>
              <div class="date"><?php echo e(fmt_date($status['Dispatched'] ?? '')); ?></div>
            </div>
            <div class="step <?php echo $active['Shipped']; ?>">
              <div class="icon"><i class='bx bx-truck'></i></div>
              <div class="label">Shipped</div>
              <div class="date"><?php echo e(fmt_date($status['Shipped'] ?? '')); ?></div>
            </div>
            <div class="step <?php echo $active['Out_for_delivery']; ?>">
              <div class="icon"><i class='bx bx-cube'></i></div>
              <div class="label">Out for delivery</div>
              <div class="date"><?php echo e(fmt_date($status['Out_for_delivery'] ?? '')); ?></div>
            </div>
            <div class="step <?php echo $active['Delivered']; ?>">
              <div class="icon"><i class='bx bx-check'></i></div>
              <div class="label">Delivered</div>
              <div class="date"><?php echo e(fmt_date($status['Delivered'] ?? '')); ?></div>
            </div>
          </div>

          <!-- Address redirection -->
          <div class="mt-4" <?php echo $hide; ?>>
            <div class="alert alert-info d-flex align-items-start">
              <i class='bx bx-info-circle me-2 fs-4'></i>
              <div>
                <div class="fw-semibold">Unable to receive on the expected date?</div>
                <small>We can drop off with a trusted friend nearby in your city.</small>
              </div>
            </div>

            <form method="POST" class="mb-3">
              <button type="submit" name="view" class="btn btn-outline-primary btn-pill"><i class='bx bx-user-plus me-1'></i> Update Delivery Address</button>
            </form>

            <form method="POST" <?php echo $hidden; ?> class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Friend’s Name</label>
                <input type="text" name="fname" class="form-control" value="<?php echo e($name); ?>" placeholder="Jane Doe">
                <?php if($errors['name']): ?><div class="invalid mt-1"><?php echo e($errors['name']); ?></div><?php endif; ?>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact</label>
                <input type="text" name="fcontact" class="form-control" value="<?php echo e($contact); ?>" placeholder="+234 801 234 5678">
                <?php if($errors['cont']): ?><div class="invalid mt-1"><?php echo e($errors['cont']); ?></div><?php endif; ?>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <input type="text" name="fadd" class="form-control" value="<?php echo e($add); ?>" placeholder="12 Adewale St, Garki, Abuja">
                <?php if($errors['add']): ?><div class="invalid mt-1"><?php echo e($errors['add']); ?></div><?php endif; ?>
              </div>
              <div class="col-12">
                <button type="submit" name="update" class="btn btn-primary btn-pill"><i class='bx bx-save me-1'></i> Update</button>
              </div>
            </form>
          </div>

        </div>
      </div>

    </div>
  </div>
</section>

<!-- FOOTER -->
<footer class="footer">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <p class="mb-0">&copy; 2025 Ummi's tracking. All Rights Reserved. | Delivering Beyond Borders</p>
    <div class="d-flex gap-3">
      <a href="index.php">Home</a>
      <a href="tracking.php">Tracking</a>
      <a href="branches.php">Branches</a>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
