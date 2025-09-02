from flask import Flask, jsonify, request
import mysql.connector
import numpy as np
import pandas as pd
from datetime import datetime, timedelta
import json
import logging
import time
from mysql.connector import pooling

app = Flask(__name__)

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'blood_management',
    'raise_on_warnings': True,
    'connect_timeout': 5,
    'connection_retries': 3
}

# Configure logging
logging.basicConfig(
    filename='ai_service.log',
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

db_pool = None

def init_db_pool():
    """Initialize database connection pool"""
    global db_pool
    try:
        db_pool = pooling.MySQLConnectionPool(
            pool_name="ai_pool",
            pool_size=5,
            **DB_CONFIG
        )
        logger.info("Database connection pool initialized")
    except Exception as e:
        logger.error(f"Failed to initialize database connection pool: {e}")
        raise

# Initialize the connection pool when the module loads
init_db_pool()

def get_db_connection():
    """Get a connection from the pool"""
    try:
        conn = db_pool.get_connection()
        logger.debug("Successfully got database connection from pool")
        return conn
    except Exception as e:
        logger.error(f"Failed to get database connection from pool: {e}")
        return None

def execute_query(query, params=None, fetch_all=True):
    """Execute a database query with proper error handling"""
    conn = None
    cursor = None
    try:
        conn = get_db_connection()
        if not conn:
            logger.error("Database connection is None")
            return None
            
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params or ())
        
        if fetch_all:
            result = cursor.fetchall()
        else:
            result = cursor.fetchone()
            
        conn.commit()
        return result
        
    except mysql.connector.Error as err:
        logger.error(f"Database error: {err}")
        if conn:
            conn.rollback()
        return None
        
    finally:
        if cursor:
            cursor.close()
        if conn and conn.is_connected():
            conn.close()

@app.route('/api/blood-predictions', methods=['GET'])
def get_blood_predictions():
    """Get AI blood usage predictions"""
    try:
        # Get historical usage data (last 30 days)
        query = """
        SELECT blood_type, 
               SUM(quantity_ml) as total_usage,
               DATE(created_at) as usage_date
        FROM blood_donations 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY blood_type, DATE(created_at)
        ORDER BY usage_date DESC
        """
        
        historical_data = execute_query(query)
        
        # Generate predictions for each blood type
        blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
        predictions = []
        
        for blood_type in blood_types:
            # Simple prediction algorithm (can be enhanced with ML)
            type_data = [row['total_usage'] for row in historical_data if row['blood_type'] == blood_type]
            
            if type_data:
                avg_usage = np.mean(type_data)
                # Add seasonal and trend factors
                predicted_usage = avg_usage * 1.1  # 10% increase factor
                confidence = min(72 + len(type_data) * 2, 95)  # Confidence based on data points
            else:
                # Default predictions for blood types with no data
                default_usage = {'A+': 3854, 'A-': 4275, 'B+': 3655, 'B-': 3347, 
                               'AB+': 3545, 'AB-': 3149, 'O+': 3325, 'O-': 2491}
                predicted_usage = default_usage.get(blood_type, 3000)
                confidence = 72
            
            # Get current stock
            stock_query = """
            SELECT COALESCE(SUM(quantity_ml), 0) as current_stock
            FROM blood_inventory 
            WHERE blood_type = %s AND expiry_date > NOW()
            """
            stock_result = execute_query(stock_query, (blood_type,), fetch_all=False)
            current_stock = stock_result['current_stock'] if stock_result else 0
            
            # Determine status
            if current_stock < predicted_usage * 0.5:
                status = "Shortage Predicted"
            elif current_stock < predicted_usage:
                status = "Low Stock"
            else:
                status = "Adequate"
            
            predictions.append({
                'blood_type': blood_type,
                'current_stock': f"{current_stock} ml",
                'predicted_usage': f"{int(predicted_usage):,} ml",
                'status': status,
                'confidence': f"{confidence}%",
                'confidence_level': confidence
            })
        
        return jsonify({
            'success': True,
            'predictions': predictions,
            'generated_at': datetime.now().isoformat()
        })
        
    except Exception as e:
        logger.error(f"Prediction error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/demand-analysis', methods=['GET'])
def get_demand_analysis():
    """Get blood demand analysis"""
    try:
        # Get demand levels by blood type
        demand_query = """
        SELECT blood_type,
               COUNT(*) as request_count,
               SUM(quantity_ml) as total_requested
        FROM blood_requests 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY blood_type
        """
        
        demand_data = execute_query(demand_query)
        
        # Calculate demand levels
        demand_levels = []
        for row in demand_data:
            blood_type = row['blood_type']
            request_count = row['request_count']
            
            if request_count >= 10:
                level = "High"
            elif request_count >= 5:
                level = "Medium"
            else:
                level = "Low"
            
            demand_levels.append({
                'blood_type': blood_type,
                'demand_level': level,
                'request_count': request_count,
                'total_requested': row['total_requested']
            })
        
        return jsonify({
            'success': True,
            'demand_levels': demand_levels
        })
        
    except Exception as e:
        logger.error(f"Demand analysis error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/api/donation-insights', methods=['GET'])
def get_donation_insights():
    """Get donation insights for donors"""
    try:
        donor_id = request.args.get('donor_id')
        if not donor_id:
            return jsonify({'error': 'Donor ID required'}), 400
        
        # Get donor's blood type
        query = "SELECT blood_type FROM donors WHERE id = %s"
        donor_result = execute_query(query, (donor_id,), fetch_all=False)
        
        if not donor_result:
            return jsonify({'error': 'Donor not found'}), 404
        
        blood_type = donor_result['blood_type']
        
        # Get demand for this blood type
        demand_query = """
        SELECT COUNT(*) as requests,
               COALESCE(SUM(quantity_ml), 0) as total_needed
        FROM blood_requests 
        WHERE blood_type = %s AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        """
        demand_result = execute_query(demand_query, (blood_type,), fetch_all=False)
        
        # Determine demand level
        request_count = demand_result['requests']
        if request_count >= 10:
            demand_level = "High"
        elif request_count >= 5:
            demand_level = "Medium"
        else:
            demand_level = "Low"
        
        # Optimal donation times (mock AI suggestion)
        optimal_times = [
            "Wednesday Morning",
            "Saturday Afternoon", 
            "Monday Evening"
        ]
        
        insights = {
            'blood_type_impact': f"Your {blood_type} blood is currently in {demand_level.lower()} demand. Donations can help save lives!",
            'current_demand': demand_level,
            'optimal_times': optimal_times,
            'impact_message': f"Your {blood_type} blood is currently well-stocked in your area." if demand_level == "Low" else f"Your {blood_type} blood is urgently needed!"
        }
        
        return jsonify({
            'success': True,
            'insights': insights
        })
        
    except Exception as e:
        logger.error(f"Donation insights error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/recommend-donors', methods=['GET'])
def recommend_donors():
    """Recommend donors for a request. Accepts blood_type, latitude, longitude, max_results"""
    blood_type = request.args.get('blood_type')
    lat = request.args.get('lat')
    lon = request.args.get('lon')
    try:
        max_results = int(request.args.get('max_results', 10))
    except Exception:
        max_results = 10

    if not blood_type:
        return jsonify({'error': 'blood_type required'}), 400

    # Basic donor selection: same blood type, not recently donated
    # Prefer latitude/longitude from donor_locations if present
    query = """
    SELECT d.id as id, CONCAT(d.first_name, ' ', d.last_name) as name, d.blood_type, d.last_donation_date, d.phone,
           dl.latitude as latitude, dl.longitude as longitude
    FROM donors d
    LEFT JOIN donor_locations dl ON dl.donor_id = d.id
    WHERE d.blood_type = %s
    """
    donors = execute_query(query, (blood_type,))

    results = []
    for d in donors:
        # compute recency score
        last = d.get('last_donation_date')
        days_since = None
        if last:
            try:
                days_since = (datetime.now().date() - last).days
            except Exception:
                days_since = 9999
        else:
            days_since = 9999

        score = 0
        # prefer donors who haven't donated recently
        if days_since >= 90:
            score += 50
        elif days_since >= 60:
            score += 30

        # proximity scoring if coordinates provided
        distance_km = None
        if lat and lon and d.get('latitude') and d.get('longitude'):
            try:
                # simple haversine
                from math import radians, cos, sin, asin, sqrt
                lat1, lon1, lat2, lon2 = map(float, (lat, lon, d['latitude'], d['longitude']))
                dlat = radians(lat2 - lat1)
                dlon = radians(lon2 - lon1)
                a = sin(dlat/2)**2 + cos(radians(lat1)) * cos(radians(lat2)) * sin(dlon/2)**2
                c = 2 * asin(sqrt(a))
                distance_km = 6371 * c
                if distance_km <= 10:
                    score += 40
                elif distance_km <= 50:
                    score += 20
            except Exception:
                distance_km = None

        results.append({
            'donor_id': d['id'],
            'name': d.get('name'),
            'last_donation_days': days_since,
            'distance_km': round(distance_km,1) if distance_km is not None else None,
            'score': score,
            'phone': d.get('phone')
        })

    # sort by score desc
    results = sorted(results, key=lambda x: x['score'], reverse=True)[:max_results]

    return jsonify({'success': True, 'recommendations': results})

@app.route('/api/eligibility-check', methods=['GET'])
def eligibility_check():
    """Perform basic eligibility checks for a donor id"""
    try:
        donor_id = request.args.get('donor_id')
        if not donor_id:
            return jsonify({'error': 'donor_id required'}), 400

        # Get donor's information
        query = "SELECT id, date_of_birth, last_donation_date, health_conditions FROM donors WHERE id = %s"
        d = execute_query(query, (donor_id,), fetch_all=False)
        
        if not d:
            return jsonify({'error': 'Donor not found'}), 404

        reasons = []
        eligible = True

        # Age check
        dob = d.get('date_of_birth')
        if dob:
            age = (datetime.now().date() - dob).days // 365
            if age < 18 or age > 65:
                eligible = False
                reasons.append('Age out of eligible range (18-65)')

        # Last donation check (>= 90 days)
        last = d.get('last_donation_date')
        if last:
            days_since = (datetime.now().date() - last).days
            if days_since < 90:
                eligible = False
                reasons.append(f'Last donation was {days_since} days ago (min 90 days)')

        # Health conditions (basic heuristic)
        hc = d.get('health_conditions')
        if hc:
            lowered = hc.lower()
            if 'hepatitis' in lowered or 'hiv' in lowered or 'cancer' in lowered:
                eligible = False
                reasons.append('Disqualifying health condition present')

        return jsonify({'success': True, 'eligible': eligible, 'reasons': reasons})

    except Exception as e:
        logger.error(f"Eligibility check error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/anomaly-detection', methods=['GET'])
def anomaly_detection():
    """Detect anomalous spikes in requests using a simple z-score heuristic"""
    try:
        # get daily request counts for last 60 days
        query = """
        SELECT DATE(created_at) as day, COUNT(*) as cnt
        FROM blood_requests
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
        GROUP BY DATE(created_at)
        ORDER BY day
        """
        rows = execute_query(query)
        
        counts = [r['cnt'] for r in rows]

        import statistics
        anomalies = []
        if len(counts) >= 14:
            mean = statistics.mean(counts)
            stdev = statistics.pstdev(counts) if statistics.pstdev(counts) > 0 else 1
            # check last 7 days average
            last7 = counts[-7:]
            avg7 = statistics.mean(last7)
            z = (avg7 - mean) / stdev
            if z > 2.5:
                anomalies.append({'type': 'demand_spike', 'z_score': z, 'avg7': avg7, 'mean': mean})

        return jsonify({'success': True, 'anomalies': anomalies})

    except Exception as e:
        logger.error(f"Anomaly detection error: {e}")
        return jsonify({'error': str(e)}), 500


@app.route('/api/forecast-inventory', methods=['GET'])
def forecast_inventory():
    """Forecast inventory needs for next N days using simple moving average"""
    try:
        days = int(request.args.get('days', 7))
        
        # use blood_usage if available
        query = """
        SELECT blood_type, DATE(usage_date) as day, SUM(quantity_ml) as total
        FROM blood_usage
        WHERE usage_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        GROUP BY blood_type, DATE(usage_date)
        ORDER BY day
        """
        rows = execute_query(query)
        
        # aggregate per blood type
        from collections import defaultdict
        by_type = defaultdict(list)
        for r in rows:
            by_type[r['blood_type']].append(r['total'])

        forecasts = {}
        for btype, vals in by_type.items():
            if not vals:
                continue
            avg = sum(vals) / len(vals)
            forecasts[btype] = int(avg * days)

        return jsonify({'success': True, 'forecast_days': days, 'forecasts': forecasts})

    except Exception as e:
        logger.error(f"Forecast inventory error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        'status': 'healthy',
        'service': 'AI Blood Management Service',
        'timestamp': datetime.now().isoformat()
    })

if __name__ == '__main__':
    logging.info("AI Blood Management Service starting...")
    app.run(debug=True, host='0.0.0.0', port=5000)
