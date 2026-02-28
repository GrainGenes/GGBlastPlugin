<?php
/**
 * BLAST API Index
 * 
 * Provides information about the API and lists recent jobs
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLAST Job Submission API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-left: 4px solid #4CAF50;
        }
        .endpoint {
            background-color: #fff;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .method {
            display: inline-block;
            padding: 3px 8px;
            background-color: #4CAF50;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 10px;
        }
        .method.get {
            background-color: #2196F3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        a {
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .status-completed {
            color: #4CAF50;
            font-weight: bold;
        }
        .status-failed {
            color: #f44336;
            font-weight: bold;
        }
        .status-running {
            color: #FF9800;
            font-weight: bold;
        }
        .status-pending {
            color: #FF9800;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🧬 BLAST Job Submission API</h1>
    
    <div class="section">
        <h2>API Endpoints</h2>
        
        <div class="endpoint">
            <span class="method">POST</span>
            <strong>submit_job.php</strong>
            <p>Submit a new BLAST job with a DNA/protein sequence query. Jobs execute asynchronously in the background.</p>
        </div>
        
        <div class="endpoint">
            <span class="method get">GET</span>
            <strong>get_job.php</strong>
            <p>Retrieve job status and information by jobId. Poll this endpoint to check if a running job has completed.</p>
        </div>
        
        <p><a href="README.md">📖 View Full API Documentation</a></p>
        <p><a href="test.html">🧪 Test the API</a></p>
    </div>

    <div class="section">
        <h2>Recent Jobs</h2>
        <?php
        $jobsDir = __DIR__ . '/../jobs';
        $jobs = [];
        
        if (is_dir($jobsDir)) {
            $dirs = scandir($jobsDir, SCANDIR_SORT_DESCENDING);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..' || !is_dir($jobsDir . '/' . $dir)) {
                    continue;
                }
                
                $queryFile = $jobsDir . '/' . $dir . '/query.json';
                if (file_exists($queryFile)) {
                    $jobData = json_decode(file_get_contents($queryFile), true);
                    if ($jobData) {
                        $jobs[] = $jobData;
                    }
                }
                
                // Limit to 20 most recent jobs
                if (count($jobs) >= 20) {
                    break;
                }
            }
        }
        
        if (empty($jobs)) {
            echo '<p>No jobs found.</p>';
        } else {
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>Job ID</th>';
            echo '<th>BLAST Type</th>';
            echo '<th>Database</th>';
            echo '<th>Status</th>';
            echo '<th>Created</th>';
            echo '<th>Actions</th>';
            echo '</tr></thead>';
            echo '<tbody>';
            
            foreach ($jobs as $job) {
                $statusClass = 'status-' . strtolower($job['status']);
                $resultsFile = $jobsDir . '/' . $job['jobId'] . '/results.html';
                $hasResults = file_exists($resultsFile);
                
                echo '<tr>';
                echo '<td><code>' . htmlspecialchars($job['jobId']) . '</code></td>';
                echo '<td>' . htmlspecialchars($job['blastexe']) . '</td>';
                echo '<td>' . htmlspecialchars($job['database'] ?? 'N/A') . '</td>';
                echo '<td class="' . $statusClass . '">' . htmlspecialchars($job['status']) . '</td>';
                echo '<td>' . htmlspecialchars($job['created']) . '</td>';
                echo '<td>';
                if ($hasResults) {
                    echo '<a href="../jobs/' . htmlspecialchars($job['jobId']) . '/results.html" target="_blank">View Results</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        ?>
    </div>

    <div class="section">
        <h2>Quick Start</h2>
        <h3>Submit a BLAST Job</h3>
        <pre style="background-color: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
curl -X POST submit_job.php \
  -d "blastexe=blastn" \
  -d "query=ATCGATCGATCGATCG" \
  -d "database=your_database"</pre>
        
        <h3>Get Job Status</h3>
        <pre style="background-color: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto;">
curl "get_job.php?jobId=job_1709000000_a1b2c3d4"</pre>
    </div>

</body>
</html>
