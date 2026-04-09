<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BLAST Results</title>
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
            max-width: 900px;
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
        
        .job-id {
            color: #666;
            font-size: 14px;
            font-family: 'Courier New', monospace;
        }
        
        .status-section {
            text-align: center;
            padding: 40px 0;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .status-text {
            color: #555;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .status-detail {
            color: #999;
            font-size: 14px;
        }
        
        .results-container {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            overflow: auto;
        }
        
        .error-container {
            background: #fff3f3;
            border: 2px solid #ff6b6b;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .error-container h3 {
            color: #ff6b6b;
            margin-bottom: 10px;
        }
        
        .error-message {
            color: #666;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
        }
        
        .success-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #4caf50;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .info-item {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 6px;
        }
        
        .info-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }
        
        .hidden {
            display: none;
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
            margin: 10px 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BLAST Results</h1>
            <div class="job-id">Job ID: <span id="jobIdDisplay"></span></div>
        </div>
        
        <!-- Loading State -->
        <div id="loadingState" class="status-section">
            <div class="spinner"></div>
            <div class="status-text">Processing your BLAST job...</div>
            <div class="status-detail">Checking status every 3 seconds</div>
        </div>
        
        <!-- Completed State -->
        <div id="completedState" class="hidden">
            <div class="status-section">
                <div class="success-icon">✓</div>
                <div class="status-text">BLAST job completed successfully!</div>
            </div>
            
            <div id="jobInfo" class="info-grid"></div>
            
            <div class="results-container">
                <iframe id="resultsFrame" style="width: 100%; min-height: 600px; border: none;"></iframe>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="#" class="button" onclick="window.location.reload(); return false;">Refresh</a>
                <a href="jobs.php" class="button button-secondary">View All Jobs</a>
            </div>
        </div>
        
        <!-- Failed State -->
        <div id="failedState" class="hidden">
            <div class="error-container">
                <h3>❌ BLAST Job Failed</h3>
                <div class="error-message" id="errorMessage"></div>
            </div>
            
            <div id="failedJobInfo" class="info-grid"></div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="jobs.php" class="button button-secondary">Back to Jobs</a>
            </div>
        </div>
        
        <!-- Error State (job not found, etc.) -->
        <div id="errorState" class="hidden">
            <div class="error-container">
                <h3>❌ Error</h3>
                <div class="error-message" id="errorStateMessage"></div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="jobs.php" class="button button-secondary">Back to Jobs</a>
            </div>
        </div>
    </div>
    
    <script>
        // Get jobId from URL parameter
        const urlParams = new URLSearchParams(window.location.search);
        const jobId = urlParams.get('jobId');
        
        if (!jobId) {
            showError('No job ID provided. Please provide a jobId parameter.');
        } else {
            document.getElementById('jobIdDisplay').textContent = jobId;
            checkJobStatus();
        }
        
        let pollCount = 0;
        const MAX_POLLS = 200; // Maximum 10 minutes (200 * 3 seconds)
        
        function checkJobStatus() {
            fetch('get_job.php?jobId=' + encodeURIComponent(jobId))
                .then(response => response.json())
                .then(data => {
                    console.log('Job status response:', data);
                    
                    if (!data.success) {
                        showError(data.error || 'Failed to fetch job status');
                        return;
                    }
                    
                    const status = data.job.status;
                    console.log('Current status:', status);
                    
                    if (status === 'completed') {
                        showCompleted(data.job);
                    } else if (status === 'failed') {
                        showFailed(data.job);
                    } else if (status === 'running' || status === 'pending') {
                        pollCount++;
                        if (pollCount < MAX_POLLS) {
                            // Continue polling
                            setTimeout(checkJobStatus, 3000); // Check every 3 seconds
                        } else {
                            showError('Job is taking too long. It may still be running. Please refresh the page to check again.');
                        }
                    } else {
                        showError('Unknown job status: ' + status);
                    }
                })
                .catch(error => {
                    console.error('Error checking job status:', error);
                    showError('Error checking job status: ' + error.message);
                });
        }
        
        function showCompleted(jobData) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('completedState').classList.remove('hidden');
            
            // Display job info
            const jobInfo = document.getElementById('jobInfo');
            jobInfo.innerHTML = `
                <div class="info-item">
                    <div class="info-label">BLAST Type</div>
                    <div class="info-value">${jobData.blastexe || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Database</div>
                    <div class="info-value">${jobData.database || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">E-value</div>
                    <div class="info-value">${jobData.evalue || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Max Hits</div>
                    <div class="info-value">${jobData.maxHits || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">${jobData.created || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Completed</div>
                    <div class="info-value">${jobData.completed || 'N/A'}</div>
                </div>
            `;
            
            // Load results in iframe
            const resultsFrame = document.getElementById('resultsFrame');
            resultsFrame.src = 'get_results.php?jobId=' + encodeURIComponent(jobId);
        }
        
        function showFailed(jobData) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('failedState').classList.remove('hidden');
            
            const errorMessage = document.getElementById('errorMessage');
            errorMessage.textContent = jobData.error || 'BLAST execution failed';
            
            if (jobData.errorDetails) {
                errorMessage.textContent += '\n\nDetails:\n' + jobData.errorDetails;
            }
            
            // Display job info
            const jobInfo = document.getElementById('failedJobInfo');
            jobInfo.innerHTML = `
                <div class="info-item">
                    <div class="info-label">BLAST Type</div>
                    <div class="info-value">${jobData.blastexe || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Database</div>
                    <div class="info-value">${jobData.database || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value">${jobData.created || 'N/A'}</div>
                </div>
            `;
        }
        
        function showError(message) {
            document.getElementById('loadingState').classList.add('hidden');
            document.getElementById('errorState').classList.remove('hidden');
            document.getElementById('errorStateMessage').textContent = message;
        }
    </script>
</body>
</html>
