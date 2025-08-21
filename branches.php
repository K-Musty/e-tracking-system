<?php
session_start();
require_once "db_connect.php";

// Fetch branches
$branches = [];
$sql = "SELECT * FROM branches";
$result = mysqli_query($conn, $sql);
if ($result) {
    $branches = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>DropEx Branches</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="style/bootstrap.css">
    <link href='https://fonts.googleapis.com/css?family=Roboto' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        /* === NAVBAR STYLING === */
        .navbar {
            background-color: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(8px); /* subtle glass effect */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .navbar .navbar-brand img {
            height: 50px !important;
            margin-top: 5px;
        }

        .navbar-nav .nav-link {
            color: #222 !important; /* dark text */
            font-weight: 500;
            margin-right: 20px;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .navbar-nav .nav-link:hover {
            color: #007bff !important; /* Bootstrap blue highlight */
            transform: translateY(-1px);
        }

        .navbar-nav .nav-link.active {
            color: #007bff !important;
            font-weight: 600;
            border-bottom: 2px solid #007bff;
        }

        /* Logout button can stand out */
        .btn-logout {
            color: #fff !important;
            background-color: #dc3545 !important; /* red */
            padding: 5px 12px;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        .btn-logout:hover {
            background-color: #b02a37 !important;
        }

        .card {
            background-color: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .card-body ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        .card-body li {
            margin-bottom: 8px;
            font-size: 15px;
        }
        .fa {
            color: #0056b3;
            width: 20px;
        }
        footer {
            text-align: center;
            padding: 20px;
            background: #0056b3;
            color: #fff;
            margin-top: 20px;
        }
        footer p { margin: 0; font-size: 0.9em; }
    </style>
</head>
<body style="font-family: Arial, Helvetica, sans-serif;">

<nav class="navbar navbar-toggleable-md navbar-expand-lg navbar-default navbar-light mb-10" 
     style="background-color: rgba(255, 255, 255, 0.8); margin-bottom: 20px; margin-top:10px !important;">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="Images/logo.png" id="logo" style="height: 50px !important; margin-top: 10px !important;">
        </a>
        <button class="navbar-toggler text-dark" data-toggle="collapse" data-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="navbar-nav ml-auto" style="font-size: large;">
                <a class="nav-item nav-link text-dark mr-5 <?php echo $activePage==='home'?'active':''; ?>" href="index.php">Home</a>
                <a class="nav-item nav-link text-dark mr-5 <?php echo $activePage==='tracking'?'active':''; ?>" href="tracking.php">Tracking</a>
                <a class="nav-item nav-link text-dark mr-5 <?php echo $activePage==='branches'?'active':''; ?>" href="branches.php">Branches</a>
                <a class="nav-item nav-link text-dark mr-5" href="index.php#about">About</a>
                
                <?php if(isset($_SESSION['id'])): ?>
                    <a class="nav-item nav-link text-dark mr-3" href="staff.php">Dashboard</a>
                    <a class="nav-item nav-link btn-logout" href="logout.php">Logout</a>
                <?php else: ?>
                    <a class="nav-item nav-link text-dark" href="login.php">DropEx Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="container">
    <?php if (empty($branches)): ?>
        <div class="alert alert-info">No branches found at the moment.</div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($branches as $branch): ?>
            <div class="col-md-4 col-sm-6 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <?php echo htmlspecialchars($branch['Name'] ?? 'Branch'); ?>
                        </h5>
                        <ul>
                            <li><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($branch['Address'] ?? '—'); ?></li>
                            <li><i class="fa fa-phone"></i> <?php echo htmlspecialchars($branch['Contact'] ?? '—'); ?></li>
                            <li><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($branch['Email'] ?? '—'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<footer>
    <p>&copy; 2025 DropEx. All Rights Reserved. | Delivering Beyond Borders</p>
</footer>
</body>
</html>
