<?php
/**
 * BLAST Job Status API
 * 
 * Retrieves job information by jobId
 * 
 * Required Parameters:
 *   - jobId: The job identifier returned from submit_job.php
 * 
 * Returns:
 *   JSON object with job data and status
 */

header('Content-Type: application/json');

// Load configuration
$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Configuration file not found: config.json'
    ]);
    exit;
}

$config = json_decode(file_get_contents($configFile), true);
if (!$config) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to parse config.json'
    ]);
    exit;
}

$jobsPath = isset($config['jobsPath']) ? rtrim($config['jobsPath'], '/') : __DIR__ . '/../jobs';

// Accept both GET and POST requests
$jobId = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jobId = isset($_GET['jobId']) ? trim($_GET['jobId']) : '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobId = isset($_POST['jobId']) ? trim($_POST['jobId']) : '';
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed. Only GET and POST requests are accepted.'
    ]);
    exit;
}

// Validate jobId parameter
if (empty($jobId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: jobId'
    ]);
    exit;
}

// Sanitize jobId to prevent directory traversal
if (!preg_match('/^job_[0-9]+_[a-f0-9]{8}$/', $jobId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid jobId format'
    ]);
    exit;
}

// Create jobs directory if it doesn't exist
if (!file_exists($jobsPath)) {
    mkdir($jobsPath, 0755, true);
}

$jobDir = $jobsPath . '/' . $jobId;
$jobFile = $jobDir . '/query.json';

if (!file_exists($jobDir) || !is_dir($jobDir)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Job not found'
    ]);
    exit;
}

if (!file_exists($jobFile)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Job data file not found'
    ]);
    exit;
}

$jobData = json_decode(file_get_contents($jobFile), true);

if ($jobData === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to read job data'
    ]);
    exit;
}

// Check if job is still running by looking at status file and process
$statusFile = $jobDir . '/status.txt';
$pidFile = $jobDir . '/blast.pid';

// If status file exists, read the actual status
if (file_exists($statusFile)) {
    $actualStatus = trim(file_get_contents($statusFile));
    if ($actualStatus === 'completed' || $actualStatus === 'failed') {
        // Re-read the query.json as it may have been updated by the wrapper script
        $jobData = json_decode(file_get_contents($jobFile), true);
    }
}

// If job status is still "running", check if process is actually alive
if ($jobData['status'] === 'running' && file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    if ($pid) {
        // Check if process is still running
        exec('ps -p ' . escapeshellarg($pid) . ' > /dev/null 2>&1', $psOutput, $psReturn);
        if ($psReturn !== 0) {
            // Process is not running, but status wasn't updated - check for results
            if (file_exists($jobDir . '/results.html') && filesize($jobDir . '/results.html') > 0) {
                $jobData['status'] = 'completed';
            } else {
                $jobData['status'] = 'failed';
                $jobData['error'] = 'Process terminated unexpectedly';
            }
            // Update the query.json file
            file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
        }
    }
}

// Return job data (excluding sensitive information like IP address)
unset($jobData['clientIp']);
unset($jobData['pid']);

// Add results URL if results exist
$resultsFile = $jobDir . '/results.html';
if (file_exists($resultsFile)) {
    $jobData['resultsUrl'] = 'jobs/' . $jobId . '/results.html';
    $jobData['resultsSize'] = filesize($resultsFile);
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'job' => $jobData
]);
