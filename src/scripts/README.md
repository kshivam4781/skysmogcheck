# News Scraper

This script automatically scrapes news articles from the California Air Resources Board (CARB) website and other sources, processes the content, and stores it in the database.

## Features

- Scrapes news from CARB website
- Paraphrases content to avoid duplicate content
- Downloads and saves images
- Checks for duplicate articles
- Runs automatically via cron job

## Setup

1. Make sure the script is executable:
```bash
chmod +x run_news_scraper.sh
```

2. Set up a cron job to run the script daily. Edit your crontab:
```bash
crontab -e
```

3. Add the following line to run the script daily at 1 AM:
```bash
0 1 * * * /path/to/your/project/src/scripts/run_news_scraper.sh
```

## Directory Structure

- `news_scraper.php`: Main PHP script for scraping and processing news
- `run_news_scraper.sh`: Shell script to execute the PHP script
- `scraper.log`: Log file for script execution

## Requirements

- PHP 7.4 or higher
- PHP DOM extension
- PHP cURL extension
- MySQL/MariaDB database
- Write permissions for the images directory

## Error Handling

The script logs errors to:
- PHP error log
- `scraper.log` file

## Customization

You can customize the script by:
1. Adding more news sources
2. Modifying the paraphrasing algorithm
3. Adjusting the image handling
4. Changing the cron schedule

## Security

- The script uses prepared statements for database queries
- Input is sanitized before processing
- Error messages are logged but not displayed
- File permissions are set appropriately

## Maintenance

Regular maintenance tasks:
1. Monitor the log files
2. Clean up old images periodically
3. Check for script execution status
4. Update the paraphrasing dictionary as needed 