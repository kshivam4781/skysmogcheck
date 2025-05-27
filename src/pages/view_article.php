<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if article slug is provided
if (!isset($_GET['slug'])) {
    header("Location: manage_news.php");
    exit();
}

$slug = $_GET['slug'];

// Get article details
$stmt = $conn->prepare("SELECT * FROM news_articles WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();

if (!$article) {
    header("Location: manage_news.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            margin-bottom: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            width: 0;
            padding: 20px 0;
        }
        .sidebar-toggle {
            position: fixed;
            left: 250px;
            top: 20px;
            background: #343a40;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1001;
        }
        .sidebar-toggle.collapsed {
            left: 0;
        }
        .sidebar-toggle i {
            transition: transform 0.3s;
        }
        .sidebar-toggle.collapsed i {
            transform: rotate(180deg);
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .sidebar-header {
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            margin-bottom: 5px;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar-menu a.active {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        .sidebar-menu a.active i {
            color: white;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.collapsed {
                transform: translateX(0);
                width: 250px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                left: 0;
            }
        }
        .article-header {
            position: relative;
            margin-bottom: 40px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .article-header-image {
            width: 100%;
            height: 500px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .article-header:hover .article-header-image {
            transform: scale(1.02);
        }
        .article-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .article-title {
            font-size: 2.8rem;
            font-weight: 800;
            margin: 30px 0;
            color: #1a1a1a;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        .article-meta {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 40px;
            color: #666;
            font-size: 1rem;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .article-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .article-meta i {
            color: #007bff;
            font-size: 1.1rem;
        }
        .article-content {
            font-size: 1.15rem;
            line-height: 1.9;
            color: #333;
            margin-bottom: 40px;
        }
        .article-content p {
            margin-bottom: 1.5em;
        }
        .article-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 2em 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .article-content h2, 
        .article-content h3 {
            margin: 1.5em 0 0.8em;
            color: #1a1a1a;
            font-weight: 700;
        }
        .article-content h2 {
            font-size: 1.8rem;
        }
        .article-content h3 {
            font-size: 1.5rem;
        }
        .article-content ul, 
        .article-content ol {
            margin: 1em 0;
            padding-left: 1.5em;
        }
        .article-content li {
            margin-bottom: 0.5em;
        }
        .article-content blockquote {
            border-left: 4px solid #007bff;
            padding: 1em 1.5em;
            margin: 1.5em 0;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
            font-style: italic;
        }
        .article-actions {
            margin-top: 50px;
            padding-top: 30px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 15px;
            justify-content: flex-start;
        }
        .btn-action {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-back {
            background-color: #6c757d;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }
        .btn-edit {
            background-color: #007bff;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .status-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            backdrop-filter: blur(4px);
            background-color: rgba(255, 255, 255, 0.9);
        }
        .status-published {
            color: #28a745;
        }
        .status-draft {
            color: #ffc107;
        }
        @media (max-width: 768px) {
            .article-title {
                font-size: 2rem;
            }
            .article-header-image {
                height: 300px;
            }
            .article-meta {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            .article-content {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Sky Smoke Check</h3>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="welcome.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="manage_news.php" class="active">
                        <i class="fas fa-newspaper"></i> Manage News
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                    <li>
                        <a href="manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <main>
            <div class="container-fluid">
                <div class="article-header">
                    <img src="<?php echo htmlspecialchars($article['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($article['title']); ?>" 
                         class="article-header-image">
                    <span class="status-badge status-<?php echo $article['status']; ?>">
                        <?php echo ucfirst($article['status']); ?>
                    </span>
                </div>

                <div class="article-container">
                    <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>

                    <div class="article-meta">
                        <span>
                            <i class="fas fa-user"></i>
                            <?php echo htmlspecialchars($article['author']); ?>
                        </span>
                        <span>
                            <i class="far fa-calendar-alt"></i>
                            <?php echo date('F j, Y', strtotime($article['publish_date'])); ?>
                        </span>
                    </div>

                    <div class="article-content">
                        <?php echo $article['content']; ?>
                    </div>

                    <div class="article-actions">
                        <a href="manage_news.php" class="btn-action btn-back">
                            <i class="fas fa-arrow-left"></i> Back to News
                        </a>
                        <a href="edit_article.php?id=<?php echo $article['id']; ?>" class="btn-action btn-edit">
                            <i class="fas fa-edit"></i> Edit Article
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const mainContent = document.querySelector('.main-content');
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
        });
    </script>
</body>
</html> 