<?php
session_start();
require_once "db_connect.php";

// Fetch branches (newest first based on insertion order)
$branches = [];
$sql = "SELECT b.*, s.Name as manager_name FROM branches b 
        LEFT JOIN staff s ON b.Manager_id = s.StaffID 
        ORDER BY b.Manager_id DESC";
$result = mysqli_query($conn, $sql);
if ($result) {
    $branches = mysqli_fetch_all($result, MYSQLI_ASSOC);
}

// Group branches by country/region for better organization
$nigerian_branches = [];
$international_branches = [];

foreach ($branches as $branch) {
    if (in_array($branch['state'], ['Bauchi', 'Lagos', 'FCT', 'Anambra', 'Oyo', 'Kaduna', 'Kano', 'Rivers', 'Delta', 'Abia', 'Borno'])) {
        $nigerian_branches[] = $branch;
    } else {
        $international_branches[] = $branch;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Ummi's tracking — Our Branches</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Logo Styles -->
    <link href="style/logo.css" rel="stylesheet">

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

        /* Hero Section */
        .hero {
            position: relative;
            background: linear-gradient(135deg, var(--brand) 0%, #0B4B86 40%, #0D6EFD 100%);
            color: #fff;
            overflow: hidden;
            padding: 80px 0 60px;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        /* Sections */
        .section-pad { padding: 64px 0; }
        
        .card-branch {
            border: none;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(2,12,27,0.06);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .card-branch:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(2,12,27,0.12);
        }

        .branch-header {
            background: linear-gradient(135deg, var(--brand-2) 0%, var(--brand) 100%);
            color: white;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            position: relative;
            overflow: hidden;
        }

        .branch-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .branch-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 12px;
        }

        .branch-info {
            padding: 24px;
        }

        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 8px 0;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            background: var(--brand-3);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--brand-2);
            font-size: 18px;
        }

        .nigeria-badge {
            background: linear-gradient(135deg, #00B04F 0%, #228B22 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 8px;
        }

        .international-badge {
            background: linear-gradient(135deg, var(--highlight) 0%, #E6B800 100%);
            color: #16213E;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 8px;
        }

        /* Footer */
        footer {
            background: var(--brand);
            color: #fff;
            padding: 28px 0;
        }
        
        footer a { 
            color: #E5E7EB; 
            text-decoration: none; 
        }
        
        footer a:hover { 
            color: #fff; 
            text-decoration: underline; 
        }

        .section-title {
            position: relative;
            margin-bottom: 48px;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--highlight);
            border-radius: 2px;
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
                <li class="nav-item"><a class="nav-link active" href="branches.php">Branches</a></li>
                <?php if (isset($_SESSION['id']) || isset($_SESSION['user_id'])): ?>
                    <?php if (isset($_SESSION['id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="staff.php">Dashboard</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="user_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-2">
                        <a class="nav-link text-danger" href="logout.php"><i class='bx bx-log-out-circle me-1'></i> Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3">
                        <a class="btn btn-sm btn-outline-primary px-3" href="login.php">Ummi's tracking Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- HERO -->
<header class="hero">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h1 class="display-4 fw-bold mb-3">Our Branch Network</h1>
                <p class="lead opacity-90">
                    Discover our extensive network of branches across Nigeria and internationally. 
                    Each location is staffed with experienced professionals ready to serve your logistics needs.
                </p>
                <div class="d-flex gap-4 mt-4">
                    <div class="text-center">
                        <div class="h2 fw-bold"><?php echo count($nigerian_branches); ?></div>
                        <div class="small opacity-75">Nigerian Branches</div>
                    </div>
                    <div class="text-center">
                        <div class="h2 fw-bold"><?php echo count($international_branches); ?></div>
                        <div class="small opacity-75">International Branches</div>
                    </div>
                    <div class="text-center">
                        <div class="h2 fw-bold"><?php echo count($branches); ?></div>
                        <div class="small opacity-75">Total Locations</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="text-center">
                    <i class='bx bx-world' style="font-size: 120px; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- NIGERIAN BRANCHES -->
<?php if (!empty($nigerian_branches)): ?>
<section class="section-pad">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-8">
                <h2 class="fw-bold section-title">Nigerian Branches</h2>
                <p class="text-muted">Our local presence across major Nigerian cities, with special focus on Bauchi state operations.</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($nigerian_branches as $branch): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card-branch">
                    <div class="branch-header">
                        <div class="nigeria-badge">🇳🇬 Nigeria</div>
                        <div class="branch-icon">
                            <i class='bx bx-buildings'></i>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($branch['city'] ?? 'Branch'); ?></h5>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($branch['state'] ?? ''); ?></p>
                    </div>
                    <div class="branch-info">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-map'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Address</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Address'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-phone'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Contact</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Contact'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-envelope'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Email</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Email'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($branch['manager_name'])): ?>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-user-circle'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Manager</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['manager_name']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- INTERNATIONAL BRANCHES -->
<?php if (!empty($international_branches)): ?>
<section class="section-pad bg-light">
    <div class="container">
        <div class="row mb-5">
            <div class="col-lg-8">
                <h2 class="fw-bold section-title">International Branches</h2>
                <p class="text-muted">Our global network ensuring seamless international logistics and delivery services.</p>
            </div>
        </div>
        <div class="row g-4">
            <?php foreach ($international_branches as $branch): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card-branch">
                    <div class="branch-header">
                        <div class="international-badge">🌍 International</div>
                        <div class="branch-icon">
                            <i class='bx bx-world'></i>
                        </div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($branch['city'] ?? 'Branch'); ?></h5>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($branch['state'] ?? ''); ?></p>
                    </div>
                    <div class="branch-info">
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-map'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Address</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Address'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-phone'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Contact</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Contact'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-envelope'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Email</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['Email'] ?? '—'); ?></div>
                            </div>
                        </div>
                        <?php if (!empty($branch['manager_name'])): ?>
                        <div class="info-item">
                            <div class="info-icon">
                                <i class='bx bx-user-circle'></i>
                            </div>
                            <div>
                                <div class="fw-semibold">Manager</div>
                                <div class="text-muted small"><?php echo htmlspecialchars($branch['manager_name']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

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
</body>
</html>