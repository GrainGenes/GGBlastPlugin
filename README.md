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

## JBrowse Configuration

Configure the plugin behavior in your JBrowse `trackList.json` or track configuration:

### Basic Configuration

```json
{
  "blastDatabase": "TaFielder"
}
```

### PHP BLAST Service Configuration

To use the built-in PHP BLAST API instead of redirecting to an external BLAST page:

```json
{
  "blastService": "php",
  "blastDatabase": "TaFielder",
  "blastEvalue": "1e-5",
  "blastMaxHits": 10
}
```

**Configuration Options:**

| Option | Required | Default | Description |
|--------|----------|---------|-------------|
| `blastDatabase` | Yes | - | Name of the BLAST database to search |
| `blastService` | No | (legacy) | Set to `"php"` to use the PHP API, otherwise opens `/blast` page |
| `blastEvalue` | No | 1e-5 | E-value threshold for BLAST search |
| `blastMaxHits` | No | 10 | Maximum number of hits to return |

### Configuration Examples

**Example 1: Use PHP API with custom parameters**
```json
{
  "blastService": "php",
  "blastDatabase": "wheat_RefSeqv1.0",
  "blastEvalue": "1e-10",
  "blastMaxHits": 20
}
```

**Example 2: Legacy behavior (redirect to BLAST page)**
```json
{
  "blastDatabase": "TaFielder"
}
```

**Example 3: In a specific track configuration**
```json
{
  "tracks": [
    {
      "label": "genes",
      "type": "CanvasFeatures",
      "blastService": "php",
      "blastDatabase": "wheat_genes"
    }
  ]
}
```

### How It Works

- **With `blastService: "php"`**: Sequences are submitted directly to the PHP BLAST API (`blast/submit_job.php`), which runs BLAST in the background and returns results asynchronously.

- **Without `blastService` (legacy)**: Sequences are stored in localStorage and a new window opens to `/blast`, where an external BLAST interface retrieves the sequence data.

## Legacy Configuration (Deprecated)

For backward compatibility, the original configuration style still works:

In JBrowse trackList.json:
```
"blastDatabase": <ggblast database name>

For example:
"blastDatabase": "TaFielder"
```

