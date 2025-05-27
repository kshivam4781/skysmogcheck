<?php
// Image URLs (using placeholder images for testing)
$images = [
    'regulations.jpg' => 'https://images.unsplash.com/photo-1562811950-41d4a4944a4b',
    'new-location.jpg' => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa',
    'technology.jpg' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b',
    'conference.jpg' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87',
    'maintenance.jpg' => 'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2',
    'success-story.jpg' => 'https://images.unsplash.com/photo-1566576721346-d4a3b4eaeb55'
];

// Create directory if it doesn't exist
$dir = '../assets/images/news';
if (!file_exists($dir)) {
    mkdir($dir, 0777, true);
}

// Download and save each image
foreach ($images as $filename => $url) {
    $filepath = $dir . '/' . $filename;
    
    // Download image
    $image_data = file_get_contents($url);
    
    if ($image_data !== false) {
        // Save image
        if (file_put_contents($filepath, $image_data)) {
            echo "Downloaded: $filename\n";
        } else {
            echo "Failed to save: $filename\n";
        }
    } else {
        echo "Failed to download: $filename\n";
    }
}

echo "Image download process completed.\n";
?> 