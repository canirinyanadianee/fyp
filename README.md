# AI-Powered Blood Management System

An intelligent system for managing blood inventory, connecting donors with blood banks and hospitals, and automating blood supply management through AI.

## Overview

The AI-Powered Blood Management System is a comprehensive solution designed to:

1. Connect blood donors with blood banks
2. Manage blood inventory at blood banks and hospitals
3. Automate blood transfer requests based on inventory levels
4. Use AI to monitor blood levels and notify stakeholders when action is needed
5. Learn from usage patterns to optimize blood distribution

## Features

### Role-Based Access

- **Donors**: Register, view donation history and appointments, receive AI-powered notifications when their blood type is needed, check eligibility status
- **Blood Banks**: Manage blood inventory, process donations, approve hospital transfer requests
- **Hospitals**: Manage blood sub-inventory, request blood from blood banks, record blood usage
- **Admin**: Oversee system operations, manage users, view reports

### AI Capabilities

- **Automated Monitoring**: Continuously checks blood inventory levels at hospitals and blood banks
- **Smart Notifications**: Sends targeted notifications to donors when specific blood types are critically low
- **Pattern Recognition**: Learns from blood usage patterns to predict future needs
- **Automatic Requests**: Creates blood transfer requests when hospital inventory falls below thresholds

## Setup Instructions

### Prerequisites

- XAMPP (or similar stack with PHP 7.4+ and MySQL/MariaDB)
- Web browser
- Internet connection (for Bootstrap and other CDN resources)

### Installation Steps

1. **Set up the database**:
   - Start XAMPP and ensure Apache and MySQL services are running
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `blood_management`
   - Import the `database.sql` file from the root directory

2. **Configure the application**:
   - Navigate to `includes/config.php` and update settings if necessary
   - Ensure the database connection details in `includes/db.php` match your environment

3. **Set up the AI monitoring cron job**:
   - For Windows:
     - Open Task Scheduler
     - Create a new task to run `php c:\xampp\htdocs\fyp\ai\cron.php` every hour
   - For Linux:
     - Edit crontab: `crontab -e`
     - Add: `0 * * * * php /path/to/fyp/ai/cron.php`

4. **Access the application**:
   - Open your web browser and navigate to `http://localhost/fyp/`
   - Use the default admin credentials:
     - Username: admin
     - Password: admin123

## System Architecture

### Directory Structure

```
fyp/
├── admin/             # Admin portal
├── ai/                # AI monitoring system
├── assets/            # CSS, JS, images
│   ├── css/
│   ├── js/
│   └── img/
├── bloodbank/         # Blood bank portal
├── donor/             # Donor portal
├── hospital/          # Hospital portal
├── includes/          # Shared PHP files
└── database.sql       # Database schema
```

### Database Schema

The database consists of several key tables:

- **users**: Authentication and role management
- **donors**: Donor profiles and health information
- **blood_banks**: Blood bank information
- **hospitals**: Hospital information
- **blood_inventory**: Main blood stock at blood banks
- **hospital_blood_inventory**: Sub-stock at hospitals
- **blood_donations**: Record of donations
- **blood_transfers**: Blood transfers between banks and hospitals
- **blood_usage**: Blood usage records at hospitals
- **ai_notifications**: System generated notifications
- **ai_learning_data**: Data collected for AI pattern recognition
- **threshold_settings**: Inventory threshold configurations

## Usage Guide

### For Donors

1. Register as a donor and provide your blood type and health information
2. Receive notifications when your blood type is needed
3. Visit a blood bank to donate blood
4. View your donation history and impact statistics

### For Blood Banks

1. Register your blood bank with license and location details
2. Record blood donations from donors
3. Manage blood inventory by type and expiry date
4. Process transfer requests from hospitals
5. View AI-generated insights about donation patterns

### For Hospitals

1. Register your hospital with license and location details
2. Manage your blood inventory
3. Request blood from connected blood banks
4. Record blood usage for patients
5. View AI-generated insights about usage patterns

## AI System

The AI component:

1. Runs on a scheduled basis (typically hourly)
2. Analyzes current inventory levels against defined thresholds
3. Creates automatic transfer requests when hospital levels are low
4. Notifies relevant donors when blood bank levels are critically low
5. Learns from usage and donation patterns to make recommendations

### Machine Learning Capabilities

Machine learning enhances the Blood Management System (BMS) by providing predictive, personalized, and anomaly-detection features that improve supply reliability and operational efficiency:

- Predicting blood shortages based on regional and historical trends
   - Use time-series and demand forecasting models to identify likely future shortages so the system can trigger transfers or donor outreach ahead of time.
- Recommending the most suitable donors by location and blood group
   - Match available donors to requests using geospatial routing, donor availability, and compatibility scoring to speed up fulfilment.
- Automating donor eligibility checks
   - Apply classification rules and learned models on donor health and donation history to flag eligible donors and reduce manual screening.
- Detecting abnormal request patterns or demand surges
   - Use anomaly detection on request streams to surface unusual spikes or potential abuse for human review.
- Forecasting future inventory needs for planning
   - Combine seasonality, procedure schedules, and historical usage to forecast inventory needs and suggest procurement plans.

These ML capabilities are intended to work alongside rule-based automation and human oversight, improving response times and reducing wastage while keeping safety and privacy central.
## Security Considerations

- All passwords are hashed using PHP's password_hash() function
- Role-based access control limits what users can see and do
- Input sanitization is implemented for all user inputs
- Session management includes timeout and secure cookie settings

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Acknowledgements

- Bootstrap for the responsive UI components
- Chart.js for data visualization
- FontAwesome for icons
- The open-source community for inspiration and resources























Key Components
User Portals
Donor Portal: Registration, appointment scheduling, donation history
Blood Bank Portal: Inventory management, donation processing
Hospital Portal: Blood requests, usage tracking
Admin Portal: System oversight, user management
AI/ML Features
Predictive inventory management
Automated donor notifications
Usage pattern analysis
Smart blood transfer recommendations
Core Functionality
Real-time blood inventory tracking
Automated transfer requests
Donor matching
Usage analytics and reporting
Technical Stack
Frontend: HTML, CSS, JavaScript, Bootstrap
Backend: PHP
Database: MySQL
AI/ML: Python (in /ai directory)
Server: XAMPP stack
Key Files/Directories
/admin - Admin interface
/ai - AI/ML components
/bloodbank - Blood bank interface
/donor - Donor interface
/hospital - Hospital interface
/includes - Shared PHP functions
database.sql
 - Database schema
Notable Features
Role-based access control
Automated blood level monitoring
Intelligent notification system
Data-driven decision making
Comprehensive reporting
Setup
XAMPP stack required
Import 
database.sql
Configure database connection
Set up cron job for AI monitoring






















Role of Machine Learning in a Blood Bank
1. Blood Demand & Supply Prediction

ML analyzes historical data (donations, hospital requests, emergencies, seasons).

Predicts which blood types will be needed most in upcoming weeks/months.

Helps blood banks stock the right amount → avoid shortages & wastage.

2. Expiry & Wastage Reduction

ML models can track shelf life of each blood unit.

Predict which units are at risk of expiry and suggest where to send them first (e.g., to a hospital with higher demand).

Optimizes blood circulation (“First Expire, First Out”).

3. Donor Management & Retention

ML can predict when a donor is most likely to donate again.

Personalized reminders (SMS/email) at the right time.

Identifies donors who are becoming inactive and suggests outreach campaigns.

4. Fraud & Anomaly Detection

Detects suspicious activities, like:

Duplicate donor registrations.

False blood requests.

Abnormal inventory records.

Improves trust and security of the blood bank system.

5. Smart Matching of Blood Units

ML algorithms can quickly match the right blood type and Rh factor to hospital requests.

Takes into account:

Patient urgency

Blood compatibility rules

Geographic proximity of stock

Reduces time in finding safe blood for patients.

6. Disease Screening Assistance

ML can assist in analyzing test results of donated blood (HIV, Hepatitis, Malaria).

Helps flag suspicious patterns for further lab review.

Improves safety by reducing chances of unsafe blood entering stock.






🔹 Role of Machine Learning in Hospitals
1. Predicting Blood Demand

ML analyzes patient records, surgery schedules, accident trends, and seasonal patterns.

Predicts which blood types will be required in the near future.

Helps hospitals prepare stock in advance and avoid emergencies.

2. Real-Time Matching of Donors and Patients

When a patient needs blood, ML can quickly:

Check hospital inventory.

Recommend the best blood bank to supply the required unit.

Match compatible donors (considering blood group, Rh factor, medical history).

3. Optimizing Blood Requests

ML helps decide how much blood to request from the blood bank (not too much to avoid wastage, not too little to risk shortages).

Prioritizes requests based on urgency (e.g., accident victims vs. scheduled surgery).

4. Critical Shortage Alerts

ML models can monitor stock levels in hospitals.

Send early alerts when certain blood types are running low.

Suggest transfer from nearby hospitals or blood banks before a crisis.

5. Clinical Decision Support

During emergencies, ML can assist doctors by:

Recommending alternative blood types when the exact type isn’t available (e.g., O- for emergencies).

Providing transfusion safety guidelines.

Flagging high-risk patients (e.g., those needing rare blood).

6. Improving Patient Care & Safety

Tracks patient transfusion history.

Alerts doctors if a patient is at risk of transfusion reactions.

Recommends best practices for dosage and timing based on past cases.#   f y p  
 