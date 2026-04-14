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
DB_DIR="blastdb"

# Jobs directory
JOBS_DIR="jobs"

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

# Step 4: Update config.json
echo ""
echo -e "${YELLOW}[5/5] Updating config.json...${NC}"
CONFIG_FILE="$SCRIPT_DIR/config.json"

if [ -f "$CONFIG_FILE" ]; then
    # Update the paths in config.json using sed
    sed -i "s|\"dbPath\":\"[^\"]*\"|\"dbPath\":\"$SCRIPT_DIR/$DB_DIR/\"|g" "$CONFIG_FILE"
    sed -i "s|\"blastExePath\":\"[^\"]*\"|\"blastExePath\":\"$SCRIPT_DIR/$BLAST_DIR/bin/\"|g" "$CONFIG_FILE"
    sed -i "s|\"jobsPath\":\"[^\"]*\"|\"jobsPath\":\"$SCRIPT_DIR/$JOBS_DIR/\"|g" "$CONFIG_FILE"
    echo -e "${GREEN}  ✓ Updated config.json${NC}"
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
echo "  $SCRIPT_DIR/$BLAST_DIR/bin/"
echo ""
echo "BLAST database location:"
echo "  $SCRIPT_DIR/$DB_DIR/"
echo ""
echo "Jobs directory:"
echo "  $SCRIPT_DIR/$JOBS_DIR/"
echo ""
echo -e "${GREEN}config.json has been updated with the correct paths.${NC}"
echo -e "${YELLOW}}${NC}"
echo ""
echo "Test BLAST installation:"
echo -e "  ${YELLOW}$SCRIPT_DIR/$BLAST_DIR/bin/blastn -version${NC}"
echo ""
