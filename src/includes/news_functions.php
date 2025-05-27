<?php
require_once __DIR__ . '/../config/db_connection.php';

function getAllNewsArticles($limit = 10, $offset = 0) {
    global $conn;
    $sql = "SELECT * FROM news_articles WHERE status = 'published' ORDER BY publish_date DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getNewsArticleBySlug($slug) {
    global $conn;
    $sql = "SELECT * FROM news_articles WHERE slug = ? AND status = 'published'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getTotalNewsArticles() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM news_articles WHERE status = 'published'";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'];
}

function createNewsArticle($title, $content, $image_url, $publish_date, $author, $slug) {
    global $conn;
    $sql = "INSERT INTO news_articles (title, content, image_url, publish_date, author, slug) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssss", $title, $content, $image_url, $publish_date, $author, $slug);
    return $stmt->execute();
}

function updateNewsArticle($id, $title, $content, $image_url, $publish_date, $author, $slug, $status) {
    global $conn;
    $sql = "UPDATE news_articles SET title = ?, content = ?, image_url = ?, publish_date = ?, author = ?, slug = ?, status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssi", $title, $content, $image_url, $publish_date, $author, $slug, $status, $id);
    return $stmt->execute();
}

function deleteNewsArticle($id) {
    global $conn;
    $sql = "DELETE FROM news_articles WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function generateSlug($title) {
    // Convert the title to lowercase
    $slug = strtolower($title);
    
    // Replace non-alphanumeric characters with a dash
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    
    // Remove multiple consecutive dashes
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Remove leading and trailing dashes
    $slug = trim($slug, '-');
    
    return $slug;
}
?> 