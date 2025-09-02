import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
from sklearn.metrics import mean_absolute_error, r2_score
import joblib
import os
import mysql.connector
from dotenv import load_dotenv
import logging

# Set up logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    filename='../logs/ml_service.log'
)
logger = logging.getLogger(__name__)

class BloodDemandPredictor:
    def __init__(self):
        self.model = None
        self.model_path = 'models/demand_model.joblib'
        self.db_config = self._load_db_config()
        os.makedirs('models', exist_ok=True)
        
    def _load_db_config(self):
        """Load database configuration from environment variables"""
        load_dotenv()
        return {
            'host': os.getenv('DB_HOST', 'localhost'),
            'user': os.getenv('DB_USER', 'root'),
            'password': os.getenv('DB_PASSWORD', ''),
            'database': os.getenv('DB_NAME', 'blood_bank')
        }
    
    def get_db_connection(self):
        """Create database connection"""
        try:
            return mysql.connector.connect(**self.db_config)
        except Exception as e:
            logger.error(f"Database connection failed: {str(e)}")
            raise
    
    def load_training_data(self):
        """Load historical demand data from database"""
        query = """
        SELECT 
            date,
            blood_type,
            requested_units,
            is_emergency,
            season,
            DAYOFWEEK(date) as day_of_week,
            MONTH(date) as month,
            (DAYOFYEAR(date) / 365 * 2 * PI()) as day_of_year_rad
        FROM ml_blood_demand_data
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR)
        """
        
        try:
            conn = self.get_db_connection()
            df = pd.read_sql(query, conn)
            conn.close()
            
            if df.empty:
                logger.warning("No training data found in the database")
                return None
                
            # Feature engineering
            df = pd.get_dummies(df, columns=['blood_type', 'season', 'day_of_week', 'month'])
            
            # Split features and target
            X = df.drop(['date', 'requested_units'], axis=1)
            y = df['requested_units']
            
            return X, y
            
        except Exception as e:
            logger.error(f"Error loading training data: {str(e)}")
            return None, None
    
    def train_model(self):
        """Train the demand prediction model"""
        X, y = self.load_training_data()
        
        if X is None or y is None:
            logger.error("No data available for training")
            return False
            
        # Split data
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=0.2, random_state=42
        )
        
        # Train model
        self.model = RandomForestRegressor(
            n_estimators=100,
            max_depth=10,
            random_state=42,
            n_jobs=-1
        )
        
        self.model.fit(X_train, y_train)
        
        # Evaluate
        y_pred = self.model.predict(X_test)
        mae = mean_absolute_error(y_test, y_pred)
        r2 = r2_score(y_test, y_pred)
        
        logger.info(f"Model trained - MAE: {mae:.2f}, R²: {r2:.2f}")
        
        # Save model
        joblib.dump(self.model, self.model_path)
        logger.info(f"Model saved to {self.model_path}")
        
        return True
    
    def predict_demand(self, days_ahead=30):
        """Predict blood demand for future dates"""
        if not os.path.exists(self.model_path):
            logger.warning("Model not found, training a new one")
            if not self.train_model():
                return None
        
        # Load the model
        self.model = joblib.load(self.model_path)
        
        # Generate future dates
        future_dates = pd.date_range(
            start=datetime.now().date(),
            end=datetime.now().date() + timedelta(days=days_ahead)
        )
        
        # Create feature matrix for prediction
        blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
        predictions = []
        
        for date in future_dates:
            for blood_type in blood_types:
                # Create features for prediction
                features = {
                    'is_emergency': [0],  # Default to non-emergency
                    'day_of_week': [date.weekday() + 1],
                    'month': [date.month],
                    'day_of_year_rad': [date.timetuple().tm_yday / 365 * 2 * 3.14159]
                }
                
                # Add one-hot encoded blood types
                for bt in blood_types:
                    features[f'blood_type_{bt}'] = [1 if bt == blood_type else 0]
                
                # Add seasons (simplified for demonstration)
                if date.month in [12, 1, 2]:
                    season = 'Winter'
                elif date.month in [3, 4, 5]:
                    season = 'Spring'
                elif date.month in [6, 7, 8]:
                    season = 'Summer'
                else:
                    season = 'Fall'
                
                for s in ['Winter', 'Spring', 'Summer', 'Fall']:
                    features[f'season_{s}'] = [1 if s == season else 0]
                
                # Make prediction
                df = pd.DataFrame(features)
                predicted_units = self.model.predict(df)[0]
                
                predictions.append({
                    'date': date,
                    'blood_type': blood_type,
                    'predicted_units': max(0, predicted_units)  # Ensure non-negative
                })
        
        return pd.DataFrame(predictions)
    
    def save_predictions_to_db(self, predictions):
        """Save predictions to database"""
        if predictions is None or predictions.empty:
            logger.warning("No predictions to save")
            return False
            
        try:
            conn = self.get_db_connection()
            cursor = conn.cursor()
            
            # Group by date and blood type for 7-day and 30-day predictions
            today = datetime.now().date()
            
            for blood_type in predictions['blood_type'].unique():
                # 7-day prediction (next 7 days)
                mask_7d = (
                    (predictions['blood_type'] == blood_type) &
                    (predictions['date'] <= today + timedelta(days=7))
                )
                pred_7d = predictions[mask_7d]['predicted_units'].sum()
                
                # 30-day prediction (next 30 days)
                mask_30d = (
                    (predictions['blood_type'] == blood_type) &
                    (predictions['date'] <= today + timedelta(days=30))
                )
                pred_30d = predictions[mask_30d]['predicted_units'].sum()
                
                # Save to database
                query = """
                INSERT INTO ml_demand_predictions 
                (prediction_date, blood_type, predicted_demand_7d, predicted_demand_30d, confidence)
                VALUES (%s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE
                    predicted_demand_7d = VALUES(predicted_demand_7d),
                    predicted_demand_30d = VALUES(predicted_demand_30d),
                    confidence = VALUES(confidence)
                """
                
                cursor.execute(query, (
                    today,
                    blood_type,
                    int(round(pred_7d)),
                    int(round(pred_30d)),
                    0.85  # Placeholder confidence score
                ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            logger.info(f"Saved predictions to database for {len(predictions)} records")
            return True
            
        except Exception as e:
            logger.error(f"Error saving predictions to database: {str(e)}")
            return False

if __name__ == "__main__":
    logger.info("Starting demand prediction job")
    predictor = BloodDemandPredictor()
    
    # Train or load model
    if not os.path.exists(predictor.model_path):
        logger.info("Training new demand prediction model")
        predictor.train_model()
    
    # Generate and save predictions
    logger.info("Generating demand predictions")
    predictions = predictor.predict_demand(days_ahead=30)
    
    if predictions is not None:
        logger.info(f"Generated predictions for {len(predictions)} date/blood type combinations")
        success = predictor.save_predictions_to_db(predictions)
        if success:
            logger.info("Successfully saved predictions to database")
        else:
            logger.error("Failed to save predictions to database")
    else:
        logger.error("No predictions were generated")
