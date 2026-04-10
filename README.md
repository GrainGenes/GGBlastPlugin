# GGBlastPlugin - JBrowse Plugin
A JBrowse plugin to provide BLAST features within JBrowse.

When installed, a BLAST menu will appear at the top:
<img width="250" alt="image" src="https://github.com/user-attachments/assets/26ea0c65-88f9-484f-bb68-a46f88775783" />
When viewing a Gene/Transcript, a BLAST button enable blasting the gene.
<img width="400" alt="image" src="https://github.com/user-attachments/assets/8d83fd36-9a67-47f3-a7a7-a001906db2ff" />

<img width="912" height="743" alt="image" src="https://github.com/user-attachments/assets/12f5a64a-28f0-4220-93df-8a6415a7f6c4" />

<img width="436" height="238" alt="image" src="https://github.com/user-attachments/assets/95381dac-45bb-479b-97ae-9572df543232" />


<img width="282" height="112" alt="image" src="https://github.com/user-attachments/assets/c1f4630d-37b1-4168-9af5-3d19f5f8b3a5" />


<img width="1221" height="385" alt="image" src="https://github.com/user-attachments/assets/df79d3d9-4ce4-4ad6-9263-897c830eebde" />

## BLAST API

This plugin includes a BLAST job submission API in the `blast/` directory. See [blast/README.md](blast/README.md) for full API documentation.

## Installing BLAST with Conda

The BLAST API requires NCBI BLAST+ to be installed and available in the system PATH. The easiest way to install BLAST is using conda/mamba.

### NCBI BLAST Installation

#### Option 1: Create Dedicated BLAST Environment (Recommended)

First, install Miniconda or Anaconda if you haven't already:

```bash
# Download and install Miniconda (lightweight)
wget https://repo.anaconda.com/miniconda/Miniconda3-latest-Linux-x86_64.sh
bash Miniconda3-latest-Linux-x86_64.sh
source ~/.bashrc
```

**Important:** If you get Python version conflicts (e.g., with Python 3.13), use this method to specify a compatible Python version.

```bash
# Create a new conda environment for BLAST with Python 3.12
conda create -n blast -c bioconda -c conda-forge python=3.12 blast

# Activate the environment
conda activate blast

# Verify installation
blastn -version

# To make BLAST available system-wide, add to PATH
# Add this to your ~/.bashrc or /etc/profile:
export PATH="/path/to/miniconda3/envs/blast/bin:$PATH"
```

#### Option 2: Install via System Package Manager (Quickest)

If you just want to get BLAST working without conda complexity:

```bash
# On Ubuntu/Debian
sudo apt update
sudo apt install ncbi-blast+

# On CentOS/RHEL
sudo yum install ncbi-blast+

# Verify installation
blastn -version
```

## Plugin Configuration (config.json)

The plugin's behavior is configured via the `config.json` file in the plugin root directory. This file contains settings for the BLAST service, database paths, and plugin behavior.

**Configuration Priority:** Settings can be defined in both `config.json` and JBrowse's `trackList.json`. When both are present, `trackList.json` values take precedence over `config.json` values, allowing for dataset-specific overrides.

### Configuration File Location

```
plugins/GGBlastPlugin/config.json
```

### Configuration Options

| Option          | Required | Default                         | Description                                                                   |
|-----------------|----------|---------------------------------|-------------------------------------------------------------------------------|
| `dbPath`        | Yes      | -                               | Path to BLAST database directory                                              |
| `blastExePath`  | Yes      | -                               | Path to BLAST executables directory                                           |
| `jobsPath`      | Yes      | -                               | Path to store BLAST job files and results                                     |
| `bpSizeLimit`   | No       | 20000                           | Maximum base pair size for BLAST queries (helps prevent overload)             |
| `blastService`  | No       | null                            | Set to `"php"` to use local PHP BLAST API, otherwise uses legacy redirect     |
| `blastApp`      | No       | `/blast`                        | URL for remote BLAST service (used when `blastService` is not `"php"`)        |

### Example Configuration

```json
{
    "dbPath": "/data/blastdb_test/",
    "blastExePath": "/data/miniconda/miniconda3/envs/blast/bin/",
    "jobsPath": "/data/jobs/",
    "bpSizeLimit": 20000,
    "blastService": "php",
    "blastApp": "https://graingenes.org/blast/"
}
```

### Configuration Details

**dbPath**: Directory containing BLAST-formatted databases. Must be readable by the web server user.

**blastExePath**: Directory containing BLAST+ executables (blastn, blastp, etc.). Must be executable by the web server user.

**jobsPath**: Directory where job files, query sequences, and results are stored. Must be writable by the web server user. Each job gets its own subdirectory (e.g., `job_1234567890_abcdef12/`).

**bpSizeLimit**: Prevents users from submitting excessively large sequences that could impact server performance. When a user attempts to submit a sequence larger than this limit, they'll receive an error message. Can be adjusted based on your server capacity.

**blastService**: 
- Set to `"php"` to use the built-in PHP BLAST API (jobs run locally and return results asynchronously)
- Set to `null` or omit for legacy behavior (redirects to external BLAST page specified by `blastApp`)

**blastApp**: URL of the external BLAST application to redirect to when `blastService` is not set to `"php"`. Defaults to `/blast` if not specified. The sequence data is stored in localStorage and the external application should retrieve it from there. For example: `"https://graingenes.org/blast/"`

### Directory Setup

Ensure the configured directories exist and have proper permissions:

```bash
# Create directories
sudo mkdir -p /data/blastdb_test
sudo mkdir -p /data/jobs

# Set ownership to web server user
sudo chown -R www-data:www-data /data/jobs

# Set permissions
sudo chmod 755 /data/blastdb_test
sudo chmod 755 /data/jobs
```

## JBrowse Configuration

Configure the plugin behavior in your JBrowse `trackList.json` or track configuration.

**Configuration Priority:** Settings in `trackList.json` will override the same settings from `config.json`, allowing dataset-specific customization. You can override `bpSizeLimit`, `blastService`, and `blastApp` on a per-dataset basis.

### Basic Configuration

```json
{
  "blastDatabase": "TaFielder"
}
```

### Configuration with Optional Parameters

```json
{
  "blastDatabase": "TaFielder",
  "blastEvalue": "1e-5",
  "blastMaxHits": 10
}
```

### Configuration with Plugin Setting Overrides

You can override plugin-level settings from config.json:

```json
{
  "blastDatabase": "TaFielder",
  "blastService": "php",
  "bpSizeLimit": 50000,
  "blastApp": "https://custom-blast-server.org/blast/"
}
```

**Configuration Options:**

| Option           | Required | Default | Description                                                                       |
|------------------|----------|---------|-----------------------------------------------------------------------------------|
| `blastDatabase`  | Yes      | -       | Name of the BLAST database to search (must exist in `dbPath` from config.json)    |
| `blastEvalue`    | No       | 1e-5    | E-value threshold for BLAST search                                                |
| `blastMaxHits`   | No       | 10      | Maximum number of hits to return                                                  |
| `blastService`   | No       | -       | Override config.json: Set to `"php"` for local BLAST API                          |
| `bpSizeLimit`    | No       | -       | Override config.json: Maximum base pairs for queries                              |
| `blastApp`       | No       | -       | Override config.json: URL for remote BLAST service                                |

### Configuration Examples

**Example 1: Basic database configuration**
```json
{
  "blastDatabase": "wheat_RefSeqv1.0"
}
```

### How It Works

**Configuration Loading:**
1. Plugin loads default values
2. Settings from `config.json` are merged in
3. Settings from `trackList.json` override both defaults and `config.json` values

**BLAST Service Behavior:**

The plugin behavior depends on the `blastService` setting (from either `config.json` or `trackList.json`):

- **With `blastService: "php"`**: Sequences are submitted directly to the PHP BLAST API (`blast/submit_job.php`), which runs BLAST in the background and returns results asynchronously.

- **Without `blastService` (or set to null)**: Sequences are stored in localStorage and a new window opens to the URL specified in `blastApp` (defaults to `/blast`), where an external BLAST interface retrieves the sequence data.

## Running Tests

Run the unit tests to validate the plugin functionality:

```bash
npm test
```

This runs 38+ tests covering validation, security, configuration, and job management without requiring BLAST to be installed. See [tests/README.md](tests/README.md) for more details.

