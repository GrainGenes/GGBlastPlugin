# GGBlastPlugin - JBrowse Plugin

A JBrowse plugin that integrates BLAST search functionality, allowing users to BLAST genes, transcripts, or selected genomic regions directly from the genome browser with job tracking and history management.

**Live Demo:** https://graingenes.org/jb/?data=/ggds/whe-test

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Quick Start Guide](#quick-start-guide)
- [Configuration](#configuration)
  - [Plugin Configuration (config.json)](#plugin-configuration-configjson)
  - [JBrowse Configuration (jbrowse.conf)](#jbrowse-configuration-jbrowseconf)
  - [Dataset Configuration (trackList.json)](#dataset-configuration-tracklistjson)
- [BLAST API](#blast-api)
- [Development & Testing](#development--testing)
- [Getting Help](#getting-help)

---

## Features

**BLAST Menu Integration** - The plugin adds a dedicated BLAST menu to the JBrowse navigation bar, providing quick access to BLAST functionality and job history:  
<img width="250" alt="image" src="https://github.com/user-attachments/assets/26ea0c65-88f9-484f-bb68-a46f88775783" />    

**One-Click Gene/Transcript BLAST** - When viewing genomic features, a BLAST button appears in the feature detail panel, enabling users to submit the gene or transcript sequence for BLAST analysis with a single click:  
<img width="400" alt="image" src="https://github.com/user-attachments/assets/8d83fd36-9a67-47f3-a7a7-a001906db2ff" />

**Real-Time Job Progress Tracking** - BLAST jobs open in a new browser tab displaying real-time job status, execution progress, and formatted results with alignment details and E-values:  
<img width="600" alt="image" src="https://github.com/user-attachments/assets/12f5a64a-28f0-4220-93df-8a6415a7f6c4" />

**Custom Region BLAST** - Users can select any genomic region using the browser's selection tool and submit that specific sequence for BLAST analysis:  
<img width="436" height="238" alt="image" src="https://github.com/user-attachments/assets/95381dac-45bb-479b-97ae-9572df543232" />

**Job History Access** - Access your BLAST job history from the BLAST menu to review, reopen, or manage past BLAST searches:  
<img width="282" height="112" alt="image" src="https://github.com/user-attachments/assets/c1f4630d-37b1-4168-9af5-3d19f5f8b3a5" />

**Job History Dashboard** - The job history interface displays all previous BLAST searches with timestamps, job status, database information, and quick access to results:  
<img width="600" alt="image" src="https://github.com/user-attachments/assets/df79d3d9-4ce4-4ad6-9263-897c830eebde" />

---

---

## Prerequisites

Before installing the plugin, ensure you have the following:

| Requirement          | Version | Required | Notes                                                          |
|:---------------------|:--------|:---------|:---------------------------------------------------------------|
| **JBrowse**          | 1.x     | Yes      | Working JBrowse installation. See [jbrowse.org]                |
| **PHP**              | 7.4+    | Yes      | For BLAST API backend                                          |
| **NCBI BLAST+**      | Latest  | Yes      | Command-line tools (blastn, blastp, blastx, etc.)              |
| **BLAST Databases**  | -       | Yes      | Pre-formatted databases for your organisms                     |
| **Web Server**       | -       | Yes      | Apache/Nginx with write permissions                            |
| **Node.js & npm**    | -       | No       | Only for development and testing                               |

[jbrowse.org]: https://jbrowse.org/jbrowse1.html

### Installing JBrowse 1.x

See the official documentation: https://jbrowse.org/jbrowse1.html

### Installing NCBI BLAST+

**Option 1: System Package Manager (Recommended for beginners)**

```bash
# On Ubuntu/Debian
sudo apt update
sudo apt install ncbi-blast+

# Verify installation
blastn -version
```

**Option 2: Conda/Miniconda**

```bash
# Create a new conda environment for BLAST
conda create -n blast -c bioconda -c conda-forge python=3.12 blast

# Activate the environment
conda activate blast

# Verify installation
blastn -version

# To make BLAST available system-wide, add to your ~/.bashrc:
export PATH="/path/to/miniconda3/envs/blast/bin:$PATH"
```

### Installing BLAST Databases

In our example the blast databases are placed in this directory:
```
"dbPath":"/data/blastdb_test/",
```
BLAST databases can be created from FASTA files using the `makeblastdb` tool:

```bash
# Example: Create a nucleotide database
makeblastdb -in sequences.fasta -dbtype nucl -out my_database

**Our Sample Database for Testing: (Stubby T. urartu)**
Download our sample database: http://graingenes.org/ggds/whe-test/S_urartu.zip

```bash
# Download and extract sample database
wget http://graingenes.org/ggds/whe-test/S_urartu.zip
unzip S_urartu.zip -d /data/blastdb_test/
```

### Web Server & PHP Setup

**Apache with PHP:**

```bash
# Install Apache and PHP
sudo apt install apache2 php libapache2-mod-php

# Enable PHP module
sudo a2enmod php8.1  # or php7.4, depending on version

# Restart Apache
sudo systemctl restart apache2
```

---

## Installation

### Step 1: Clone or Download the Plugin

```bash
# Navigate to your JBrowse plugins directory
cd /path/to/jbrowse/plugins/

# Clone the plugin repository
git clone https://github.com/GrainGenes/GGBlastPlugin.git

# Or download and extract manually to:
# /path/to/jbrowse/plugins/GGBlastPlugin/
```

### Step 2: Set Up Required Directories

Create directories for BLAST databases and job storage:

```bash
# Create directories
sudo mkdir -p /data/blastdb_test
sudo mkdir -p /data/jobs

# Set ownership to web server user (usually www-data or apache)
sudo chown -R www-data:www-data /data/jobs

# Set appropriate permissions
sudo chmod 755 /data/blastdb_test
sudo chmod 755 /data/jobs
```

**Important:** The `jobsPath` directory must be writable by your web server user.

### Step 3: Verify BLAST Installation

```bash
# Check that BLAST commands are accessible
which blastn

# Get the path to BLAST executables (needed for config.json)
dirname $(which blastn)
# Example output: /usr/bin or /data/miniconda/miniconda3/envs/blast/bin/
```

---

## Quick Start Guide

Follow these steps to get the plugin running:

### 1. Configure the Plugin (config.json)

Edit `/path/to/jbrowse/plugins/GGBlastPlugin/config.json`:

```json
{
    "dbPath": "/data/blastdb_test/",
    "blastExePath": "/usr/bin/",
    "jobsPath": "/data/jobs/",
    "bpSizeLimit": 20000,
    "blastService": "php"
}
```

**Quick Setup Tips:**
- `dbPath`: Where your BLAST databases are stored
- `blastExePath`: Output of `dirname $(which blastn)`
- `jobsPath`: Directory for job files (must be writable by web server)
- `blastService`: Set to `"php"` to use the built-in API

### 2. Enable Plugin in JBrowse (jbrowse.conf)

Add the plugin to your JBrowse configuration at `/path/to/jbrowse/jbrowse.conf`:

```json
{
  "plugins": [
    {
      "name": "GGBlastPlugin",
      "location": "plugins/GGBlastPlugin"
    }
  ]
}
```

### 3. Configure Dataset (trackList.json)

Edit your dataset's `trackList.json` (e.g., `/path/to/jbrowse/data/dataset1/trackList.json`):

```json
{
  "blastDatabase": "S_urartu",
  "blastService": "php"
}
```

The `blastDatabase` value should match the name of a database file in your `dbPath` directory.

### 4. Test the Installation

1. Open your JBrowse instance in a web browser
2. Look for the **BLAST** menu in the navigation bar
3. Click on a gene or transcript feature
4. Click the **BLAST** button that appears
5. Verify that a BLAST job is submitted and results appear

---

## Configuration

### Plugin Configuration (config.json)

This is found in the GGBlastPlugin directory: `plugins/GGBlastPlugin/config.json`

The plugin's behavior is configured via the `config.json` file in the plugin root directory. This file contains settings for the BLAST service, database paths, and plugin behavior.

**Configuration Priority:** Settings can be defined in both `config.json` and JBrowse's `trackList.json`. When both are present, `trackList.json` values take precedence over `config.json` values, allowing for dataset-specific overrides.

#### Configuration Options

| Option            | Required | Default   | Description                                                       |
|:------------------|:---------|:----------|:------------------------------------------------------------------|
| `dbPath`          | Yes      | -         | Path to BLAST database directory                                  |
| `blastExePath`    | Yes      | -         | Path to BLAST executables directory                               |
| `jobsPath`        | Yes      | -         | Path to store BLAST job files and results                         |
| `bpSizeLimit`     | No       | 20000     | Maximum base pair size for BLAST queries (prevents overload)      |
| `blastService`    | No       | null      | Set to `"php"` for local API, else uses legacy redirect           |
| `blastApp`        | No       | `/blast`  | URL for remote BLAST service (when `blastService` is not `"php"`) |

#### Example Configuration

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

#### Configuration Details

**`dbPath`**: Directory containing BLAST-formatted databases. Must be readable by the web server user.

**`blastExePath`**: Directory containing BLAST+ executables (blastn, blastp, etc.). Must be executable by the web server user. Use `which blastn` and then `dirname $(which blastn)` to find this path.

**`jobsPath`**: Directory where job files, query sequences, and results are stored. Must be writable by the web server user. Each job gets its own subdirectory (e.g., `job_1234567890_abcdef12/`).

**`bpSizeLimit`**: Prevents users from submitting excessively large sequences that could impact server performance. When a user attempts to submit a sequence larger than this limit, they'll receive an error message. Can be adjusted based on your server capacity.

**`blastService`**: 
- Set to `"php"` to use the built-in PHP BLAST API (jobs run locally and return results asynchronously)
- Set to `null` or omit for legacy behavior (redirects to external BLAST page specified by `blastApp`)

**`blastApp`**: URL of the external BLAST application to redirect to when `blastService` is not set to `"php"`. The sequence data is stored in localStorage and the external application should retrieve it from there.

### JBrowse Configuration (jbrowse.conf)

Add the plugin to your JBrowse configuration file at `/path/to/jbrowse/jbrowse.conf`:

```json
{
  "plugins": [
    {
      "name": "GGBlastPlugin",
      "location": "plugins/GGBlastPlugin"
    }
  ]
}
```

If you already have other plugins configured, add GGBlastPlugin to the existing array:

```json
{
  "plugins": [
    {
      "name": "ExistingPlugin",
      "location": "plugins/ExistingPlugin"
    },
    {
      "name": "GGBlastPlugin",
      "location": "plugins/GGBlastPlugin"
    }
  ]
}
```

### Dataset Configuration (trackList.json)

Configure the plugin behavior in your JBrowse dataset's `trackList.json` file (e.g., `/path/to/jbrowse/data/dataset1/trackList.json`).

**Configuration Priority:** Settings in `trackList.json` will override the same settings from `config.json`, allowing dataset-specific customization. You can override `bpSizeLimit`, `blastService`, and `blastApp` on a per-dataset basis.

#### Basic Configuration

```json
{
  "blastDatabase": "S_urartu"
}
```

The `blastDatabase` value should match the base name of your BLAST database files (without extensions like .nhr, .nin, .nsq).

#### Configuration with Optional Parameters

```json
{
  "blastDatabase": "S_urartu",
  "blastEvalue": "1e-5",
  "blastMaxHits": 10
}
```


---

## BLAST API

See [blast/README.md](blast/README.md) for full API documentation including:
- Endpoint specifications
- Request/response formats
- Authentication options
- Rate limiting
- Error handling

---

## Development & Testing

### Running Tests

The plugin includes a comprehensive test suite:

```bash
# Run all tests (unit tests + syntax checks)
npm test

For more details, see [tests/README.md](tests/README.md).


```bash
# Clone the repository
git clone https://github.com/GrainGenes/GGBlastPlugin.git
cd GGBlastPlugin

# Install development dependencies (optional)
npm install

# Run tests before making changes
npm test

# Make your changes...

# Run tests again to verify
npm test
```

---

## Getting Help

### Documentation

- [BLAST API Documentation](blast/README.md) - Details on the PHP BLAST API
- [Testing Documentation](tests/README.md) - Information about the test suite
- [JBrowse Documentation](https://jbrowse.org/jbrowse1.html) - Official JBrowse 1.x docs

### Live Example

See the plugin in action: https://graingenes.org/jb/?data=/ggds/whe-test

### Sample Resources

- **Sample BLAST Database**: http://graingenes.org/ggds/whe-test/S_urartu.zip
- **Sample Dataset Configuration**: Available at the live example above

---

## Contributors

Developed and maintained by the GrainGenes team.

---

**Version**: 1.0.0  
**Last Updated**: 2026

