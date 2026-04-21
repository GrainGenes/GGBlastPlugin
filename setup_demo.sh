#!/bin/bash
set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}  GGBlastPlugin Setup Script${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# BLAST+ download URL
BLAST_URL="https://ftp.ncbi.nlm.nih.gov/blast/executables/blast+/LATEST/ncbi-blast-2.17.0+-x64-linux.tar.gz"
BLAST_ARCHIVE="ncbi-blast-2.17.0+-x64-linux.tar.gz"
BLAST_DIR="ncbi-blast-2.17.0+"

# Database download URL
DB_URL="http://graingenes.org/ggds/whe-test/S_urartu.zip"
DB_ARCHIVE="S_urartu.zip"
DB_DIR="demo-blastdb"

# Jobs directory
JOBS_DIR="demo-jobs"

# Step 1: Download and extract BLAST+ executables
echo -e "${YELLOW}[1/4] Downloading NCBI BLAST+ executables...${NC}"
if [ -d "$BLAST_DIR" ]; then
    echo -e "${GREEN}  ✓ BLAST+ already unpacked. Skipping.${NC}"
else
    if [ -f "$BLAST_ARCHIVE" ]; then
        echo -e "${YELLOW}  Archive already exists. Skipping download.${NC}"
    else
        wget --show-progress "$BLAST_URL" || {
            echo -e "${RED}  Failed to download BLAST+${NC}"
            exit 1
        }
        echo -e "${GREEN}  ✓ Downloaded${NC}"
    fi
    
    echo -e "${YELLOW}[2/4] Extracting BLAST+ executables...${NC}"
    tar -xzf "$BLAST_ARCHIVE" || {
        echo -e "${RED}  Failed to extract BLAST+${NC}"
        exit 1
    }
    echo -e "${GREEN}  ✓ Extracted to $BLAST_DIR${NC}"
    
    # Clean up archive
    rm -f "$BLAST_ARCHIVE"
    echo -e "${GREEN}  ✓ Cleaned up archive${NC}"
fi

# Step 2: Download and extract BLAST database
echo -e "${YELLOW}[3/4] Downloading sample BLAST database...${NC}"
if [ -d "$DB_DIR" ] && [ "$(ls -A $DB_DIR 2>/dev/null)" ]; then
    echo -e "${GREEN}  ✓ Database already unpacked. Skipping.${NC}"
else
    mkdir -p "$DB_DIR"
    
    if [ -f "$DB_ARCHIVE" ]; then
        echo -e "${YELLOW}  Archive already exists. Skipping download.${NC}"
    else
        wget --show-progress "$DB_URL" || {
            echo -e "${RED}  Failed to download database${NC}"
            exit 1
        }
        echo -e "${GREEN}  ✓ Downloaded${NC}"
    fi
    
    echo -e "${YELLOW}[4/4] Extracting BLAST database...${NC}"
    unzip -q "$DB_ARCHIVE" -d "$DB_DIR" || {
        echo -e "${RED}  Failed to extract database${NC}"
        exit 1
    }
    
    # Check if extraction created a nested blastdb directory and flatten it
    if [ -d "$DB_DIR/blastdb" ]; then
        echo -e "${YELLOW}  Flattening nested directory structure...${NC}"
        mv "$DB_DIR/blastdb"/* "$DB_DIR/" 2>/dev/null || true
        rmdir "$DB_DIR/blastdb" 2>/dev/null || true
    fi
    
    echo -e "${GREEN}  ✓ Extracted to $DB_DIR${NC}"
    
    # Clean up archive
    rm -f "$DB_ARCHIVE"
    echo -e "${GREEN}  ✓ Cleaned up archive${NC}"
fi

# Step 3: Create jobs directory
echo ""
echo -e "${YELLOW}[4/5] Creating jobs directory...${NC}"
if [ ! -d "$JOBS_DIR" ]; then
    mkdir -p "$JOBS_DIR"
    echo -e "${GREEN}  ✓ Created $JOBS_DIR directory${NC}"
else
    echo -e "${GREEN}  ✓ Jobs directory already exists${NC}"
fi

# Set permissions so web server can write to jobs directory
chmod 777 "$JOBS_DIR"
echo -e "${GREEN}  ✓ Set permissions on $JOBS_DIR (777 - web server writable)${NC}"

# Step 4: Verify BLAST installation and update config.json
echo ""
echo -e "${YELLOW}[5/5] Verifying BLAST installation and updating config.json...${NC}"
CONFIG_FILE="$SCRIPT_DIR/config.json"

# Determine the correct BLAST executable path
BLAST_EXE_PATH=""
if [ -f "$SCRIPT_DIR/$BLAST_DIR/bin/blastn" ]; then
    # Test local BLAST installation
    if "$SCRIPT_DIR/$BLAST_DIR/bin/blastn" -version &>/dev/null; then
        BLAST_EXE_PATH="$SCRIPT_DIR/$BLAST_DIR/bin/"
        echo -e "${GREEN}  ✓ Using local BLAST installation: $BLAST_EXE_PATH${NC}"
    else
        echo -e "${YELLOW}  ⚠ Local BLAST found but not working${NC}"
    fi
fi

# If local BLAST not found or not working, check system PATH
if [ -z "$BLAST_EXE_PATH" ]; then
    if command -v blastn &>/dev/null; then
        SYSTEM_BLASTN=$(command -v blastn)
        BLAST_EXE_PATH=$(dirname "$SYSTEM_BLASTN")/
        echo -e "${GREEN}  ✓ Using system BLAST installation: $BLAST_EXE_PATH${NC}"
    else
        echo -e "${RED}  ✗ No working BLAST installation found${NC}"
        exit 1
    fi
fi

# Verify directories exist
if [ ! -d "$SCRIPT_DIR/$DB_DIR" ]; then
    echo -e "${RED}  ✗ Database directory not found: $SCRIPT_DIR/$DB_DIR${NC}"
    exit 1
fi

if [ ! -d "$SCRIPT_DIR/$JOBS_DIR" ]; then
    echo -e "${RED}  ✗ Jobs directory not found: $SCRIPT_DIR/$JOBS_DIR${NC}"
    exit 1
fi

# Update config.json with verified paths
if [ -f "$CONFIG_FILE" ]; then
    sed -i "s|\"dbPath\"[[:space:]]*:[[:space:]]*\"[^\"]*\"|\"dbPath\":\"$SCRIPT_DIR/$DB_DIR/\"|g" "$CONFIG_FILE"
    sed -i "s|\"blastExePath\"[[:space:]]*:[[:space:]]*\"[^\"]*\"|\"blastExePath\": \"$BLAST_EXE_PATH\"|g" "$CONFIG_FILE"
    sed -i "s|\"jobsPath\"[[:space:]]*:[[:space:]]*\"[^\"]*\"|\"jobsPath\":\"$SCRIPT_DIR/$JOBS_DIR/\"|g" "$CONFIG_FILE"
    echo -e "${GREEN}  ✓ Updated config.json with verified paths${NC}"
else
    echo -e "${RED}  ✗ config.json not found${NC}"
    exit 1
fi

# Summary
echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}  Setup Complete!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo "BLAST+ executables location:"
echo "  $BLAST_EXE_PATH"
echo ""
echo "BLAST database location:"
echo "  $SCRIPT_DIR/$DB_DIR/"
echo ""
echo "Jobs directory:"
echo "  $SCRIPT_DIR/$JOBS_DIR/"
echo ""
echo -e "${GREEN}config.json has been updated with verified paths.${NC}"
echo ""
echo "Test BLAST installation:"
echo -e "  ${YELLOW}${BLAST_EXE_PATH}blastn -version${NC}"
echo ""
