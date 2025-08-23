<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startup Pitch Hub - Connect Ideas with Funding</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <nav class="navbar">
            <div class="nav-container">
                <div class="nav-logo">
                    <a href="index.php" class="logo">
                        <i class="fas fa-rocket"></i>
                        Smart Pitch Hub
                    </a>
                </div>
                <div class="nav-menu">
                    <a href="index.php" class="nav-link">Home</a>
                    <a href="#features" class="nav-link">Features</a>
                    <a href="#how-it-works" class="nav-link">How It Works</a>
                    <a href="#contact" class="nav-link">Contact</a>
                    
                    <?php if (isset($_SESSION['user_role'])): ?>
                        <?php if ($_SESSION['user_role'] === 'investor'): ?>
                            <a href="dashboard-investor.php" class="nav-link btn-primary">Investor Dashboard</a>
                        <?php elseif ($_SESSION['user_role'] === 'entrepreneur'): ?>
                            <a href="dashboard-entrepreneur.php" class="nav-link btn-primary">Entrepreneur Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="nav-link btn-secondary">Logout</a>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php?role=investor" class="nav-link btn-primary">Investor Login</a>
                            <a href="login.php?role=entrepreneur" class="nav-link btn-secondary">Entrepreneur Login</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hamburger">
                    <span class="bar"></span>
                    <span class="bar"></span>
                    <span class="bar"></span>
                </div>
            </div>
        </nav>
    </header>
    <main>
