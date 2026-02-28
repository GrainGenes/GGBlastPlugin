<?php
/**
 * BLAST Job Submission API
 * 
 * Accepts HTTP POST requests to create BLAST jobs
 * 
 * Required Parameters:
 *   - blastexe: The BLAST executable/type (e.g., 'blastn', 'blastp', 'blastx')
 *   - query: DNA/protein sequence to search
 * 
 * Returns:
 *   JSON object with jobId and status
 */

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed. Only POST requests are accepted.'
    ]);
    exit;
}

// Get POST parameters
$blastexe = isset($_POST['blastexe']) ? trim($_POST['blastexe']) : '';
$query = isset($_POST['query']) ? trim($_POST['query']) : '';

// Validate required parameters
if (empty($blastexe)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: blastexe'
    ]);
    exit;
}

if (empty($query)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing required parameter: query'
    ]);
    exit;
}

// Validate blastexe value (security check)
$valid_blast_executables = ['blastn', 'blastp', 'blastx', 'tblastn', 'tblastx'];
if (!in_array($blastexe, $valid_blast_executables)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid blastexe. Must be one of: ' . implode(', ', $valid_blast_executables)
    ]);
    exit;
}

// Optional parameters
$database = isset($_POST['database']) ? trim($_POST['database']) : null;
$evalue = isset($_POST['evalue']) ? trim($_POST['evalue']) : '1e-5';
$maxHits = isset($_POST['maxHits']) ? intval($_POST['maxHits']) : 10;

// Generate unique job ID
$jobId = generateJobId();

// Job data
$jobData = [
    'jobId' => $jobId,
    'blastexe' => $blastexe,
    'query' => $query,
    'database' => $database,
    'evalue' => $evalue,
    'maxHits' => $maxHits,
    'status' => 'pending',
    'created' => date('Y-m-d H:i:s'),
    'clientIp' => $_SERVER['REMOTE_ADDR']
];

// Create job directory
$jobsDir = __DIR__ . '/../jobs';
if (!file_exists($jobsDir)) {
    mkdir($jobsDir, 0755, true);
}

$jobDir = $jobsDir . '/' . $jobId;
if (!mkdir($jobDir, 0755, true)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create job directory'
    ]);
    exit;
}

// Save query parameters to query.json
$queryFile = $jobDir . '/query.json';
if (file_put_contents($queryFile, json_encode($jobData, JSON_PRETTY_PRINT)) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to save query parameters'
    ]);
    exit;
}

// Create temporary query sequence file
$querySeqFile = $jobDir . '/query.fasta';
if (file_put_contents($querySeqFile, $query) === false) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to create query sequence file'
    ]);
    exit;
}

// Execute BLAST command in background
$resultsFile = $jobDir . '/results.html';
$errorFile = $jobDir . '/error.log';
$statusFile = $jobDir . '/status.txt';
$pidFile = $jobDir . '/blast.pid';

// Build BLAST command
$cmd = escapeshellcmd($blastexe) . ' -query ' . escapeshellarg($querySeqFile) . ' -html';

// Add optional parameters
if ($database) {
    $cmd .= ' -db ' . escapeshellarg($database);
}
if ($evalue) {
    $cmd .= ' -evalue ' . escapeshellarg($evalue);
}
if ($maxHits) {
    $cmd .= ' -max_target_seqs ' . escapeshellarg($maxHits);
}

// Create a wrapper script to handle completion status
$wrapperScript = $jobDir . '/run_blast.sh';
$wrapperContent = <<<SCRIPT
#!/bin/bash
# BLAST wrapper script

# Run BLAST and capture exit code
$cmd
BLAST_EXIT=\$?

# Update status based on result
if [ \$BLAST_EXIT -eq 0 ] && [ -s "$resultsFile" ]; then
    echo "completed" > "$statusFile"
    # Update query.json with completion status
    php -r "
        \\\$file = '$queryFile';
        \\\$data = json_decode(file_get_contents(\\\$file), true);
        \\\$data['status'] = 'completed';
        \\\$data['completed'] = date('Y-m-d H:i:s');
        file_put_contents(\\\$file, json_encode(\\\$data, JSON_PRETTY_PRINT));
    "
else
    echo "failed" > "$statusFile"
    # Update query.json with failure status
    php -r "
        \\\$file = '$queryFile';
        \\\$data = json_decode(file_get_contents(\\\$file), true);
        \\\$data['status'] = 'failed';
        \\\$data['error'] = 'BLAST execution failed with exit code: \$BLAST_EXIT';
        if (file_exists('$errorFile')) {
            \\\$data['errorDetails'] = file_get_contents('$errorFile');
        }
        file_put_contents(\\\$file, json_encode(\\\$data, JSON_PRETTY_PRINT));
    "
fi

# Clean up PID file
rm -f "$pidFile"
SCRIPT;

file_put_contents($wrapperScript, $wrapperContent);
chmod($wrapperScript, 0755);

// Test if BLAST executable exists and is executable
$testCmd = 'which ' . escapeshellarg($blastexe) . ' 2>&1';
exec($testCmd, $testOutput, $testReturn);

if ($testReturn !== 0) {
    // BLAST executable not found - instant failure
    $jobData['status'] = 'failed';
    $jobData['error'] = 'BLAST executable not found: ' . $blastexe;
    file_put_contents($queryFile, json_encode($jobData, JSON_PRETTY_PRINT));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'jobId' => $jobId,
        'error' => 'BLAST executable not found: ' . $blastexe,
        'message' => 'Job creation failed'
    ]);
    exit;
}

// If database is specified, verify it exists (optional quick check)
if ($database) {
    // Try to get database info - this will fail quickly if database doesn't exist
    $dbCheckCmd = escapeshellcmd($blastexe) . ' -db ' . escapeshellarg($database) . ' -help 2>&1 | head -1';
    exec($dbCheckCmd, $dbCheckOutput, $dbCheckReturn);
    // Note: We don't enforce database existence check as it may slow down submission
}

// Execute wrapper script in background with nohup
$bgCmd = 'nohup ' . escapeshellarg($wrapperScript) . ' > /dev/null 2>&1 & echo $!';
exec($bgCmd, $pidOutput, $bgReturn);

if ($bgReturn !== 0 || empty($pidOutput)) {
    // Failed to start background process - instant failure
    $jobData['status'] = 'failed';
    $jobData['error'] = 'Failed to start BLAST background process';
    file_put_contents($queryFile, json_encode($jobData, JSON_PRETTY_PRINT));
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'jobId' => $jobId,
        'error' => 'Failed to start BLAST background process',
        'message' => 'Job creation failed'
    ]);
    exit;
}

// Save the process ID
$pid = trim($pidOutput[0]);
file_put_contents($pidFile, $pid);

// Update job status to running
$jobData['status'] = 'running';
$jobData['pid'] = $pid;
file_put_contents($queryFile, json_encode($jobData, JSON_PRETTY_PRINT));

// Return success response - job is now running in background
http_response_code(201);
echo json_encode([
    'success' => true,
    'jobId' => $jobId,
    'status' => 'running',
    'message' => 'Job created and running in background',
    'statusCheckUrl' => 'get_job.php?jobId=' . $jobId
]);

/**
 * Generate a unique job ID
 * Format: timestamp-random-hash
 * 
 * @return string
 */
function generateJobId() {
    $timestamp = time();
    $random = bin2hex(random_bytes(8));
    $hash = substr(md5($timestamp . $random . uniqid()), 0, 8);
    return 'job_' . $timestamp . '_' . $hash;
}
