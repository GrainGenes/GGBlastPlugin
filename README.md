# GGBlastPlugin - JBrowse Plugin

A JBrowse plugin that integrates BLAST search functionality, allowing users to BLAST genes, transcripts, or selected genomic regions directly from the genome browser with job tracking and history management.

**Live Demo:** https://graingenes.org/jb/?data=/ggds/whe-test

---

## Table of Contents

- [Features](#features)
- [Prerequisites](#prerequisites)
- [Quick Start Guide](#quick-start-guide)
- [Configuration Options](#configuration-options)
- [Testing](#testing)
- [Getting Help](#getting-help)
- [Contributors](#contributors)

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

## Quick Start Guide

From JBrowse `plugins` directory (`/path/to/jbrowse/`)
```
git clone https://github.com/GrainGenes/GGBlastPlugin.git
cd GGBlastPlugin
npm install
```

setup_demo.sh installs command-line BLAST tools and S_urartu blast database demo.
```
./setup_demo.sh
```

### Enable Plugin in JBrowse (jbrowse.conf)

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

### Configure Dataset (trackList.json)

Edit your dataset's `trackList.json` (e.g., `/path/to/jbrowse/data/dataset1/trackList.json`):

```json
{
  "blastDatabase": "S_urartu",
  "blastService": "php"
}
```

The `blastDatabase` value should match the name of a database file in your `dbPath` directory.

---

## Configuration Options

### Plugin Configuration (config.json)

This is found in the GGBlastPlugin directory: `plugins/GGBlastPlugin/config.json`

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
---

## Testing

The plugin includes a comprehensive test suite:

```bash
# Run all tests (unit tests + syntax checks)
npm test

For more details, see [tests/README.md](tests/README.md).

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

