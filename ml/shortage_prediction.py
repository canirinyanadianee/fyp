"""
Blood Shortage Prediction Module

This module predicts potential blood shortages based on current inventory,
historical usage patterns, and upcoming demand forecasts.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class ShortagePredictor:
    """Predicts potential blood shortages based on inventory and usage patterns."""
    
    def __init__(self, db_conn=None):
        """Initialize the predictor with a database connection."""
        self.db_conn = db_conn
        self.safety_stock_levels = {
            'A+': 2000,  # ml
            'A-': 1000,
            'B+': 1500,
            'B-': 800,
            'AB+': 1000,
            'AB-': 500,
            'O+': 2500,
            'O-': 1500
        }
    
    def get_current_inventory(self, blood_bank_id, blood_type=None):
        """
        Get current blood inventory levels.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            blood_type (str, optional): Specific blood type to filter by
            
        Returns:
            dict: Dictionary of blood types and their current quantities (ml)
        """
        query = """
            SELECT blood_type, SUM(quantity_ml) as total_quantity
            FROM blood_inventory
            WHERE blood_bank_id = %s
              AND status = 'available'
              AND expiry_date > CURDATE()
        """
        
        params = [blood_bank_id]
        
        if blood_type:
            query += " AND blood_type = %s"
            params.append(blood_type)
            
        query += " GROUP BY blood_type"
        
        try:
            df = pd.read_sql(query, self.db_conn, params=params)
            return dict(zip(df['blood_type'], df['total_quantity']))
        except Exception as e:
            logger.error(f"Error getting current inventory: {e}")
            raise
    
    def get_avg_daily_usage(self, blood_bank_id, days=30):
        """
        Calculate average daily blood usage.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            days (int): Number of days to look back for calculating average
            
        Returns:
            dict: Dictionary of blood types and their average daily usage (ml)
        """
        query = """
            SELECT 
                blood_type,
                SUM(quantity_ml) / %s as avg_daily_usage
            FROM blood_requests
            WHERE blood_bank_id = %s
              AND request_date >= DATE_SUB(CURDATE(), INTERVAL %s DAY)
              AND status = 'fulfilled'
            GROUP BY blood_type
        """
        
        try:
            df = pd.read_sql(query, self.db_conn, params=[days, blood_bank_id, days])
            return dict(zip(df['blood_type'], df['avg_daily_usage']))
        except Exception as e:
            logger.error(f"Error calculating average daily usage: {e}")
            raise
    
    def predict_shortages(self, blood_bank_id, forecast_days=7):
        """
        Predict potential blood shortages in the coming days.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            forecast_days (int): Number of days to forecast
            
        Returns:
            list: List of dictionaries with shortage predictions
        """
        try:
            # Get current inventory
            inventory = self.get_current_inventory(blood_bank_id)
            
            # Get average daily usage
            avg_daily_usage = self.get_avg_daily_usage(blood_bank_id)
            
            # Calculate days of supply remaining
            shortages = []
            
            for blood_type, current_qty in inventory.items():
                # Skip if we don't have usage data for this blood type
                if blood_type not in avg_daily_usage or avg_daily_usage[blood_type] <= 0:
                    continue
                
                # Calculate days of supply remaining
                days_of_supply = current_qty / avg_daily_usage[blood_type]
                
                # Get safety stock level
                safety_stock = self.safety_stock_levels.get(blood_type, 1000)  # Default to 1000ml if not found
                
                # Calculate days until safety stock is reached
                days_to_safety_stock = (current_qty - safety_stock) / avg_daily_usage[blood_type] \
                    if current_qty > safety_stock else 0
                
                # Determine shortage risk level
                if days_of_supply <= 2:
                    risk = 'Critical'
                elif days_of_supply <= 5:
                    risk = 'High'
                elif days_of_supply <= 10:
                    risk = 'Medium'
                else:
                    risk = 'Low'
                
                shortages.append({
                    'blood_type': blood_type,
                    'current_inventory_ml': current_qty,
                    'safety_stock_ml': safety_stock,
                    'avg_daily_usage_ml': avg_daily_usage[blood_type],
                    'days_of_supply': round(days_of_supply, 1),
                    'days_to_safety_stock': round(days_to_safety_stock, 1) if days_to_safety_stock > 0 else 0,
                    'risk_level': risk,
                    'projected_shortage_date': (datetime.now() + timedelta(days=days_to_safety_stock)).strftime('%Y-%m-%d') \
                        if days_to_safety_stock > 0 else None
                })
            
            # Sort by risk level (Critical, High, Medium, Low)
            risk_order = {'Critical': 0, 'High': 1, 'Medium': 2, 'Low': 3}
            shortages.sort(key=lambda x: risk_order[x['risk_level']])
            
            return shortages
            
        except Exception as e:
            logger.error(f"Error predicting shortages: {e}")
            raise
    
    def get_shortage_alerts(self, blood_bank_id, threshold_days=7):
        """
        Get alerts for potential shortages within the threshold days.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            threshold_days (int): Number of days to consider for alerts
            
        Returns:
            list: List of alert messages
        """
        try:
            shortages = self.predict_shortages(blood_bank_id, threshold_days)
            alerts = []
            
            for shortage in shortages:
                if shortage['days_to_safety_stock'] <= threshold_days and shortage['days_to_safety_stock'] > 0:
                    alerts.append({
                        'blood_type': shortage['blood_type'],
                        'message': f"{shortage['blood_type']} may reach safety stock in {shortage['days_to_safety_stock']:.1f} days ({shortage['projected_shortage_date']})",
                        'risk_level': shortage['risk_level'],
                        'days_remaining': shortage['days_to_safety_stock']
                    })
            
            # Sort by days remaining (ascending)
            alerts.sort(key=lambda x: x['days_remaining'])
            
            return alerts
            
        except Exception as e:
            logger.error(f"Error generating shortage alerts: {e}")
            raise
