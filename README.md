# GGBlastPlugin - JBrowse Plugin
A JBrowse plugin that integrates BLAST search functionality, allowing users to BLAST genes, transcripts, or selected genomic regions directly from the genome browser with job tracking and history management.

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

## Prerequisites

- **Install JBrowse 1.x** - A working JBrowse installation (beyond scope of this doc)
- **Install NCBI BLAST+** - Command-line BLAST tools (blastn, blastp, blastx, etc.)
- **Install BLAST Databases** - Pre-formatted BLAST databases for your organisms/sequences
- **Web Server/PHP** - Apache/Nginx with PHP 7.x support and write permissions for job storage directory (beyond the scope of this doc)
- **Node.js & npm** - For plugin installation and development

### Install JBrowse 1.x
https://jbrowse.org/jbrowse1.html

### Install NCBI BLAST+
There are various way of installing the blast command-line tools (blastn, blastp, makeblastdb, etc.)

Here is one way:
```bash
# On Ubuntu/Debian
sudo apt update
sudo apt install ncbi-blast+
```

### Install BLAST databases

### Web Server / PHP
A full description of this is beyond the scope of this doc.   
Presuming Apache2, use this to install php:
```
sudo a2enmod php*
```

### Node.js & npm
This is not strictly required but if you want to run some of the testing tools, you will need node.js and npm for the project.


## Setup

- **config.json** - Edit important directories in the GGBlastPlugin directory
- **jbrowse.conf** - Add the GGBlastPlugin in JBrowse jbrowse.conf file.
- **trackList.json** - Add references blastDatabase, blastSercice config.


### jbrowse.conf
This is found where JBrowse is installed.

In JBrowse's jbrowse.conf file:  
<img width="373" height="133" alt="image" src="https://github.com/user-attachments/assets/d524e2d0-6948-4eba-b9f6-98079d810b05" />


### config.json
This is found in the GGBlastPlugin directory

The plugin's behavior is configured via the `config.json` file in the plugin root directory. This file contains settings for the BLAST service, database paths, and plugin behavior.

**Configuration Priority:** Settings can be defined in both `config.json` and JBrowse's `trackList.json`. When both are present, `trackList.json` values take precedence over `config.json` values, allowing for dataset-specific overrides.

```
plugins/GGBlastPlugin/config.json
```

| Option          | Required | Default                         | Description                                                                   |
|-----------------|----------|---------------------------------|-------------------------------------------------------------------------------|
| `dbPath`        | Yes      | -                               | Path to BLAST database directory                                              |
| `blastExePath`  | Yes      | -                               | Path to BLAST executables directory                                           |
| `jobsPath`      | Yes      | -                               | Path to store BLAST job files and results                                     |
| `bpSizeLimit`   | No       | 20000                           | Maximum base pair size for BLAST queries (helps prevent overload)             |
| `blastService`  | No       | null                            | Set to `"php"` to use local PHP BLAST API, otherwise uses legacy redirect     |
| `blastApp`      | No       | `/blast`                        | URL for remote BLAST service (used when `blastService` is not `"php"`)        |

**Example Configuration**

We used the following in our example
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

**Configuration Details**

**dbPath**: Directory containing BLAST-formatted databases. Must be readable by the web server user.

**blastExePath**: Directory containing BLAST+ executables (blastn, blastp, etc.). Must be executable by the web server user.

use `which blastn` to determine where your blastExePath is located.

**jobsPath**: Directory where job files, query sequences, and results are stored. Must be writable by the web server user. Each job gets its own subdirectory (e.g., `job_1234567890_abcdef12/`).

**bpSizeLimit**: Prevents users from submitting excessively large sequences that could impact server performance. When a user attempts to submit a sequence larger than this limit, they'll receive an error message. Can be adjusted based on your server capacity.

**blastService**: 
- Set to `"php"` to use the built-in PHP BLAST API (jobs run locally and return results asynchronously)
- Set to `null` or omit for legacy behavior (redirects to external BLAST page specified by `blastApp`)

**blastApp**: URL of the external BLAST application to redirect to when `blastService` is not set to `"php"`. Defaults to `/blast` if not specified. The sequence data is stored in localStorage and the external application should retrieve it from there. For example: `"https://graingenes.org/blast/"`


### trackList.json
This is found in the genome browser dataset configuration.

Configure the plugin behavior in your JBrowse `trackList.json` or track configuration.

**Configuration Priority:** Settings in `trackList.json` will override the same settings from `config.json`, allowing dataset-specific customization. You can override `bpSizeLimit`, `blastService`, and `blastApp` on a per-dataset basis.

**Basic Configuration**

```json
{
  "blastDatabase": "S_urartu"
}
```

**Configuration with Optional Parameters**

```json
{
  "blastDatabase": "S_urartu",
  "blastEvalue": "1e-5",
  "blastMaxHits": 10
}
```


## BLAST API

This plugin includes a BLAST job submission API in the `blast/` directory. See [blast/README.md](blast/README.md) for full API documentation.


## Tests

Run the unit tests to validate the plugin functionality:

```bash
npm test
```

This runs 38+ tests covering validation, security, configuration, and job management without requiring BLAST to be installed. See [tests/README.md](tests/README.md) for more details.

