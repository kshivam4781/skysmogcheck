<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle article deletion via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_POST['article_id'])) {
        $article_id = (int)$_POST['article_id'];
        
        try {
            // Delete the article
            $stmt = $conn->prepare("DELETE FROM news_articles WHERE id = ?");
            $stmt->bind_param("i", $article_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Article deleted successfully';
            } else {
                $response['message'] = 'Error deleting article: ' . $conn->error;
            }
        } catch (Exception $e) {
            $response['message'] = 'Error deleting article: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Article ID is required';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get all news articles
$stmt = $conn->prepare("SELECT * FROM news_articles ORDER BY created_at DESC");
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage News Articles - Sky Smoke Check LLC</title>
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
        .article-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .article-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .article-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .article-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-bottom: 1px solid #eee;
        }
        .article-content {
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .article-title {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #333;
            line-height: 1.4;
            font-weight: 600;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .article-meta {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .article-meta i {
            font-size: 0.8rem;
            color: #888;
        }
        .article-actions {
            display: flex;
            gap: 8px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }
        .btn-action {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }
        .btn-action:hover {
            transform: translateY(-2px);
            color: white;
        }
        .btn-action i {
            font-size: 1rem;
        }
        .btn-view {
            background-color: #28a745;
        }
        .btn-view:hover {
            background-color: #218838;
        }
        .btn-edit {
            background-color: #007bff;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
        .btn-delete {
            background-color: #dc3545;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status-published {
            background-color: #28a745;
            color: white;
        }
        .status-draft {
            background-color: #ffc107;
            color: #000;
        }
        .add-article-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        .add-article-btn:hover {
            transform: scale(1.1);
            background-color: #0056b3;
            color: white;
        }

        /* Terminal Modal Styles */
        .terminal-output {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
            height: 500px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .terminal-output::-webkit-scrollbar {
            width: 8px;
        }
        .terminal-output::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        .terminal-output::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 4px;
        }
        .terminal-output::-webkit-scrollbar-thumb:hover {
            background: #444;
        }
        .text-success {
            color: #28a745 !important;
        }
        .text-warning {
            color: #ffc107 !important;
        }
        .text-error {
            color: #dc3545 !important;
        }
        .text-info {
            color: #17a2b8 !important;
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
                <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                <li>
                    <a href="#" onclick="showComingSoon('All Clients'); return false;">
                        <i class="fas fa-users"></i> All Clients
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('All Appointments'); return false;">
                        <i class="fas fa-calendar-alt"></i> All Appointments
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Clean Truck Checks'); return false;">
                        <i class="fas fa-truck"></i> Clean Truck Checks
                    </a>
                </li>
                <li>
                    <a href="#" onclick="showComingSoon('Smog Tests'); return false;">
                        <i class="fas fa-smog"></i> Smog Tests
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <?php if (isset($_SESSION['accountType']) && $_SESSION['accountType'] == 4): ?>
                    <li>
                        <a href="manage_users.php">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#" onclick="showComingSoon('Settings'); return false;">
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Manage News</h1>
                    <div class="d-flex gap-3">
                        <a href="add_article.php" class="btn btn-primary" title="Create New Article">
                            <i class="fas fa-plus"></i> New Article
                        </a>
                        <a href="../scripts/news_scraper.php" class="btn btn-success" title="Fetch News from CARB" target="_blank">
                            <i class="fas fa-sync-alt"></i> Fetch CARB News
                        </a>
                    </div>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <div class="article-grid">
                    <?php foreach ($articles as $article): ?>
                        <div class="article-card">
                            <div style="position: relative;">
                                <img src="<?php echo htmlspecialchars($article['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($article['title']); ?>" 
                                     class="article-image">
                                <span class="status-badge status-<?php echo $article['status']; ?>">
                                    <?php echo ucfirst($article['status']); ?>
                                </span>
                            </div>
                            <div class="article-content">
                                <h3 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h3>
                                <div class="article-meta">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('F j, Y', strtotime($article['publish_date'])); ?>
                                </div>
                                <div class="article-actions">
                                    <a href="view_article.php?slug=<?php echo $article['slug']; ?>" 
                                       class="btn-action btn-view" title="View Article">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit_article.php?id=<?php echo $article['id']; ?>" 
                                       class="btn-action btn-edit" title="Edit Article">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this article?');">
                                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                                        <button type="submit" name="delete_article" class="btn-action btn-delete w-100" title="Delete Article">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <a href="add_article.php" class="add-article-btn" title="Add New Article">
                    <i class="fas fa-plus"></i>
                </a>
            </div>
        </main>
    </div>

    <!-- Coming Soon Modal -->
    <div class="modal fade" id="comingSoonModal" tabindex="-1" aria-labelledby="comingSoonModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="comingSoonModalLabel">Coming Soon</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-tools fa-3x mb-3 text-primary"></i>
                    <h4 id="featureName"></h4>
                    <p class="mb-0">This feature is currently under development. Please check back later.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/main.js"></script>
    <script>
        // Function to show coming soon message
        function showComingSoon(featureName) {
            const modal = new bootstrap.Modal(document.getElementById('comingSoonModal'));
            document.getElementById('featureName').textContent = featureName;
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.querySelector('.sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const mainContent = document.querySelector('.main-content');
            
            // Initialize Bootstrap components
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const messageToast = new bootstrap.Toast(document.getElementById('messageToast'));
            
            let articleToDelete = null;
            
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                sidebarToggle.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });

            // Handle delete button clicks
            document.querySelectorAll('.btn-delete').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    articleToDelete = this.closest('.article-card');
                    deleteModal.show();
                });
            });

            // Handle delete confirmation
            document.getElementById('confirmDelete').addEventListener('click', function() {
                if (articleToDelete) {
                    const articleId = articleToDelete.querySelector('input[name="article_id"]').value;
                    
                    // Send delete request
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete&article_id=${articleId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        deleteModal.hide();
                        
                        // Show message
                        const toastTitle = document.getElementById('toastTitle');
                        const toastMessage = document.getElementById('toastMessage');
                        
                        if (data.success) {
                            toastTitle.textContent = 'Success';
                            toastMessage.textContent = data.message;
                            toastTitle.className = 'me-auto text-success';
                            
                            // Remove the article card from the DOM
                            articleToDelete.remove();
                        } else {
                            toastTitle.textContent = 'Error';
                            toastMessage.textContent = data.message;
                            toastTitle.className = 'me-auto text-danger';
                        }
                        
                        messageToast.show();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        deleteModal.hide();
                        
                        // Show error message
                        const toastTitle = document.getElementById('toastTitle');
                        const toastMessage = document.getElementById('toastMessage');
                        toastTitle.textContent = 'Error';
                        toastMessage.textContent = 'An error occurred while deleting the article.';
                        toastTitle.className = 'me-auto text-danger';
                        messageToast.show();
                    });
                }
            });
        });
    </script>
</body>
</html> 