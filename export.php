<?php
// Core functionality for exporting WordPress posts

function extractPosts($config) {
    // Validate and sanitize inputs
    $db_host = filter_var($config['db_host'], FILTER_SANITIZE_STRING);
    $db_name = filter_var($config['db_name'], FILTER_SANITIZE_STRING);
    $db_user = filter_var($config['db_user'], FILTER_SANITIZE_STRING);
    $db_pass = $config['db_pass']; // Password shouldn't be sanitized
    $table_prefix = filter_var($config['table_prefix'], FILTER_SANITIZE_STRING);
    
    // Output directory handling
    $output_dir = isset($config['output_dir']) ? rtrim($config['output_dir'], '/') : '';
    if (empty($output_dir)) {
        $output_dir = __DIR__ . '/exported-posts';
    }
    
    // Create main folder if it doesn't exist
    if (!file_exists($output_dir)) {
        if (!mkdir($output_dir, 0755, true)) {
            showConfigForm("❌ Failed to create output directory: $output_dir", $config);
            return;
        }
    }
    
    // Create subdirectories for posts and pages
    $posts_dir = $output_dir . '/posts';
    $pages_dir = $output_dir . '/pages';
    
    if (!file_exists($posts_dir)) {
        mkdir($posts_dir, 0755, true);
    }
    if (!file_exists($pages_dir)) {
        mkdir($pages_dir, 0755, true);
    }
    
    // Check if directories are writable
    if (!is_writable($output_dir) || !is_writable($posts_dir) || !is_writable($pages_dir)) {
        showConfigForm("❌ Output directory or subdirectories are not writable: $output_dir", $config);
        return;
    }
    
    // Post types to export
    $post_types = ['post'];
    if (isset($config['export_pages']) && $config['export_pages'] === '1') {
        $post_types[] = 'page';
    }
    
    // Export format
    $export_format = isset($config['export_format']) ? $config['export_format'] : 'html';
    
    // Connect to the database and extract posts
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Prepare IN clause for post types
        $placeholders = implode(',', array_fill(0, count($post_types), '?'));
        $query = "
            SELECT ID, post_title, post_content, post_date, post_name, post_type, post_excerpt 
            FROM {$table_prefix}posts 
            WHERE post_type IN ($placeholders) 
            AND post_status = 'publish'
            ORDER BY post_date DESC
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($post_types);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($posts)) {
            showConfigForm("⚠️ No published posts found matching the criteria", $config);
            return;
        }
        
        $export_count = 0;
        $errors = [];
        $exported_files = [];
        
        foreach ($posts as $post) {
            try {
                $filename = generateFilename($post, $export_format);
                
                // Determine the correct directory
                $subdir = ($post['post_type'] === 'page') ? $pages_dir : $posts_dir;
                $filepath = $subdir . '/' . $filename;
                
                if ($export_format === 'html') {
                    $content = generatePostHTML($post);
                } else {
                    $content = generatePlainText($post);
                }
                
                if (file_put_contents($filepath, $content)) {
                    $export_count++;
                    // Store file info for index generation
                    $exported_files[] = [
                        'path' => str_replace($output_dir . '/', '', $filepath),
                        'title' => $post['post_title'],
                        'type' => $post['post_type'],
                        'date' => $post['post_date']
                    ];
                } else {
                    $errors[] = "Failed to write file: $filename";
                }
            } catch (Exception $e) {
                $errors[] = "Error processing post {$post['ID']}: " . $e->getMessage();
            }
        }
        
        // Generate index file if we exported anything
        if ($export_count > 0) {
            generateIndexFile($output_dir, $exported_files);
        }
        
        // Prepare success/error message
        $message = "✅ Successfully exported $export_count posts to $output_dir";
        if (!empty($errors)) {
            $message .= "<br><br>⚠️ Some errors occurred:<br>" . implode("<br>", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "<br>...and " . (count($errors) - 5) . " more";
            }
        }
        
        // Convert local path to web-accessible URL if possible
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
        $output_dir_clean = str_replace('\\', '/', $output_dir);
        
        if (strpos($output_dir_clean, $doc_root) === 0) {
            // Path is under document root - make web-accessible URL
            $web_path = str_replace($doc_root, '', $output_dir_clean);
            $web_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . 
                      $_SERVER['HTTP_HOST'] . $web_path;
            $message .= "<br><br><a href=\"$web_url/index.html\" target=\"_blank\">View Export Index</a>";
        } else {
            // Path is outside document root - show path only
            $message .= "<br><br>Export folder is outside web root: " . htmlspecialchars($output_dir);
        }
        
        // Show result message
        showConfigForm($message, $config);
        
    } catch (PDOException $e) {
        showConfigForm("❌ Database error: " . $e->getMessage(), $config);
    }
}

function generateIndexFile($output_dir, $files) {
    $index_content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exported WordPress Content</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px;
            color: #333;
        }
        h1 { 
            color: #2c3e50;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .section { 
            margin-bottom: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        h2 { 
            color: #3498db;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        ul { 
            list-style: none; 
            padding: 0; 
        }
        li { 
            margin-bottom: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        a { 
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        a:hover { 
            text-decoration: underline;
        }
        .post-date { 
            color: #7f8c8d; 
            font-size: 0.9em; 
            margin-left: 10px;
        }
        .post-count {
            color: #7f8c8d;
            font-size: 0.9em;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <h1>Exported WordPress Content</h1>
HTML;

    // Group by post type
    $grouped = [];
    foreach ($files as $file) {
        $grouped[$file['type']][] = $file;
    }

    foreach ($grouped as $type => $items) {
        $type_name = ($type === 'post') ? 'Posts' : 'Pages';
        $count = count($items);
        $index_content .= "<div class='section'>";
        $index_content .= "<h2>$type_name <span class='post-count'>($count items)</span></h2>";
        $index_content .= "<ul>";
        
        foreach ($items as $item) {
            $date = date('M j, Y', strtotime($item['date']));
            $index_content .= "<li><a href='{$item['path']}'>{$item['title']}</a> <span class='post-date'>$date</span></li>";
        }
        
        $index_content .= "</ul></div>";
    }

    $index_content .= "</body></html>";
    
    file_put_contents($output_dir . '/index.html', $index_content);
}

function showConfigForm($message = null, $previous_values = []) {
    // Default values
    $defaults = [
        'db_host' => 'localhost',
        'db_name' => '',
        'db_user' => '',
        'db_pass' => '',
        'table_prefix' => 'wp_',
        'output_dir' => __DIR__ . '/exported-posts',
        'export_pages' => '0',
        'export_format' => 'html'
    ];
    
    // Merge with previous values if available
    if ($previous_values) {
        $values = array_merge($defaults, $previous_values);
    } else {
        $values = $defaults;
    }
    
    // Load the form template
    require __DIR__ . '/templates/form.php';
}

function generateFilename($post, $format = 'html') {
    $extension = $format === 'html' ? 'html' : 'txt';
    
    // Use post name (slug) if available, otherwise sanitize title
    $name = !empty($post['post_name']) ? $post['post_name'] : $post['post_title'];
    
    // Sanitize filename
    $name = preg_replace('/[^a-z0-9-]/i', '-', $name);
    $name = preg_replace('/-+/', '-', $name);
    $name = trim($name, '-');
    
    // If name is empty after sanitization (unlikely), use 'untitled'
    if (empty($name)) {
        $name = 'untitled';
    }
    
    return $name . '.' . $extension;
}

function generatePostHTML($post) {
    $date = date('F j, Y', strtotime($post['post_date']));
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$post['post_title']}</title>
    <meta name="description" content="{$post['post_excerpt']}">
    <meta name="generator" content="WordPress Post Extractor">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .post-meta {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }
        .post-content {
            line-height: 1.8;
        }
        .post-content img {
            max-width: 100%;
            height: auto;
        }
        .post-content blockquote {
            border-left: 4px solid #3498db;
            padding-left: 15px;
            margin-left: 0;
            color: #555;
            font-style: italic;
        }
        .back-link {
            display: inline-block;
            margin-top: 30px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <article>
        <h1>{$post['post_title']}</h1>
        <div class="post-meta">
            Published on {$date} | Type: {$post['post_type']}
        </div>
        <div class="post-content">
            {$post['post_content']}
        </div>
        <a href="../index.html" class="back-link">← Back to all posts</a>
    </article>
</body>
</html>
HTML;
}

function generatePlainText($post) {
    $date = date('F j, Y', strtotime($post['post_date']));
    $content = strip_tags($post['post_content']);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    return <<<TEXT
Title: {$post['post_title']}
Date: {$date}
Type: {$post['post_type']}
URL: {$post['post_name']}

{$content}

View all posts: ../index.html
TEXT;
}