# Blood Bank ML Service

This service provides machine learning capabilities for the Blood Bank Management System, including demand prediction and inventory optimization.

## Features

- Blood demand forecasting (7-day and 30-day predictions)
- Inventory optimization
- Expiry prediction
- Donor engagement scoring
- Anomaly detection

## Prerequisites

- Python 3.8+
- MySQL 8.0+
- pip

## Setup

1. Create and activate a virtual environment:
   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows: venv\Scripts\activate
   ```

2. Install dependencies:
   ```bash
   pip install -r requirements.txt
   ```

3. Create a `.env` file in the `ai` directory with your database configuration:
   ```env
   DB_HOST=localhost
   DB_USER=your_username
   DB_PASSWORD=your_password
   DB_NAME=blood_bank
   ```

## Running the Service

### Manual Run
```bash
python run_prediction.py
```

### Scheduled Run (Cron)
Add this to your crontab to run daily at 2 AM:
```
0 2 * * * cd /path/to/ai && /path/to/venv/bin/python run_prediction.py
```

## API Endpoints

### Get Demand Predictions
```
GET /api/predictions/demand?blood_type=A+&days=7
```

### Get Expiry Predictions
```
GET /api/predictions/expiry?blood_type=O+&days=30
```

## Logs

Logs are stored in the `logs/` directory with daily rotation.

## License

MIT
