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
    <title>BLAST Jobs</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 1200px;
            width: 100%;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 500;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        code {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        a:hover {
            color: #5568d3;
            text-decoration: underline;
        }
        
        .status-completed {
            color: #4CAF50;
            font-weight: 500;
        }
        
        .status-failed {
            color: #ff6b6b;
            font-weight: 500;
        }
        
        .status-running {
            color: #FF9800;
            font-weight: 500;
        }
        
        .status-pending {
            color: #FF9800;
            font-weight: 500;
        }
        
        .no-jobs {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
        
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            transition: background 0.3s;
            margin: 20px 5px 0;
            cursor: pointer;
            border: none;
        }
        
        .button:hover {
            background: #5568d3;
        }
        
        .button-secondary {
            background: #6c757d;
        }
        
        .button-secondary:hover {
            background: #5a6268;
        }
        
        .actions-center {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BLAST Jobs</h1>
            <p>Recent job submissions</p>
        </div>
        <?php
        // Load configuration
        $configFile = __DIR__ . '/../config.json';
        $jobsDir = __DIR__ . '/../jobs'; // Default fallback
        
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config && isset($config['jobsPath'])) {
                $jobsDir = rtrim($config['jobsPath'], '/');
            }
        }
        
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
            echo '<div class="no-jobs">No jobs found.</div>';
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
                echo '<a href="results.php?jobId=' . htmlspecialchars($job['jobId']) . '">View</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        }
        ?>
        
    </div>

</body>
</html>
