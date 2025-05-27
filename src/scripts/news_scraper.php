<?php
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../includes/news_functions.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set script execution time limit
set_time_limit(300); // 5 minutes

echo "Starting news scraper...\n";

// Function to scrape CARB news
function scrapeCARBNews() {
    echo "Fetching news from CARB website...\n";
    $url = 'https://ww2.arb.ca.gov/news';
    
    // Add error context
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        $error = error_get_last();
        echo "Error: Failed to fetch CARB news page\n";
        echo "Error details: " . print_r($error, true) . "\n";
        return [];
    }

    echo "Successfully fetched CARB news page\n";
    echo "HTML content length: " . strlen($html) . " bytes\n";

    // Create a DOM document
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    
    if (!empty($errors)) {
        echo "HTML parsing errors:\n";
        foreach ($errors as $error) {
            echo "Line {$error->line}: {$error->message}\n";
        }
    }

    $xpath = new DOMXPath($dom);

    $articles = [];
    
    // Find all news articles with more detailed logging
    $newsNodes = $xpath->query("//article[contains(@class, 'node--type-news-release')]");
    
    echo "Found " . $newsNodes->length . " articles\n";
    
    if ($newsNodes->length === 0) {
        echo "No articles found. Checking HTML structure...\n";
        $allArticles = $xpath->query("//article");
        echo "Total article elements found: " . $allArticles->length . "\n";
        for ($i = 0; $i < min(5, $allArticles->length); $i++) {
            $article = $allArticles->item($i);
            echo "Article " . ($i + 1) . " class: " . $article->getAttribute('class') . "\n";
        }
    }
    
    foreach ($newsNodes as $node) {
        try {
            $titleNode = $xpath->query(".//div[contains(@class, 'card__title')]//span", $node)->item(0);
            $dateNode = $xpath->query(".//div[contains(@class, 'card__date')]//time", $node)->item(0);
            $imageNode = $xpath->query(".//div[contains(@class, 'card__img-top')]//img", $node)->item(0);
            $linkNode = $xpath->query(".//div[contains(@class, 'card__title')]//a", $node)->item(0);
            
            if (!$titleNode || !$dateNode || !$linkNode) {
                echo "Missing required elements in article node\n";
                continue;
            }
            
            $title = $titleNode->textContent;
            $date = $dateNode->getAttribute('datetime');
            $imageUrl = $imageNode ? $imageNode->getAttribute('src') : null;
            if ($imageUrl && strpos($imageUrl, 'http') !== 0) {
                $imageUrl = 'https://ww2.arb.ca.gov' . $imageUrl;
            }
            
            // Get the article URL
            $relativeUrl = $linkNode->getAttribute('href');
            $articleUrl = (strpos($relativeUrl, 'http') === 0) ? $relativeUrl : 'https://ww2.arb.ca.gov' . $relativeUrl;
            
            $articles[] = [
                'title' => trim($title),
                'content' => trim($title), // Will be updated with full content later
                'date' => trim($date),
                'image_url' => $imageUrl,
                'url' => $articleUrl
            ];
            
            echo "Successfully processed article: " . trim($title) . "\n";
        } catch (Exception $e) {
            echo "Error processing article node: " . $e->getMessage() . "\n";
        }
    }
    
    return $articles;
}

// Function to paraphrase text using a simple algorithm
function paraphraseText($text) {
    // This is a simple paraphrasing example. In production, you might want to use an API
    // like OpenAI's GPT or other NLP services for better results
    
    // Replace common phrases with synonyms
    $replacements = [
        'announced' => 'revealed',
        'launched' => 'introduced',
        'implemented' => 'enacted',
        'developed' => 'created',
        'improved' => 'enhanced',
        'reduced' => 'decreased',
        'increased' => 'boosted',
        'important' => 'crucial',
        'significant' => 'substantial',
        'new' => 'novel'
    ];
    
    foreach ($replacements as $original => $replacement) {
        $text = str_replace($original, $replacement, $text);
    }
    
    return $text;
}

// Function to download and save image
function downloadAndSaveImage($imageUrl, $title) {
    if (!$imageUrl) {
        echo "No image URL provided for article: $title\n";
        return __DIR__ . '/../assets/images/news/default.jpg';
    }
    
    // Create directory if it doesn't exist
    $dir = __DIR__ . '/../assets/images/news';
    if (!file_exists($dir)) {
        echo "Creating directory: $dir\n";
        mkdir($dir, 0777, true);
    }
    
    // Generate filename from title
    $filename = strtolower(preg_replace('/[^a-z0-9]+/', '-', $title)) . '.jpg';
    $filepath = $dir . '/' . $filename;
    
    echo "Downloading image for article: $title\n";
    // Download image
    $imageData = file_get_contents($imageUrl);
    if ($imageData !== false) {
        file_put_contents($filepath, $imageData);
        echo "Image saved successfully: $filepath\n";
        return __DIR__ . '/../assets/images/news/' . $filename;
    }
    
    echo "Failed to download image for article: $title\n";
    return __DIR__ . '/../assets/images/news/default.jpg';
}

// Function to check if article already exists
function articleExists($title) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM news_articles WHERE title = ?");
    $stmt->bind_param("s", $title);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

// Function to update database schema
function updateDatabaseSchema() {
    global $conn;
    
    // Check if image_url column exists and is TEXT type
    $result = $conn->query("SHOW COLUMNS FROM news_articles LIKE 'image_url'");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if ($row['Type'] !== 'TEXT') {
            // Alter the column to TEXT type
            $conn->query("ALTER TABLE news_articles MODIFY COLUMN image_url TEXT");
            echo "Updated image_url column to TEXT type\n";
        }
    } else {
        // Create the column if it doesn't exist
        $conn->query("ALTER TABLE news_articles ADD COLUMN image_url TEXT");
        echo "Added image_url column\n";
    }
}

// Function to get a random truck image from Unsplash
function getRandomTruckImage() {
    $unsplashImages = [
        'https://images.unsplash.com/photo-1601584115197-04ecc0da31d7',
        'https://images.unsplash.com/photo-1592838064575-70ed626d3a0e',
        'https://images.unsplash.com/photo-1501700493788-fa1a4fc9fe62',
        'https://images.unsplash.com/photo-1562954203-74c7fb884cba',
        'https://images.unsplash.com/photo-1577075473292-5f62dfae5522',
        'https://images.unsplash.com/photo-1618582948377-cd7eb0e8cb14',
        'https://images.unsplash.com/photo-1629881635342-c1272d45d0fa',
        'https://images.unsplash.com/photo-1543858671-c460805db8f7',
        'https://images.unsplash.com/photo-1594010465298-8522f3325429',
        'https://images.unsplash.com/photo-1565891741441-64926e441838'
    ];
    return $unsplashImages[array_rand($unsplashImages)];
}

// Function to extract all image URLs from HTML content
function extractImageUrls($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    
    // Look specifically for the main article image with class 'list'
    $imgNodes = $xpath->query("//img[contains(@src, '/styles/list/')]");
    if ($imgNodes->length > 0) {
        $src = $imgNodes->item(0)->getAttribute('src');
        if ($src && strpos($src, 'http') !== 0) {
            $src = 'https://ww2.arb.ca.gov' . $src;
        }
        return $src;
    }
    return null;
}

// Main execution
try {
    echo "Connecting to database...\n";
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    echo "Database connection successful\n";

    // Update database schema
    updateDatabaseSchema();

    // Scrape CARB news
    $articles = scrapeCARBNews();
    
    echo "Processing " . count($articles) . " articles...\n";
    
    foreach ($articles as $article) {
        echo "\nProcessing article: " . $article['title'] . "\n";
        
        // Skip if article already exists
        if (articleExists($article['title'])) {
            echo "Article already exists, skipping...\n";
            continue;
        }
        
        // Get article content and images
        $articleHtml = @file_get_contents($article['url']);
        $content = $article['title']; // fallback
        $imageUrl = null;
        
        if ($articleHtml !== false) {
            $articleDom = new DOMDocument();
            libxml_use_internal_errors(true);
            @$articleDom->loadHTML($articleHtml);
            libxml_clear_errors();
            $articleXpath = new DOMXPath($articleDom);
            
            // Extract content with HTML formatting
            $contentNode = $articleXpath->query("//section[contains(@class, 'section--no-top-padding')]//div[contains(@class, 'container')]");
            if ($contentNode->length > 0) {
                // Get the inner HTML of the content node
                $content = '';
                foreach ($contentNode->item(0)->childNodes as $node) {
                    $content .= $articleDom->saveHTML($node);
                }
                // Clean up the content
                $content = preg_replace('/\s+/', ' ', $content); // Remove extra whitespace
                $content = trim($content);
            }
            
            // Extract main article image
            $imageUrl = extractImageUrls($articleHtml);
            echo "Found main article image: " . ($imageUrl ? "Yes" : "No") . "\n";
        } else {
            echo "Failed to fetch article page: " . $article['url'] . "\n";
        }
        
        // If no image found in article, use a random truck image from Unsplash
        if (!$imageUrl) {
            $imageUrl = getRandomTruckImage();
            echo "Using fallback Unsplash image\n";
        }
        
        // Generate slug
        $slug = generateSlug($article['title']);
        
        // Create article in database with image URL
        echo "Creating article in database...\n";
        $success = createNewsArticle(
            $article['title'],
            $content,
            $imageUrl, // Store single image URL as string
            date('Y-m-d', strtotime($article['date'])),
            'CARB News',
            $slug
        );
        
        if ($success) {
            echo "Successfully added article: " . $article['title'] . "\n";
            echo "Image URL: " . $imageUrl . "\n";
        } else {
            echo "Failed to add article: " . $article['title'] . "\n";
        }
    }
    
    echo "\nNews scraping completed successfully\n";
} catch (Exception $e) {
    echo "Error in news scraper: " . $e->getMessage() . "\n";
}
?> 