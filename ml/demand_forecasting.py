"""
Blood Demand Forecasting Module

This module provides functionality to forecast blood demand using historical data.
Uses time series forecasting with Facebook's Prophet library.
"""

import pandas as pd
from prophet import Prophet
from datetime import datetime, timedelta
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class BloodDemandForecaster:
    """Forecasts blood demand based on historical usage patterns."""
    
    def __init__(self, db_conn=None):
        """Initialize the forecaster with a database connection."""
        self.model = None
        self.db_conn = db_conn
        
    def load_historical_data(self, blood_bank_id, blood_type=None, days_back=365):
        """
        Load historical blood usage data from the database.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            blood_type (str, optional): Specific blood type to filter by
            days_back (int): Number of days of historical data to load
            
        Returns:
            pd.DataFrame: DataFrame with columns ['ds' (date), 'y' (usage)]
        """
        query = """
            SELECT 
                DATE(request_date) as ds,
                SUM(quantity_ml) as y
            FROM blood_requests
            WHERE blood_bank_id = %s
              AND request_date >= DATE_SUB(CURDATE(), INTERVAL %s DAY)
              AND status = 'fulfilled'
        """
        params = [blood_bank_id, days_back]
        
        if blood_type:
            query += " AND blood_type = %s"
            params.append(blood_type)
            
        query += " GROUP BY DATE(request_date) ORDER BY ds"
        
        try:
            df = pd.read_sql(query, self.db_conn, params=params)
            return df
        except Exception as e:
            logger.error(f"Error loading historical data: {e}")
            raise
    
    def train_model(self, df):
        """
        Train the Prophet forecasting model.
        
        Args:
            df (pd.DataFrame): Training data with columns ['ds', 'y']
        """
        try:
            # Initialize and fit the model
            self.model = Prophet(
                yearly_seasonality=True,
                weekly_seasonality=True,
                daily_seasonality=False,
                seasonality_mode='multiplicative'
            )
            
            # Add custom holidays that might affect blood donation
            self.model.add_country_holidays(country_name='US')
            
            # Fit the model
            self.model.fit(df)
            
        except Exception as e:
            logger.error(f"Error training model: {e}")
            raise
    
    def forecast_demand(self, periods=30, include_history=True):
        """
        Generate demand forecasts.
        
        Args:
            periods (int): Number of days to forecast
            include_history (bool): Whether to include historical data in the forecast
            
        Returns:
            pd.DataFrame: DataFrame with forecasted values and confidence intervals
        """
        if not self.model:
            raise ValueError("Model has not been trained. Call train_model() first.")
            
        # Create future dates for forecasting
        future = self.model.make_future_dataframe(periods=periods, include_history=include_history)
        
        # Generate forecast
        forecast = self.model.predict(future)
        
        return forecast[['ds', 'yhat', 'yhat_lower', 'yhat_upper']]
    
    def get_forecast_plot(self, forecast):
        """
        Generate a plot of the forecast.
        
        Args:
            forecast (pd.DataFrame): Forecast data from forecast_demand()
            
        Returns:
            matplotlib.figure.Figure: The forecast plot
        """
        if not hasattr(self, 'model'):
            raise ValueError("Model has not been trained. Call train_model() first.")
            
        return self.model.plot(forecast)
    
    def get_forecast_components(self, forecast):
        """
        Get the components of the forecast (trend, weekly, yearly).
        
        Args:
            forecast (pd.DataFrame): Forecast data from forecast_demand()
            
        Returns:
            matplotlib.figure.Figure: The components plot
        """
        if not hasattr(self, 'model'):
            raise ValueError("Model has not been trained. Call train_model() first.")
            
        return self.model.plot_components(forecast)
