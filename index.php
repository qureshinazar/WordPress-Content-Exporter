<?php
// Main entry point that handles form submission and routing
require_once __DIR__ . '/export.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process the extraction
    extractPosts($_POST);
} else {
    // Show the configuration form
    showConfigForm();
}