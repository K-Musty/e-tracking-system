<?php
session_start();
include("db_connect.php");

/* --- DATA: Employee of the Month (highest credits) --- */
$sql = "SELECT * FROM staff WHERE credits = (SELECT MAX(credits) FROM staff)";
$result = mysqli_query($conn, $sql);
$empmonth = [];
if ($result && mysqli_num_rows($result) > 0) {
  $empmonth = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

/* --- FEEDBACK FORM HANDLING --- */
$name = $email = $msg = '';
$error = ['name'=>'', 'email'=>'', 'msg'=>''];

function clean($v) {
  return trim($v ?? '');
}
function e($v) { // safe echo
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

if (isset($_POST['submit'])) {
  $name = clean($_POST['name']);
  $email = clean($_POST['email']);
  $msg = clean($_POST['msg']);

  if ($name === '') $error['name'] = '*Required';
  if ($email === '') {
    $error['email'] = '*Required';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error['email'] = '*Invalid email';
  }
  if ($msg === '') $error['msg'] = '*Required';

  if (!array_filter($error)) {
    // Prepared statement (safer)
    $stmt = mysqli_prepare($conn, "INSERT INTO feedback (Cust_name, Cust_mail, Cust_msg) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "sss", $name, $email, $msg);
    if (mysqli_stmt_execute($stmt)) {
      echo '<script type="text/javascript">
              setTimeout(function () {
                swal("Thank you!", "Your response was recorded successfully.", "success");
              }, 400);
            </script>';
      $name = $email = $msg = '';
    } else {
      error_log("Insert Error: ". mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Ummi's tracking — Delivering Beyond Borders</title>
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
      --brand:#0A3D62;         /* Deep navy (GIGL-esque) */
      --brand-2:#0F5CA8;       /* Accent blue */
      --brand-3:#F8FAFC;       /* Soft background */
      --highlight:#FAD02C;     /* Warm highlight */
      --ink:#1F2937;           /* Dark text */
      --muted:#6B7280;
      --glass:rgba(255,255,255,0.7);
    }
    html, body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; color: var(--ink); }
    
    /* Modern Navbar */
    .navbar { 
      backdrop-filter: saturate(180%) blur(20px); 
      background: linear-gradient(135deg, var(--brand) 0%, var(--brand-2) 100%)!important;
      border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    
    .navbar-brand-text {
      font-weight: 700;
      color: white;
      font-size: 1.1rem;
      margin: 0;
    }
    
    .nav-link-modern {
      font-weight: 500;
      color: rgba(255,255,255,0.9)!important;
      padding: 8px 16px!important;
      border-radius: 8px;
      transition: all 0.3s ease;
      margin: 0 2px;
    }
    
    .nav-link-modern:hover {
      color: white!important;
      background: rgba(255,255,255,0.1);
      transform: translateY(-1px);
    }
    
    .nav-link-modern.active {
      color: white!important;
      background: rgba(255,255,255,0.15);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .btn-login {
      background: var(--highlight);
      color: var(--brand)!important;
      border: none;
      padding: 8px 20px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-login:hover {
      background: #E6B800;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(250, 208, 44, 0.3);
    }
    
    .btn-logout {
      background: rgba(239, 68, 68, 0.1);
      color: #EF4444!important;
      border: 1px solid rgba(239, 68, 68, 0.3);
      padding: 8px 16px;
      border-radius: 8px;
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
    }
    
    .btn-logout:hover {
      background: rgba(239, 68, 68, 0.2);
      color: #DC2626!important;
      transform: translateY(-1px);
      text-decoration: none;
    }
    
    .dropdown-menu {
      border: none;
      box-shadow: 0 10px 30px rgba(0,0,0,0.15);
      border-radius: 12px;
      padding: 8px;
      margin-top: 8px;
    }
    
    .dropdown-item {
      border-radius: 8px;
      padding: 10px 16px;
      transition: all 0.3s ease;
    }
    
    .dropdown-item:hover {
      background: var(--brand-3);
      color: var(--brand);
    }

    /* Hero */
    .hero {
      position: relative;
      background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 40%, #0D6EFD 100%);
      color: #fff;
      overflow: hidden;
    }
    .hero .glass {
      background: rgba(255,255,255,0.09);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 16px;
      padding: 1rem 1.25rem;
      backdrop-filter: blur(6px);
    }
    .hero-cta .btn {
      border-radius: 999px;
      padding: .75rem 1.25rem;
      font-weight: 600;
    }
    .btn-highlight {
      background: var(--highlight);
      color: #16213E;
      border: none;
    }
    .btn-outline-white {
      border: 2px solid rgba(255,255,255,0.9);
      color: #fff;
      background: transparent;
    }
    .btn-outline-white:hover { background: rgba(255,255,255,0.1); }

    /* Sections */
    .section-pad { padding: 64px 0; }
    .card-soft {
      border: none;
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 10px 30px rgba(2,12,27,0.06);
    }
    .icon-pill {
      width: 48px; height: 48px; border-radius: 12px;
      display: grid; place-items: center;
      background: #EFF6FF; color: var(--brand-2); font-size: 22px;
    }

    /* Employee of the Month */
    .eom {
      background: linear-gradient(180deg, #F8FAFF 0%, #FFFFFF 100%);
      border-top: 1px solid #EEF2F7;
      border-bottom: 1px solid #EEF2F7;
    }

    /* Footer */
    footer {
      background: var(--brand);
      color: #fff;
      padding: 28px 0;
    }
    footer a { color: #E5E7EB; text-decoration: none; }
    footer a:hover { color: #fff; text-decoration: underline; }

    /* Forms */
    .form-control, .form-select { border-radius: 12px; }
    .invalid { color: #DC2626; font-size: .875rem; }

    /* Utilities */
    .muted { color: var(--muted); }
  </style>
</head>
<body>

<!-- NAV -->
<nav class="navbar navbar-expand-lg sticky-top shadow-lg">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center" href="index.php" aria-label="Ummi's tracking Home">
      <img src="Images/logo.svg" alt="Ummi's tracking" style="height: 45px; width: auto;" class="me-2">
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <i class='bx bx-menu text-white fs-4'></i>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item">
          <a class="nav-link nav-link-modern active" href="index.php">
            <i class='bx bx-home me-1'></i>Home
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-modern" href="tracking.php">
            <i class='bx bx-search-alt me-1'></i>Tracking
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-modern" href="branches.php">
            <i class='bx bx-buildings me-1'></i>Branches
          </a>
        </li>
        <?php if (isset($_SESSION['id']) || isset($_SESSION['user_id'])): ?>
          <?php if (isset($_SESSION['id'])): ?>
            <li class="nav-item">
              <a class="nav-link nav-link-modern" href="staff.php">
                <i class='bx bx-briefcase me-1'></i>Dashboard
              </a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link nav-link-modern" href="user_dashboard.php">
                <i class='bx bx-user-circle me-1'></i>Dashboard
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item ms-lg-2">
            <a class="btn btn-logout" href="logout.php" id="logoutLink">
              <i class='bx bx-log-out me-1'></i>Logout
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <div class="dropdown">
              <button class="btn btn-login dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class='bx bx-log-in me-1'></i>Login
              </button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="user_login.php"><i class='bx bx-user me-2'></i>User Portal</a></li>
                <li><a class="dropdown-item" href="login.php"><i class='bx bx-briefcase me-2'></i>Staff Portal</a></li>
                <li><a class="dropdown-item" href="admin_login.php"><i class='bx bx-shield-alt-2 me-2'></i>Admin Portal</a></li>
              </ul>
            </div>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<<!-- HERO -->
<header class="hero py-5 py-lg-6">
  <div class="container">
    <div class="row align-items-center g-4">
      
      <!-- Left Text Section -->
      <div class="col-lg-7 text-white">
        <span class="badge bg-warning text-dark fw-semibold mb-3">E-Tracking System</span>
        <h1 class="display-5 fw-bold">
          Digital Transformation of <br/> 
          <span class="text-warning">3PL Logistics</span> in Nigeria
        </h1>
        <p class="lead mt-3 opacity-90">
          Ummi's tracking is a prototype <strong>E-Tracking System</strong> designed for third-party logistics (3PL) operators (GIG and NIPOST).  
          Built to address challenges of <em>manual waybills, lack of real-time tracking, and customer dissatisfaction</em>,  
          Ummi's tracking enables transparent, fast, and reliable parcel management.
        </p>

        <div class="hero-cta d-flex flex-wrap gap-2 mt-4">
          <a href="#about" class="btn btn-highlight">Learn About the System</a>
          <a href="tracking.php" class="btn btn-outline-white">Track Your Parcel</a>
        </div>

        <!-- Quick tracking bar -->
        <div class="glass mt-4 shadow">
          <form class="row g-2" action="tracking.php" method="get" aria-label="Quick tracking form">
            <div class="col-12 col-md">
              <label for="tn" class="form-label visually-hidden">Tracking Number</label>
              <input id="tn" name="tn" class="form-control form-control-lg" placeholder="Enter tracking number…" />
            </div>
            <div class="col-12 col-md-auto">
              <button class="btn btn-light btn-lg w-100" type="submit">
                <i class='bx bx-search-alt-2 me-1'></i> Track
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Image Section -->
      <div class="col-lg-5">
        <img src="Images/bigp.svg" 
             alt="Ummi's tracking prototype system" 
             class="img-fluid rounded-4 shadow-lg border border-3 border-white" />
        <div class="mt-3 p-3 bg-dark bg-opacity-75 text-white rounded-3 shadow-sm">
          <h6 class="mb-1">Research Context</h6>
          <p class="small opacity-85 mb-0">
            This system is part of the study:  
            <em>“Design of an E-Tracking System for Third-Party Logistics Operators in Bauchi Metropolis, Nigeria.”</em>
          </p>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- SERVICES -->
<section class="section-pad bg-light">
  <div class="container">
    <div class="row mb-4">
      <div class="col-lg-8">
        <h2 class="fw-bold">Services Available</h2>
        <p class="muted">Discover logistics options from Ummi's tracking Global Forwarding, tailored for businesses of all sizes.</p>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-sm-6 col-lg-4">
        <div class="card-soft p-4 h-100">
          <div class="icon-pill mb-3"><i class='bx bx-plane-alt'></i></div>
          <h5 class="fw-semibold mb-1">Air Freight</h5>
          <p class="muted mb-0">Priority air cargo with speed, visibility, and reliability.</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card-soft p-4 h-100">
          <div class="icon-pill mb-3"><i class='bx bx-highway'></i></div>
          <h5 class="fw-semibold mb-1">Road Freight</h5>
          <p class="muted mb-0">Domestic and cross-border trucking with smart routing.</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card-soft p-4 h-100">
          <div class="icon-pill mb-3"><i class='bx bx-water'></i></div>
          <h5 class="fw-semibold mb-1">Ocean Freight</h5>
          <p class="muted mb-0">FCL/LCL solutions with predictable transit times.</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card-soft p-4 h-100">
          <div class="icon-pill mb-3"><i class='bx bx-train'></i></div>
          <h5 class="fw-semibold mb-1">Rail Freight</h5>
          <p class="muted mb-0">Cost-effective, lower-emission intermodal shipping.</p>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card-soft p-4 h-100">
          <div class="icon-pill mb-3"><i class='bx bx-time'></i></div>
          <h5 class="fw-semibold mb-1">Express Delivery</h5>
          <p class="muted mb-0">Time-critical shipments with guaranteed delivery windows.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ABOUT -->
<section id="about" class="section-pad">
  <div class="container">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <div class="card-soft p-4 p-md-5">
          <h2 class="fw-bold mb-3 text-dark">About Ummi's tracking</h2>
          <p>
            <strong>Ummi's tracking</strong> is more than a logistics provider—it is an <em>E-Tracking System</em> 
            designed to bring digital transformation to third-party logistics (3PL) operators (GIG and NIPOST) in Nigeria and beyond.  
            Our platform enables real-time parcel tracking, transparent supply chains, and customer trust.
          </p>
          <p>
            <strong>Our Mission:</strong> To simplify shipping by blending innovation, 
            technology, and efficiency—delivering end-to-end solutions from parcels to bulk freight, 
            while reducing fraud, delays, and uncertainty in logistics.
          </p>
          <h5 class="mt-3">Why Choose Ummi's tracking?</h5>
          <ul class="mt-2">
            <li><strong>Real-time tracking</strong> with instant status updates.</li>
            <li><strong>Global reach</strong> across major hubs and local networks.</li>
            <li><strong>Fast & secure</strong> handling with precision and care.</li>
            <li><strong>Customer-first</strong> support available 24/7.</li>
            <li><strong>Sustainability</strong> through greener operations.</li>
          </ul>
        </div>
      </div>
      <div class="col-lg-6">
        <img src="Images/aboutus.svg" alt="About Ummi's tracking" class="img-fluid rounded-4 shadow-sm mb-3">
        <div class="row g-3">
          <div class="col-4"><img src="Images/last.svg"   alt="Gallery" class="img-fluid rounded-3"></div>
          <div class="col-4"><img src="Images/icon2.svg" alt="Gallery" class="img-fluid rounded-3"></div>
          <div class="col-4"><img src="Images/worker.svg"alt="Gallery" class="img-fluid rounded-3"></div>
          <div class="col-4"><img src="Images/icon4.svg"  alt="Gallery" class="img-fluid rounded-3"></div>
          <div class="col-4"><img src="Images/icon5.svg" alt="Gallery" class="img-fluid rounded-3"></div>
          <div class="col-4"><img src="Images/icon1.svg" alt="Gallery" class="img-fluid rounded-3"></div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- EMPLOYEE OF THE MONTH -->
<section class="eom section-pad">
  <div class="container">
    <div class="text-center mb-4">
      <h2 class="fw-bold">Employee of the Month</h2>
      <p class="muted">Celebrating excellence and outstanding contributions at Ummi's tracking.</p>
    </div>

    <?php if (!empty($empmonth)): ?>
      <div class="row g-4">
        <?php foreach ($empmonth as $emp): ?>
          <div class="col-md-6 col-lg-4">
            <div class="card-soft p-4 h-100 text-center">
              <img src="Images/ofthemonth.svg" alt="Award" class="img-fluid rounded-3 mb-3">
              <h5 class="fw-bold text-warning mb-1"><?php echo e($emp['Name']); ?></h5>
              <p class="mb-1">Staff ID: <strong><?php echo e($emp['StaffID']); ?></strong></p>
              <p class="mb-0">Credits: <strong><?php echo e($emp['Credits']); ?></strong></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-light border text-center">No award data to display.</div>
    <?php endif; ?>
  </div>
</section>

<!-- FEEDBACK (Optional contact/feedback) -->
<section class="section-pad bg-light">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-6">
        <h3 class="fw-bold">We’d love your feedback</h3>
        <p class="muted">Tell us how we’re doing or what we can improve.</p>

        <form method="post" class="mt-3">
          <div class="mb-3">
            <label class="form-label fw-semibold" for="name">Full name</label>
            <input type="text" id="name" name="name" value="<?php echo e($name); ?>" class="form-control <?php echo $error['name'] ? 'is-invalid' : ''; ?>" placeholder="Jane Doe">
            <?php if($error['name']): ?><div class="invalid"><?php echo e($error['name']); ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="email">Email address</label>
            <input type="email" id="email" name="email" value="<?php echo e($email); ?>" class="form-control <?php echo $error['email'] ? 'is-invalid' : ''; ?>" placeholder="jane@company.com">
            <?php if($error['email']): ?><div class="invalid"><?php echo e($error['email']); ?></div><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="msg">Message</label>
            <textarea id="msg" name="msg" rows="4" class="form-control <?php echo $error['msg'] ? 'is-invalid' : ''; ?>" placeholder="Your message…"><?php echo e($msg); ?></textarea>
            <?php if($error['msg']): ?><div class="invalid"><?php echo e($error['msg']); ?></div><?php endif; ?>
          </div>
          <button type="submit" name="submit" class="btn btn-primary px-4"><i class='bx bx-send me-1'></i> Submit</button>
        </form>
      </div>

      <div class="col-lg-6">
        <div class="card-soft p-4">
          <h5 class="fw-semibold mb-2"><i class='bx bx-support me-2'></i> 24/7 Support</h5>
          <p class="muted mb-0">Need help with a shipment? Visit our <a href="branches.php">Branches</a> page or reach us from your dashboard.</p>
          <hr class="my-4" />
          <h5 class="fw-semibold mb-2"><i class='bx bx-lock-alt me-2'></i> Secure & Transparent</h5>
          <p class="muted mb-0">We protect your data and keep you informed at every step.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- FOOTER -->
<footer>
  <div class="container">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
      <p class="mb-0">&copy; 2025 Ummi's tracking. All Rights Reserved. | Delivering Beyond Borders</p>
      <div class="d-flex gap-3">
        <a href="index.php">Home</a>
        <a href="tracking.php">Tracking</a>
        <a href="branches.php">Branches</a>
      </div>
    </div>
  </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  document.querySelector('#logoutLink')?.addEventListener('click', function(e) {
    e.preventDefault();
    swal({
      title: "Logout",
      text: "Are you sure you want to logout?",
      icon: "warning",
      buttons: true,
      dangerMode: true,
    }).then((willLogout) => {
      if (willLogout) window.location.href = 'logout.php';
    });
  });
</script>
</body>
</html>
