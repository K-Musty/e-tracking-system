<?php 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is staff
if (!isset($_SESSION['id']) || empty($_SESSION['id'])) {
    $_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
    header("Location: login.php");
    exit();
}

include("db_connect.php");
date_default_timezone_set('Asia/Dhaka');

// Small helpers (safe output)
function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// Verify staff exists in database
$id = $_SESSION['id'];
$sql = "SELECT * FROM staff WHERE StaffID=?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    session_destroy();
    header("Location: login.php?error=invalid_staff");
    exit();
}

$staff = mysqli_fetch_assoc($result);
$name = $staff['Name'] ?? 'Staff';

// Initialize variables
$sname = $sadd = $scity = $sstate = $scontact = $rname = $radd = $rcity = $rstate = $rcontact = $wgt = '';
$status = array('disp' => '', 'ship' => '', 'out' => '', 'del' => '');
$inp_tid = '';
$disable_del = $disable_out = $disable_ship = '';
$errors = array('req' => '');

if(isset($_POST['submit'])){
    if(empty($_POST['sname'])){ $errors['req'] = '*Required Field'; } else{ $sname = $_POST['sname']; }
    if(empty($_POST['sadd'])){ $errors['req'] = '*Required Field'; } else{ $sadd = $_POST['sadd']; }
    if(empty($_POST['scity'])){ $errors['req'] = '*Required Field'; } else{ $scity = $_POST['scity']; }
    if(empty($_POST['sstate'])){ $errors['req'] = '*Required Field'; } else{ $sstate = $_POST['sstate']; }
    if(empty($_POST['scontact'])){ $errors['req'] = '*Required Field'; } else{ $scontact = $_POST['scontact']; }
    if(empty($_POST['rname'])){ $errors['req'] = '*Required Field'; } else{ $rname = $_POST['rname']; }
    if(empty($_POST['radd'])){ $errors['req'] = '*Required Field'; } else{ $radd = $_POST['radd']; }
    if(empty($_POST['rcity'])){ $errors['req'] = '*Required Field'; } else{ $rcity = $_POST['rcity']; }
    if(empty($_POST['rstate'])){ $errors['req'] = '*Required Field'; } else{ $rstate = $_POST['rstate']; }
    if(empty($_POST['rcontact'])){ $errors['req'] = '*Required Field'; } else{ $rcontact = $_POST['rcontact']; }
    if(empty($_POST['wgt'])){ $errors['req'] = '*Required Field'; } else{ $wgt = $_POST['wgt']; }
    
    if(!array_filter($errors)){
        $price = 0;
        $sql = "SELECT * FROM pricing WHERE State_1 = ? AND State_2 = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $sstate, $rstate);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result) > 0){
            $pricing = mysqli_fetch_assoc($result);
            $price = $pricing['Cost'] * $wgt;
            
            $sql = "INSERT INTO parcel (StaffID, S_Name, S_Add, S_City, S_State, S_Contact, 
                    R_Name, R_Add, R_City, R_State, R_Contact, Weight_Kg, Price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssssssssdd", 
                $id, $sname, $sadd, $scity, $sstate, $scontact, 
                $rname, $radd, $rcity, $rstate, $rcontact, $wgt, $price);
            
            if(mysqli_stmt_execute($stmt)){
                $tid = mysqli_insert_id($conn);
                $_SESSION['tid'] = $tid;
                header("Location: receipt.php");
                exit();
            }else{
                echo "Error : " . mysqli_error($conn);
            }
        }else{
            echo '<script type="text/javascript">';
            echo "setTimeout(function () { swal('Service Not Available', 
                \"We don't have an office in this area—our delivery team is too busy zooming around!\", 'info');";
            echo '}, 600);</script>';
        }
    }
}

if(isset($_POST['sel_order'])){
    if(empty($_POST['inp_tid'])){
        $errors['status'] = '*Required Field';
    }else{
        $inp_tid = htmlspecialchars($_POST['inp_tid']);
    }
    
    if (!empty($inp_tid)) {
        $sql = "SELECT * FROM status WHERE TrackingID = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $inp_tid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if(mysqli_num_rows($result)){
            $del_status = mysqli_fetch_assoc($result);
            $status['disp'] = $del_status['Dispatched'];
            $status['ship'] = $del_status['Shipped'];
            $status['out']  = $del_status['Out_for_delivery'];
            $status['del']  = $del_status['Delivered'];
            $inp_tid = $del_status['TrackingID'];
            $_SESSION['up_tid'] = $inp_tid;
            
            // Set disable flags based on status
            if(!is_null($status['del'])){
                $disable_del = $disable_out = $disable_ship = "disabled";
            }elseif(!is_null($status['out'])){
                $disable_out = $disable_ship = "disabled";
            }elseif(!is_null($status['ship'])){
                $disable_ship = "disabled";
            }
            if(is_null($status['ship'])){
                $disable_del = $disable_out = "disabled";
            }elseif(is_null($status['out'])){
                $disable_del = "disabled";
            }
        }else{
            $errors['status'] = 'Enter a valid tracking ID';
        }
    }
}

if(isset($_POST['update']) && isset($_SESSION['up_tid'])){
    $checked = $_POST['status_upd'];
    $inp_tid = $_SESSION['up_tid'];
    
    $sql = "";
    switch($checked) {
        case 'delivered':
            $sql = "UPDATE status SET Delivered=CURRENT_TIMESTAMP WHERE TrackingID=?";
            break;
        case 'out_for_delivery':
            $sql = "UPDATE status SET Out_for_delivery=CURRENT_TIMESTAMP WHERE TrackingID=?";
            break;
        case 'shipped':
            $sql = "UPDATE status SET Shipped=CURRENT_TIMESTAMP WHERE TrackingID=?";
            break;
    }
    
    if(!empty($sql)) {
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $inp_tid);
        if(!mysqli_stmt_execute($stmt)){
            echo 'Error : '. mysqli_error($conn);
        }
    }
}

// Fetch arrived and delivered parcels
$sql = "SELECT * FROM arrived";
$result = mysqli_query($conn, $sql);
$arr = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

$sql = "SELECT * FROM delivered";
$result = mysqli_query($conn, $sql);
$delivered = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Ummi's tracking — Staff Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <!-- Fonts & Bootstrap 5 -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style/logo.css" rel="stylesheet">

  <!-- SweetAlert -->
  <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

  <style>
    :root{
      --brand:#0A3D62; --brand-2:#0F5CA8; --bg:#F6F8FC; --ink:#111827; --muted:#6B7280;
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bg); color: var(--ink); }
    .navbar { backdrop-filter: saturate(180%) blur(6px); background: rgba(255,255,255,.9)!important; }
    .avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; }
    .card-soft { border: 0; border-radius: 16px; box-shadow: 0 12px 28px rgba(2,12,27,.06); }
    .section-pad { padding: 16px 0 48px; }
    .form-control, .form-select { border-radius: 10px; padding: .65rem .85rem; }
    .tab-hero { background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 60%, #0D6EFD 100%); border-radius: 16px; color:#fff; }
    .nav-tabs .nav-link { border: none; color: var(--muted); font-weight:600; }
    .nav-tabs .nav-link.active { color: var(--brand-2); border-bottom: 3px solid var(--brand-2); }
    .badge-soft { background:#EEF2FF; color:#3B82F6; border-radius:999px; padding:.25rem .55rem; font-weight:600; }
    table td, table th { vertical-align: middle; }
  </style>
</head>
<body>

<!-- Top Bar -->
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

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="topnav">
      <ul class="navbar-nav ms-auto align-items-center gap-3">
        <li class="nav-item"><a class="nav-link" href="staff_request_approval.php">Pending Request</a></li>
        <li class="nav-item"><a class="nav-link" href="account.php">Profile</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <img class="avatar" src="Images/pp2.png" alt="Avatar">
            <span class="fw-semibold"><?php echo e($name); ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="account.php">Account</a></li>
            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- Header band -->
<div class="container my-3">
  <div class="tab-hero p-4 p-md-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <div>
        <h2 class="mb-1">Staff Dashboard</h2>
        <div class="opacity-75">Create new orders, update statuses, and review shipments.</div>
      </div>
      <span class="badge-soft">Logged in as <?php echo e($id); ?></span>
    </div>
  </div>
</div>

<div class="container section-pad">
  <!-- Tabs -->
  <ul class="nav nav-tabs" id="mainTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="ins-tab" data-bs-toggle="tab" data-bs-target="#ins" type="button" role="tab" aria-controls="ins" aria-selected="true">New Order</button>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" href="staff_request_approval.php">Pending Request</a>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="update-tab" data-bs-toggle="tab" data-bs-target="#update" type="button" role="tab" aria-controls="update" aria-selected="false">Update Order</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="cons-tab" data-bs-toggle="tab" data-bs-target="#cons" type="button" role="tab" aria-controls="cons" aria-selected="false">Invoice</button>
    </li>
    <li class="nav-item" role="presentation">
      <a class="nav-link" href="account.php">Profile</a>
    </li>
  </ul>

  <div class="tab-content mt-3" id="mainTabsContent">
    <!-- New Order -->
    <div class="tab-pane fade show active" id="ins" role="tabpanel" aria-labelledby="ins-tab">
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card card-soft">
            <div class="card-header bg-white">
              <h5 class="mb-0">Sender’s Details</h5>
            </div>
            <div class="card-body">
              <form action="<?php echo e($_SERVER['PHP_SELF']); ?>" method="POST" class="form">
                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input type="text" name="sname" class="form-control" required>
                  <?php if(!empty($errors['req'])): ?><div class="text-danger small mt-1"><?php echo e($errors['req']); ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input type="text" name="sadd" class="form-control" required>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="scity" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">State</label>
                    <input type="text" name="sstate" class="form-control" required>
                  </div>
                </div>
                <div class="mt-3">
                  <label class="form-label">Contact</label>
                  <input type="text" name="scontact" class="form-control" required>
                </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card card-soft h-100">
            <div class="card-header bg-white">
              <h5 class="mb-0">Receiver’s Details</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                  <label class="form-label">Name</label>
                  <input type="text" name="rname" class="form-control" required>
                </div>
                <div class="mb-3">
                  <label class="form-label">Address</label>
                  <input type="text" name="radd" class="form-control" required>
                </div>
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label">City</label>
                    <input type="text" name="rcity" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">State</label>
                    <input type="text" name="rstate" class="form-control" required>
                  </div>
                </div>
                <div class="row g-3 mt-1">
                  <div class="col-md-6">
                    <label class="form-label">Contact</label>
                    <input type="text" name="rcontact" class="form-control" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Weight (kg)</label>
                    <input type="text" name="wgt" class="form-control" required>
                  </div>
                </div>
                <div class="mt-4">
                  <button type="submit" name="submit" class="btn btn-primary w-100">Place Order</button>
                </div>
              </form>
            </div>
          </div>
        </div> 
      </div> <!-- row -->
    </div>

    <!-- Update Order -->
    <div class="tab-pane fade" id="update" role="tabpanel" aria-labelledby="update-tab">
      <div class="row g-3">
        <div class="col-lg-4">
          <div class="card card-soft">
            <div class="card-header bg-white">
              <h5 class="mb-0">Select Order</h5>
            </div>
            <div class="card-body">
              <form action="" method="POST" class="form">
                <label class="form-label">Tracking ID</label>
                <input type="text" name="inp_tid" class="form-control" value="<?php echo e($_SESSION['up_tid'] ?? ($status['TrackingID'] ?? '')); ?>">
                <?php if(!empty($errors['status'])): ?><div class="text-danger small mt-1"><?php echo e($errors['status']); ?></div><?php endif; ?>
                <button type="submit" name="sel_order" class="btn btn-outline-primary mt-3 w-100">Select</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="card card-soft">
            <div class="card-header bg-white">
              <h5 class="mb-0">Order Details</h5>
            </div>
            <div class="card-body">
              <form action="<?php echo e($_SERVER['PHP_SELF']); ?>" method="POST" class="form">
                <div class="mb-2">
                  <span class="text-muted">Tracking ID :</span>
                  <strong><?php echo e($_SESSION['up_tid'] ?? ($status['TrackingID'] ?? '')); ?></strong>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="dispatched" name="status_upd" id="chk-dispatched" disabled>
                  <label class="form-check-label" for="chk-dispatched">Dispatched</label>
                  <div class="small text-muted"><?php echo e($status['disp']); ?></div>
                </div>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" value="shipped" name="status_upd" id="chk-shipped" <?php echo $disable_ship; ?>>
                  <label class="form-check-label" for="chk-shipped">Shipped</label>
                  <div class="small text-muted"><?php echo e($status['ship']); ?></div>
                </div>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" value="out_for_delivery" name="status_upd" id="chk-out" <?php echo $disable_out; ?>>
                  <label class="form-check-label" for="chk-out">Out for Delivery</label>
                  <div class="small text-muted"><?php echo e($status['out']); ?></div>
                </div>
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" value="delivered" name="status_upd" id="chk-del" <?php echo $disable_del; ?>>
                  <label class="form-check-label" for="chk-del">Delivered</label>
                  <div class="small text-muted"><?php echo e($status['del']); ?></div>
                </div>
                <button type="submit" name="update" class="btn btn-primary mt-3">Update Details</button>
              </form>
            </div>
          </div>
        </div>
      </div><!-- row -->
    </div>

    <!-- Invoice (Arrived/Delivered tables) -->
    <div class="tab-pane fade" id="cons" role="tabpanel" aria-labelledby="cons-tab">
      <ul class="nav nav-tabs" id="subTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="arr-tab" data-bs-toggle="tab" data-bs-target="#arr" type="button" role="tab" aria-controls="arr" aria-selected="true">Arrived</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="del-tab" data-bs-toggle="tab" data-bs-target="#del" type="button" role="tab" aria-controls="del" aria-selected="false">Delivered</button>
        </li>
      </ul>

      <div class="tab-content mt-3">
        <div class="tab-pane fade show active" id="arr" role="tabpanel" aria-labelledby="arr-tab">
          <div class="card card-soft">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>TrackingID</th><th>StaffID</th><th>Sender</th><th>Receiver</th><th>Weight</th><th>Price</th><th>Dispatched</th><th>Shipped</th><th>Out for delivery</th><th>Delivered</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($arr as $order): ?>
                      <tr>
                        <td><?php echo e($order['TrackingID']); ?></td>
                        <td><?php echo e($order['StaffID']); ?></td>
                        <td><?php echo e($order['S_Name'].', '.$order['S_Add'].', '.$order['S_City'].', '.$order['S_State'].' - '.$order['S_Contact']); ?></td>
                        <td><?php echo e($order['R_Name'].', '.$order['R_Add'].', '.$order['R_City'].', '.$order['R_State'].' - '.$order['R_Contact']); ?></td>
                        <td><?php echo e($order['Weight_Kg']); ?></td>
                        <td><?php echo e($order['Price']); ?></td>
                        <td><?php echo e($order['Dispatched_Time']); ?></td>
                        <td><?php echo e($order['Shipped']); ?></td>
                        <td><?php echo e($order['Out_for_delivery']); ?></td>
                        <td><?php echo e($order['Delivered']); ?></td>
                      </tr>
                    <?php endforeach;?>
                    <?php if(empty($arr)): ?>
                      <tr><td colspan="10" class="text-center text-muted">No records.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="del" role="tabpanel" aria-labelledby="del-tab">
          <div class="card card-soft">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>TrackingID</th><th>StaffID</th><th>Sender</th><th>Receiver</th><th>Weight</th><th>Price</th><th>Dispatched</th><th>Shipped</th><th>Out for delivery</th><th>Delivered</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($delivered as $order): ?>
                      <tr>
                        <td><?php echo e($order['TrackingID']); ?></td>
                        <td><?php echo e($order['StaffID']); ?></td>
                        <td><?php echo e($order['S_Name'].', '.$order['S_Add'].', '.$order['S_City'].', '.$order['S_State'].' - '.$order['S_Contact']); ?></td>
                        <td><?php echo e($order['R_Name'].', '.$order['R_Add'].', '.$order['R_City'].', '.$order['R_State'].' - '.$order['R_Contact']); ?></td>
                        <td><?php echo e($order['Weight_Kg']); ?></td>
                        <td><?php echo e($order['Price']); ?></td>
                        <td><?php echo e($order['Dispatched_Time']); ?></td>
                        <td><?php echo e($order['Shipped']); ?></td>
                        <td><?php echo e($order['Out_for_delivery']); ?></td>
                        <td><?php echo e($order['Delivered']); ?></td>
                      </tr>
                    <?php endforeach;?>
                    <?php if(empty($delivered)): ?>
                      <tr><td colspan="10" class="text-center text-muted">No records.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div><!-- sub tab content -->
    </div>

  </div><!-- main tab content -->
</div><!-- container -->

<!-- Footer -->
<footer class="mt-4">
  <div class="container">
    <div class="text-center small text-muted py-3">
      &copy; 2025 Ummi's tracking. All Rights Reserved. | Delivering Beyond Borders
    </div>
  </div>
</footer>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Persist active tab (main)
document.querySelectorAll('#mainTabs button[data-bs-toggle="tab"]').forEach(btn=>{
  btn.addEventListener('shown.bs.tab', e=>{
    localStorage.setItem('staff_lastTab', e.target.getAttribute('data-bs-target'));
  });
});
const last = localStorage.getItem('staff_lastTab');
if (last && document.querySelector(`#mainTabs button[data-bs-target="${last}"]`)) {
  new bootstrap.Tab(document.querySelector(`#mainTabs button[data-bs-target="${last}"]`)).show();
}

// Persist sub-tab in Invoice section
document.querySelectorAll('#subTabs button[data-bs-toggle="tab"]').forEach(btn=>{
  btn.addEventListener('shown.bs.tab', e=>{
    localStorage.setItem('staff_lastSubTab', e.target.getAttribute('data-bs-target'));
  });
});
const lastSub = localStorage.getItem('staff_lastSubTab');
if (lastSub && document.querySelector(`#subTabs button[data-bs-target="${lastSub}"]`)) {
  new bootstrap.Tab(document.querySelector(`#subTabs button[data-bs-target="${lastSub}"]`)).show();
}
</script>
</body>
</html>
