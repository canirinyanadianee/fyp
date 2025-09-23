"""
Blood Storage Optimization Module

This module provides recommendations for optimizing blood storage
based on usage patterns, expiration dates, and demand forecasts.
"""

import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import logging

# Set up logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

class StorageOptimizer:
    """Provides recommendations for optimizing blood storage."""
    
    def __init__(self, db_conn=None):
        """Initialize the optimizer with a database connection."""
        self.db_conn = db_conn
        self.blood_shelf_life = 42  # days (6 weeks for red blood cells)
    
    def get_inventory_aging(self, blood_bank_id):
        """
        Get inventory aging information.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            
        Returns:
            pd.DataFrame: DataFrame with inventory aging information
        """
        query = """
            SELECT 
                id,
                blood_type,
                quantity_ml,
                collection_date,
                expiry_date,
                DATEDIFF(expiry_date, CURDATE()) as days_until_expiry,
                DATEDIFF(CURDATE(), collection_date) as days_in_storage,
                quantity_ml * (DATEDIFF(expiry_date, CURDATE()) / %s) as weighted_quantity
            FROM blood_inventory
            WHERE blood_bank_id = %s
              AND status = 'available'
              AND expiry_date > CURDATE()
            ORDER BY days_until_expiry ASC, collection_date ASC
        """
        
        try:
            df = pd.read_sql(query, self.db_conn, params=[self.blood_shelf_life, blood_bank_id])
            return df
        except Exception as e:
            logger.error(f"Error getting inventory aging data: {e}")
            raise
    
    def get_optimization_recommendations(self, blood_bank_id):
        """
        Generate storage optimization recommendations.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            
        Returns:
            dict: Dictionary of recommendations by blood type
        """
        try:
            # Get inventory aging data
            inventory = self.get_inventory_aging(blood_bank_id)
            
            if inventory.empty:
                return {
                    'recommendations': [],
                    'summary': 'No inventory data available for optimization.'
                }
            
            # Group by blood type
            recommendations = {}
            
            for blood_type, group in inventory.groupby('blood_type'):
                # Sort by days until expiry (ascending)
                group = group.sort_values('days_until_expiry')
                
                # Calculate total quantity and weighted quantity
                total_quantity = group['quantity_ml'].sum()
                weighted_quantity = group['weighted_quantity'].sum()
                
                # Calculate average days until expiry
                avg_days_until_expiry = (group['days_until_expiry'] * group['quantity_ml']).sum() / total_quantity
                
                # Calculate utilization score (higher is better)
                utilization_score = min(weighted_quantity / total_quantity * 100, 100)
                
                # Generate recommendations
                type_recommendations = []
                
                # Check for soon-to-expire units
                expiring_soon = group[group['days_until_expiry'] <= 7]
                if not expiring_soon.empty:
                    total_expiring = expiring_soon['quantity_ml'].sum()
                    type_recommendations.append({
                        'type': 'warning',
                        'message': f"{total_expiring}ml of {blood_type} will expire in the next 7 days. Consider prioritizing its use.",
                        'priority': 'high',
                        'action': 'use_soon',
                        'items': expiring_soon[['id', 'quantity_ml', 'days_until_expiry']].to_dict('records')
                    })
                
                # Check for overstocked blood types
                if total_quantity > 10000:  # Example threshold, adjust as needed
                    type_recommendations.append({
                        'type': 'info',
                        'message': f"High inventory of {blood_type} ({total_ml}ml). Consider redistributing to other blood banks if possible.",
                        'priority': 'medium',
                        'action': 'redistribute'
                    })
                
                # Add general optimization suggestions
                if utilization_score < 50:
                    type_recommendations.append({
                        'type': 'suggestion',
                        'message': f"Low utilization score ({utilization_score:.1f}%) for {blood_type}. Consider adjusting collection schedule.",
                        'priority': 'low',
                        'action': 'adjust_schedule'
                    })
                
                # Store recommendations for this blood type
                recommendations[blood_type] = {
                    'total_quantity_ml': total_quantity,
                    'weighted_quantity_ml': weighted_quantity,
                    'utilization_score': utilization_score,
                    'avg_days_until_expiry': avg_days_until_expiry,
                    'recommendations': type_recommendations
                }
            
            # Generate summary
            summary = f"Generated {sum(len(rec['recommendations']) for rec in recommendations.values())} " \
                     f"recommendations across {len(recommendations)} blood types."
            
            return {
                'blood_types': recommendations,
                'summary': summary,
                'timestamp': datetime.now().isoformat()
            }
            
        except Exception as e:
            logger.error(f"Error generating optimization recommendations: {e}")
            raise
    
    def get_ideal_inventory_levels(self, blood_bank_id, days=30):
        """
        Calculate ideal inventory levels based on historical usage.
        
        Args:
            blood_bank_id (int): ID of the blood bank
            days (int): Number of days of historical data to consider
            
        Returns:
            dict: Dictionary of ideal inventory levels by blood type
        """
        try:
            # Get historical usage data
            query = """
                SELECT 
                    blood_type,
                    AVG(daily_usage) as avg_daily_usage,
                    STDDEV(daily_usage) as std_daily_usage,
                    MAX(daily_usage) as max_daily_usage,
                    COUNT(*) as days_data
                FROM (
                    SELECT 
                        blood_type,
                        DATE(request_date) as day,
                        SUM(quantity_ml) as daily_usage
                    FROM blood_requests
                    WHERE blood_bank_id = %s
                      AND request_date >= DATE_SUB(CURDATE(), INTERVAL %s DAY)
                      AND status = 'fulfilled'
                    GROUP BY blood_type, DATE(request_date)
                ) as daily_usage
                GROUP BY blood_type
            """
            
            df = pd.read_sql(query, self.db_conn, params=[blood_bank_id, days])
            
            if df.empty:
                return {}
            
            # Calculate ideal inventory levels (average daily usage * lead time + safety stock)
            # Using 1.5 * standard deviation as safety stock for 93% service level
            ideal_levels = {}
            
            for _, row in df.iterrows():
                lead_time = 7  # days (time to get new supply)
                safety_stock = 1.5 * (row['std_daily_usage'] or 0)
                
                ideal_levels[row['blood_type']] = {
                    'min_ideal_ml': max(1000, (row['avg_daily_usage'] * lead_time) + safety_stock),
                    'max_ideal_ml': max(2000, (row['avg_daily_usage'] * (lead_time + 7)) + (2 * safety_stock)),
                    'current_avg_daily_usage': row['avg_daily_usage'],
                    'safety_stock_ml': safety_stock,
                    'days_data': row['days_data']
                }
            
            return ideal_levels
            
        except Exception as e:
            logger.error(f"Error calculating ideal inventory levels: {e}")
            raise
