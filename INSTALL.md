# Installation Guide for AI-Powered Blood Management System

This guide will walk you through setting up and configuring the AI-Powered Blood Management System on your local environment.

## 1. Prerequisites

Before starting, ensure you have the following software installed:

- [XAMPP](https://www.apachefriends.org/index.html) (Version 7.4.x or newer)
- [Web browser](https://www.google.com/chrome/) (Google Chrome, Firefox, etc.)
- [Text editor](https://code.visualstudio.com/) (VS Code, Sublime Text, etc.) for any custom configurations

## 2. Database Setup

1. **Start XAMPP**:
   - Launch XAMPP Control Panel
   - Start the Apache and MySQL services

2. **Create Database**:
   - Open your web browser and navigate to `http://localhost/phpmyadmin`
   - Click on "New" in the left sidebar to create a new database
   - Enter `blood_management` as the database name and click "Create"

3. **Import Database Schema**:
   - Select the `blood_management` database from the left sidebar
   - Click on the "Import" tab
   - Click "Browse" and select the `database.sql` file from the project root
   - Click "Go" to import the schema and initial data

## 3. Configure the Application

1. **Check Database Connection**:
   - Open `includes/db.php` in your text editor
   - Verify that the database connection details match your environment:
     ```php
     $host = "localhost";
     $username = "root"; // Default XAMPP username
     $password = "";     // Default XAMPP password (blank)
     $database = "blood_management";
     ```
   - Save any changes

2. **Configure Application Settings**:
   - Open `includes/config.php` in your text editor
   - Update the `BASE_URL` constant if your installation path is different:
     ```php
     define('BASE_URL', 'http://localhost/fyp/');
     ```
   - Adjust other settings as needed (e.g., admin email, time zone)
   - Save any changes

## 4. Set Up the AI Monitoring System

### For Windows:

1. **Configure Task Scheduler**:
   - Open Windows Task Scheduler
   - Click "Create Basic Task"
   - Name it "Blood Management AI Monitor" and click "Next"
   - Select "Daily" and click "Next"
   - Set the start time and click "Next"
   - Select "Start a program" and click "Next"
   - In the "Program/script" field, enter: `C:\xampp\php\php.exe`
   - In the "Add arguments" field, enter: `C:\xampp\htdocs\fyp\ai\cron.php`
   - Click "Next" and then "Finish"
   - Right-click on the created task and select "Properties"
   - On the "Triggers" tab, edit the trigger to repeat every 1 hour for a duration of 1 day
   - Click "OK" to save changes

### For Linux/macOS:

1. **Set up a Cron Job**:
   - Open Terminal
   - Enter `crontab -e` to edit your cron jobs
   - Add the following line to run the script hourly:
     ```
     0 * * * * /usr/bin/php /path/to/xampp/htdocs/fyp/ai/cron.php
     ```
   - Save and exit the editor

## 5. Accessing the System

1. **Open the Application**:
   - Launch your web browser
   - Navigate to `http://localhost/fyp/`
   - You should see the landing page of the Blood Management System

2. **Default Admin Access**:
   - Username: `admin`
   - Password: `admin123`
   - **Important**: Change this password immediately after first login

## 6. Post-Installation Steps

1. **Create Initial Accounts**:
   - Register at least one blood bank account
   - Register at least one hospital account
   - Register several donor accounts with different blood types

2. **Configure Threshold Settings**:
   - Log in as admin
   - Navigate to Settings
   - Configure default threshold values for different blood types
   - These thresholds determine when the AI will trigger notifications and requests

3. **Test the AI System**:
   - Manually run the AI monitoring script to test:
     ```
     php c:\xampp\htdocs\fyp\ai\cron.php
     ```
   - Check for any error messages in the console or log files

## 7. Troubleshooting

### Database Connection Issues

- Ensure MySQL service is running in XAMPP Control Panel
- Verify database credentials in `includes/db.php`
- Check if the `blood_management` database exists

### Permission Issues

- Ensure the web server has read/write permissions to the project directory
- For Linux/macOS: `chmod -R 755 /path/to/fyp`

### AI Monitoring Issues

- Check the log files at `ai/ai_errors.log` and `ai/ai_execution.log`
- Verify that PHP can be executed from the command line
- Ensure the cron job or scheduled task is set up correctly

## 8. Security Recommendations

1. **Change Default Credentials**:
   - Immediately change the default admin password
   - Consider creating a new admin account and disabling the default one

2. **Enable HTTPS**:
   - For production environments, secure your site with an SSL certificate
   - Update the `BASE_URL` in `config.php` to use `https://`

3. **Regular Backups**:
   - Set up regular database backups using phpMyAdmin or automated scripts

## Support

For additional support or to report issues:
- Check the README.md file for more information
- Create an issue on the project repository
- Contact system administrator at the provided email

---

Thank you for installing the AI-Powered Blood Management System! This platform will help streamline blood donation management and ensure timely distribution where needed most.
