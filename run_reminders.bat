@echo off
echo Starting Reminder Creation Process...

:: Set the base directory (where the batch file is located)
set "BASE_DIR=%~dp0"
cd /d "%BASE_DIR%"

:: Log start time
echo %date% %time% - Process Started >> "%BASE_DIR%reminder_log.txt"

:: Run the PHP script with full path
php "%BASE_DIR%src\pages\create_appointment_reminders.php" >> "%BASE_DIR%reminder_log.txt" 2>&1

:: Log completion time
echo %date% %time% - Process Completed >> "%BASE_DIR%reminder_log.txt"
echo Reminder Creation Process Completed.