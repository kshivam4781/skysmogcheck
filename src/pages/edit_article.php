<?php
session_start();
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if article ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_news.php");
    exit();
}

$article_id = $_GET['id'];

// Get article details
$stmt = $conn->prepare("SELECT * FROM news_articles WHERE id = ?");
$stmt->bind_param("i", $article_id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();

if (!$article) {
    header("Location: manage_news.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $image_url = $_POST['image_url'];
    $publish_date = $_POST['publish_date'];
    $author = $_POST['author'];
    $status = $_POST['status'];
    
    // Generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // Update article
    $stmt = $conn->prepare("UPDATE news_articles SET title = ?, content = ?, image_url = ?, publish_date = ?, author = ?, status = ?, slug = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("sssssssi", $title, $content, $image_url, $publish_date, $author, $status, $slug, $article_id);
    
    if ($stmt->execute()) {
        header("Location: view_article.php?slug=" . $slug);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Article - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/ty9olnipt398xv19du58z8ofchfkuctsjo8mwcwqry4jmwfy/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
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
        .article-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        }
        .form-select {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px;
            font-size: 1rem;
            transition: all 0.2s ease;
        }
        .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.15);
        }
        .tox-tinymce {
            border-radius: 8px !important;
            border: 1px solid #ddd !important;
        }
        .article-actions {
            margin-top: 40px;
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
            border: none;
            cursor: pointer;
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
        .btn-save {
            background-color: #28a745;
        }
        .btn-save:hover {
            background-color: #218838;
        }
        .preview-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
            .article-container {
                padding: 0 15px;
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
                <div class="article-container">
                    <h1 class="mb-4">Edit Article</h1>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" id="title" name="title" 
                                   value="<?php echo htmlspecialchars($article['title']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="content" class="form-label">Content</label>
                            <textarea class="form-control" id="content" name="content" rows="10"><?php echo htmlspecialchars($article['content']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="image_url" class="form-label">Image URL</label>
                            <input type="url" class="form-control" id="image_url" name="image_url" 
                                   value="<?php echo htmlspecialchars($article['image_url']); ?>" required>
                            <img src="<?php echo htmlspecialchars($article['image_url']); ?>" 
                                 alt="Preview" class="preview-image" id="imagePreview">
                        </div>

                        <div class="form-group">
                            <label for="publish_date" class="form-label">Publish Date</label>
                            <input type="date" class="form-control" id="publish_date" name="publish_date" 
                                   value="<?php echo date('Y-m-d', strtotime($article['publish_date'])); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="author" class="form-label">Author</label>
                            <input type="text" class="form-control" id="author" name="author" 
                                   value="<?php echo htmlspecialchars($article['author']); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="draft" <?php echo $article['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="published" <?php echo $article['status'] === 'published' ? 'selected' : ''; ?>>Published</option>
                            </select>
                        </div>

                        <div class="article-actions">
                            <a href="view_article.php?slug=<?php echo $article['slug']; ?>" class="btn-action btn-back">
                                <i class="fas fa-arrow-left"></i> Back to Article
                            </a>
                            <button type="submit" class="btn-action btn-save">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
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

            // Image preview
            const imageUrlInput = document.getElementById('image_url');
            const imagePreview = document.getElementById('imagePreview');

            imageUrlInput.addEventListener('input', function() {
                imagePreview.src = this.value;
            });

            // Initialize TinyMCE
            tinymce.init({
                selector: '#content',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | emoticons charmap | removeformat',
                height: 500,
                menubar: false,
                branding: false,
                promotion: false,
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 16px; line-height: 1.6; }',
            });
        });
    </script>
</body>
</html> 