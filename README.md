# SequenceLinkOut JBrowse Plugin
Provides options to select and link out a sequence to another web-app, like ggblast.
Currently customized to link to ggblast

## BLAST API

This plugin includes a BLAST job submission API in the `blast/` directory. See [blast/README.md](blast/README.md) for full API documentation.

## Installing BLAST with Conda

The BLAST API requires NCBI BLAST+ to be installed and available in the system PATH. The easiest way to install BLAST is using conda/mamba.

### Prerequisites

First, install Miniconda or Anaconda if you haven't already:

```bash
# Download and install Miniconda (lightweight)
wget https://repo.anaconda.com/miniconda/Miniconda3-latest-Linux-x86_64.sh
bash Miniconda3-latest-Linux-x86_64.sh
source ~/.bashrc
```

### Installation Options

#### Option 1: Create Dedicated BLAST Environment (Recommended)

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

#### Option 2: Install in Base Environment (If Python Version Compatible)

#### Option 2: Install in Base Environment (If Python Version Compatible)

**Note:** Only use this if your base environment has Python 3.12 or earlier. Skip if you get dependency conflicts.

```bash
# Install BLAST+ in the base conda environment
conda install -c bioconda blast

# Verify installation
blastn -version
```

#### Option 3: Using Mamba (Faster and Better Dependency Resolution)

```bash
# Install mamba (faster conda alternative)
conda install -n base -c conda-forge mamba

# Install BLAST using mamba with compatible Python version
mamba create -n blast -c bioconda -c conda-forge python=3.12 blast

# Activate and verify
conda activate blast
blastn -version
```

#### Option 4: Install via System Package Manager (Quickest)

#### Option 4: Install via System Package Manager (Quickest)

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

### Troubleshooting Installation

#### Python 3.13 Compatibility Issue

If you get an error like `Pins seem to be involved in the conflict. Currently pinned specs: - python=3.13`:

**Solution:** BLAST packages don't support Python 3.13 yet. Use Option 1 (create dedicated environment with Python 3.12):

```bash
conda create -n blast -c bioconda -c conda-forge python=3.12 blast
conda activate blast
```

Or use Option 4 (system package manager):

```bash
sudo apt install ncbi-blast+
```

#### Dependency Conflicts with conda-libmamba-solver

If you see `LibMambaUnsatisfiableError` or `conda-libmamba-solver` errors:

1. Try creating a new environment with explicit Python version (Option 1)
2. Use mamba instead of conda (Option 3)
3. Use system package manager (Option 4)

### For System-Wide Access (Requires sudo)

If you need BLAST to be available to the web server user:

```bash
# Option A: Create symlinks to system binary directory
sudo ln -s /path/to/miniconda3/envs/blast/bin/blastn /usr/local/bin/
sudo ln -s /path/to/miniconda3/envs/blast/bin/blastp /usr/local/bin/
sudo ln -s /path/to/miniconda3/envs/blast/bin/blastx /usr/local/bin/
sudo ln -s /path/to/miniconda3/envs/blast/bin/tblastn /usr/local/bin/
sudo ln -s /path/to/miniconda3/envs/blast/bin/tblastx /usr/local/bin/

# Option B: Add conda environment to system PATH
# Edit /etc/environment or /etc/profile and add:
# PATH="/path/to/miniconda3/envs/blast/bin:$PATH"
```

### Verify Web Server Can Access BLAST

Test that the web server user can execute BLAST:

```bash
# Test as web server user (usually www-data or apache)
sudo -u www-data which blastn
sudo -u www-data blastn -version
```

### Setting Up BLAST Databases

```bash
# Create a directory for BLAST databases
mkdir -p /data/blastdb
cd /data/blastdb

# Download pre-formatted databases from NCBI (optional)
# For example, to download the nt database:
# update_blastdb.pl --decompress nt

# Or create your own database from a FASTA file:
makeblastdb -in your_sequences.fasta -dbtype nucl -out your_database_name

# Set BLASTDB environment variable
export BLASTDB=/data/blastdb
# Add to ~/.bashrc or /etc/environment for persistence
```

### Troubleshooting

If BLAST commands are not found by the web server after conda installation:

1. **Check PATH for web server user:**
   ```bash
   sudo -u www-data bash -c 'echo $PATH'
   sudo -u www-data which blastn
   ```

2. **For conda installations, create symlinks:**
   ```bash
   # Find the blast binary location
   conda activate blast
   which blastn
   
   # Create symlink (adjust path as needed)
   sudo ln -s /path/to/miniconda3/envs/blast/bin/blastn /usr/local/bin/
   ```

3. **Restart web server:**
   ```bash
   # Apache
   sudo systemctl restart apache2
   
   # nginx + php-fpm
   sudo systemctl restart php-fpm
   ```

4. **Check permissions:**
   ```bash
   ls -la /usr/local/bin/blastn
   sudo -u www-data /usr/local/bin/blastn -version
   ```

## Plugin Configuration (config.json)

The plugin's behavior is configured via the `config.json` file in the plugin root directory. This file contains settings for the BLAST service, database paths, and plugin behavior.

### Configuration File Location

```
plugins/SequenceLinkOut/config.json
```

### Configuration Options

| Option          | Required | Default | Description                                                                   |
|-----------------|----------|---------|-------------------------------------------------------------------------------|
| `dbPath`        | Yes      | -       | Path to BLAST database directory                                              |
| `blastExePath`  | Yes      | -       | Path to BLAST executables directory                                           |
| `jobsPath`      | Yes      | -       | Path to store BLAST job files and results                                     |
| `bpSizeLimit`   | No       | 20000   | Maximum base pair size for BLAST queries (helps prevent overload)             |
| `blastService`  | No       | null    | Set to `"php"` to use local PHP BLAST API, otherwise uses legacy redirect     |

### Example Configuration

```json
{
    "dbPath": "/data/blastdb_test/",
    "blastExePath": "/data/miniconda/miniconda3/envs/blast/bin/",
    "jobsPath": "/data/jobs/",
    "bpSizeLimit": 20000,
    "blastService": "php"
}
```

### Configuration Details

**dbPath**: Directory containing BLAST-formatted databases. Must be readable by the web server user.

**blastExePath**: Directory containing BLAST+ executables (blastn, blastp, etc.). Must be executable by the web server user.

**jobsPath**: Directory where job files, query sequences, and results are stored. Must be writable by the web server user. Each job gets its own subdirectory (e.g., `job_1234567890_abcdef12/`).

**bpSizeLimit**: Prevents users from submitting excessively large sequences that could impact server performance. When a user attempts to submit a sequence larger than this limit, they'll receive an error message. Can be adjusted based on your server capacity.

**blastService**: 
- Set to `"php"` to use the built-in PHP BLAST API (jobs run locally and return results asynchronously)
- Set to `null` or omit for legacy behavior (redirects to external `/blast` page)

### Directory Setup

Ensure the configured directories exist and have proper permissions:

```bash
# Create directories
sudo mkdir -p /data/blastdb_test
sudo mkdir -p /data/jobs
sudo mkdir -p /data/miniconda/miniconda3/envs/blast/bin

# Set ownership to web server user
sudo chown -R www-data:www-data /data/jobs

# Set permissions
sudo chmod 755 /data/blastdb_test
sudo chmod 755 /data/jobs
```

## JBrowse Configuration

Configure the plugin behavior in your JBrowse `trackList.json` or track configuration.

**Note:** The `blastService` and `bpSizeLimit` options have been moved to `config.json` (see Plugin Configuration section above). The options below are for JBrowse-specific settings.

### Basic Configuration

```json
{
  "blastDatabase": "TaFielder"
}
```

### JBrowse Configuration with Optional Parameters

```trackList.json
{
  "blastDatabase": "TaFielder",
  "blastEvalue": "1e-5",
  "blastMaxHits": 10
}
```

**Configuration Options:**

| Option           | Required | Default | Description                                                                       |
|------------------|----------|---------|-----------------------------------------------------------------------------------|
| `blastDatabase`  | Yes      | -       | Name of the BLAST database to search (must exist in `dbPath` from config.json)    |
| `blastEvalue`    | No       | 1e-5    | E-value threshold for BLAST search                                                |
| `blastMaxHits`   | No       | 10      | Maximum number of hits to return                                                  |

### Configuration Examples

**Example 1: Basic database configuration**
```json
{
  "blastDatabase": "wheat_RefSeqv1.0"
}
```

**Example 2: Custom BLAST parameters**
```json
{
  "blastDatabase": "wheat_RefSeqv1.0",
  "blastEvalue": "1e-10",
  "blastMaxHits": 20
}
```

**Example 3: In a specific track configuration**
```json
{
  "tracks": [
    {
      "label": "genes",
      "type": "CanvasFeatures",
      "blastDatabase": "wheat_genes"
    }
  ]
}
```

### How It Works

The plugin behavior depends on the `blastService` setting in `config.json`:

- **With `blastService: "php"` in config.json**: Sequences are submitted directly to the PHP BLAST API (`blast/submit_job.php`), which runs BLAST in the background and returns results asynchronously.

- **Without `blastService` (or set to null in config.json)**: Sequences are stored in localStorage and a new window opens to `/blast`, where an external BLAST interface retrieves the sequence data.

## Legacy Configuration (Deprecated)

For backward compatibility, the original configuration style still works:

In JBrowse trackList.json:
```
"blastDatabase": <ggblast database name>

For example:
"blastDatabase": "TaFielder"
```

