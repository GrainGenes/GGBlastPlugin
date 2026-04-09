# BLAST Job Submission API

This directory contains PHP scripts for submitting and managing BLAST jobs via HTTP POST/GET requests.

## Integration with JBrowse

This API is integrated with the GGBlastPlugin JBrowse plugin. To enable automatic BLAST submission from JBrowse, add the following to your trackList.json:

```json
{
  "blastService": "php",
  "blastDatabase": "your_database_name"
}
```

See the main [GGBlastPlugin README](../README.md#jbrowse-configuration) for complete configuration options.

## API Endpoints

### 1. Submit BLAST Job

**Endpoint:** `submit_job.php`
**Method:** POST
**Content-Type:** `application/x-www-form-urlencoded` or `multipart/form-data`

#### Required Parameters

| Parameter | Type   | Description                                    |
|-----------|--------|------------------------------------------------|
| blastexe  | string | BLAST executable type                          |
| query     | string | DNA or protein sequence to search              |

#### Optional Parameters

| Parameter | Type   | Default | Description                          |
|-----------|--------|---------|--------------------------------------|
| database  | string | null    | Target BLAST database name           |
| evalue    | string | 1e-5    | E-value threshold                    |
| maxHits   | int    | 10      | Maximum number of hits to return     |

#### Valid BLAST Executables

- `blastn` - Nucleotide-nucleotide BLAST
- `blastp` - Protein-protein BLAST
- `blastx` - Translated nucleotide vs protein
- `tblastn` - Protein vs translated nucleotide
- `tblastx` - Translated nucleotide vs translated nucleotide

#### Example Request (cURL)

```bash
curl -X POST http://your-domain/plugins/GGBlastPlugin/blast/submit_job.php \
  -d "blastexe=blastn" \
  -d "query=ATCGATCGATCGATCG" \
  -d "database=TaFielder"
```

#### Example Request (JavaScript)

```javascript
fetch('/plugins/GGBlastPlugin/blast/submit_job.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams({
        blastexe: 'blastn',
        query: 'ATCGATCGATCGATCG',
        database: 'TaFielder'
    })
})
.then(response => response.json())
.then(data => {
    console.log('Job ID:', data.jobId);
})
.catch(error => console.error('Error:', error));
```

#### Success Response (201 Created)

```json
{
  "success": true,
  "jobId": "job_1709000000_a1b2c3d4",
  "status": "running",
  "message": "Job created and running in background",
  "statusCheckUrl": "get_job.php?jobId=job_1709000000_a1b2c3d4"
}
```

**Note:** The job is executed asynchronously in the background using `nohup`. The API returns immediately with status `running`, and you should poll the status endpoint to check for completion.

#### Error Responses

**400 Bad Request** - Missing or invalid parameters, or BLAST executable not found
```json
{
  "success": false,
  "jobId": "job_1709000000_a1b2c3d4",
  "error": "BLAST executable not found: blastn",
  "message": "Job creation failed"
}
```

**405 Method Not Allowed** - Non-POST request
```json
{
  "success": false,
  "error": "Method Not Allowed. Only POST requests are accepted."
}
```

**500 Internal Server Error** - Failed to start background process
```json
{
  "success": false,
  "jobId": "job_1709000000_a1b2c3d4",
  "error": "Failed to start BLAST background process",
  "message": "Job creation failed"
}
```

---

### 2. Get Job Status

**Endpoint:** `get_job.php`
**Method:** GET or POST

#### Required Parameters

| Parameter | Type   | Description                          |
|-----------|--------|--------------------------------------|
| jobId     | string | Job ID returned from submit_job.php |

#### Example Request (cURL)

```bash
curl "http://your-domain/plugins/GGBlastPlugin/blast/get_job.php?jobId=job_1709000000_a1b2c3d4"
```

#### Example Request (JavaScript)

```javascript
fetch('/plugins/GGBlastPlugin/blast/get_job.php?jobId=job_1709000000_a1b2c3d4')
.then(response => response.json())
.then(data => {
    console.log('Job status:', data.job.status);
    console.log('Job data:', data.job);
})
.catch(error => console.error('Error:', error));
```

#### Success Response (200 OK)

```json
{
  "success": true,
  "job": {
    "jobId": "job_1709000000_a1b2c3d4",
    "blastexe": "blastn",
    "query": "ATCGATCGATCGATCG",
    "database": "TaFielder",
    "evalue": "1e-5",
    "maxHits": 10,
    "status": "completed",
    "created": "2026-02-26 12:34:56",
    "completed": "2026-02-26 12:35:01",
    "resultsUrl": "jobs/job_1709000000_a1b2c3d4/results.html",
    "resultsSize": 45632
  }
}
```

**Possible Status Values:**
- `running` - Job is currently executing
- `completed` - Job finished successfully
- `failed` - Job failed during execution

#### Error Responses

**400 Bad Request** - Missing or invalid jobId
```json
{
  "success": false,
  "error": "Invalid jobId format"
}
```

**404 Not Found** - Job does not exist
```json
{
  "success": false,
  "error": "Job not found"
}
```

---

## Job Storage

Jobs are stored in the `../jobs/` directory. Each job is stored in its own directory named after the jobId.

### Job Directory Structure

```
jobs/
└── job_1709000000_a1b2c3d4/
    ├── query.json         # Job metadata and parameters
    ├── query.fasta        # Input query sequence
    ├── results.html       # BLAST results in HTML format
    ├── error.log          # BLAST stderr output
    ├── status.txt         # Current job status (completed/failed)
    ├── blast.pid          # Process ID of running BLAST
    └── run_blast.sh       # Wrapper script for BLAST execution
```

### Job Metadata File (query.json)

```json
{
  "jobId": "job_1709000000_a1b2c3d4",
  "blastexe": "blastn",
  "query": "ATCGATCGATCGATCG",
  "database": "TaFielder",
  "evalue": "1e-5",
  "maxHits": 10,
  "status": "completed",
  "created": "2026-02-26 12:34:56",
  "completed": "2026-02-26 12:35:01",
  "clientIp": "192.168.1.100"
}
```

### Accessing Results

Results are available as HTML files that can be accessed directly:
- Path: `jobs/{jobId}/results.html`
- Example: `jobs/job_1709000000_a1b2c3d4/results.html`

## BLAST Execution

The API executes BLAST commands **asynchronously in the background** using `nohup` and a wrapper shell script. This allows the API to return immediately while BLAST runs. The BLAST executable must be installed and available in the system PATH.

### Background Execution Process

1. Job submission creates a wrapper script (`run_blast.sh`) that executes BLAST
2. The wrapper script is launched in the background using `nohup`
3. The API returns immediately with status `running`
4. The wrapper script updates job status upon completion
5. Clients poll the status endpoint to check for completion

### Command Format

```bash
{blastexe} -query {query.fasta} -html [-db {database}] [-evalue {evalue}] [-max_target_seqs {maxHits}]
```

### Prerequisites

- BLAST+ toolkit must be installed on the server
- BLAST executables must be in the system PATH
- BLAST databases must be configured and accessible
- Web server must have execute permissions for BLAST commands

### Installation of BLAST+

```bash
# On Ubuntu/Debian
sudo apt-get install ncbi-blast+

# On CentOS/RHEL
sudo yum install ncbi-blast+

# Or download from NCBI
# https://blast.ncbi.nlm.nih.gov/Blast.cgi?PAGE_TYPE=BlastDocs&DOC_TYPE=Download
```

Job IDs follow the format: `job_{timestamp}_{hash}`

Example: `job_1709000000_a1b2c3d4`

- `job_` - Prefix
- `1709000000` - Unix timestamp
- `a1b2c3d4` - 8-character hash for uniqueness

## Security Considerations

- BLAST executable validation ensures only valid BLAST types can be used
- Job IDs are validated to prevent directory traversal attacks
- Command parameters are properly escaped using `escapeshellcmd()` and `escapeshellarg()`
- Client IP addresses are logged but not returned in API responses
- Query sequences are saved to files to prevent command injection
- Consider implementing rate limiting to prevent abuse
- Consider adding authentication for production use
- Consider implementing job cleanup for old jobs
- Ensure BLAST database paths are properly secured
- Consider setting execution timeouts for long-running BLAST jobs

## Error Handling

### Instant Failures

If the job fails immediately (e.g., BLAST executable not found, failed to start process):
- The API returns status 400 or 500
- Response includes `success: false` and error details:

```json
{
  "success": false,
  "jobId": "job_1709000000_a1b2c3d4",
  "error": "BLAST executable not found: blastn",
  "message": "Job creation failed"
}
```

### Runtime Failures

If BLAST fails during execution:
- The job status will be set to `failed` by the wrapper script
- Error details will be captured in `error.log` within the job directory
- The `get_job.php` endpoint will show the failure:

```json
{
  "success": true,
  "job": {
    "jobId": "job_1709000000_a1b2c3d4",
    "status": "failed",
    "error": "BLAST execution failed with exit code: 1",
    "errorDetails": "Error message from BLAST..."
  }
}
```

Check the job's `query.json` and `error.log` files for detailed error information.

## Installation

1. Ensure PHP is installed and configured on your web server
2. Ensure the web server has write permissions to the `../jobs/` directory
3. The scripts will automatically create the jobs directory if it doesn't exist

## Future Enhancements

- Implement asynchronous job execution for large queries
- Add job queueing system for managing multiple concurrent jobs
- Add result retrieval endpoint in JSON format
- Implement job cleanup/expiration for old jobs
- Add authentication and authorization
- Add rate limiting to prevent abuse
- Add WebSocket support for real-time status updates
- Add progress tracking for long-running jobs
- Support for additional output formats (XML, JSON, tabular)

## Test Page

https://malt.pw.usda.gov/jb/plugins/GGBlastPlugin/blast/test.html
