<?php
/**
 * BLAST Results Viewer
 * 
 * Serves the results.html file for a completed job
 * 
 * Required Parameters:
 *   - jobId: The job identifier
 */

// Load configuration
$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo '<h3>Error: Configuration file not found</h3>';
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    http_response_code(500);
    echo '<h3>Error: Failed to parse config.json</h3>';
    exit;
}

$jobsPath = isset($config['jobsPath']) ? rtrim($config['jobsPath'], '/') : __DIR__ . '/../jobs';

// Get jobId parameter
$jobId = isset($_GET['jobId']) ? trim($_GET['jobId']) : '';

// Validate jobId parameter
if (empty($jobId)) {
    http_response_code(400);
    echo '<h3>Error: Missing required parameter: jobId</h3>';
    exit;
}

// Sanitize jobId to prevent directory traversal
if (!preg_match('/^job_[0-9]+_[a-f0-9]{8}$/', $jobId)) {
    http_response_code(400);
    echo '<h3>Error: Invalid jobId format</h3>';
    exit;
}

// Construct path to results file
$jobDir = $jobsPath . '/' . $jobId;
$resultsFile = $jobDir . '/results.html';

// Check if results file exists
if (!file_exists($resultsFile)) {
    http_response_code(404);
    echo '<h3>Error: Results file not found</h3>';
    echo '<p>The job may still be running or may have failed.</p>';
    exit;
}

// Serve the results file
header('Content-Type: text/html; charset=utf-8');
readfile($resultsFile);
?>
