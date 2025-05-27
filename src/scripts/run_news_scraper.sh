#!/bin/bash

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Change to the script directory
cd "$SCRIPT_DIR"

# Run the PHP script
php news_scraper.php

# Log the execution
echo "News scraper executed at $(date)" >> scraper.log 