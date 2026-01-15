# Air Quality Backend - Complete API Documentation

**Version**: 1.0  
**Base URL**: `http://localhost:8000`  
**Last Updated**: December 14, 2025

## 📚 Table of Contents
1. [Authentication](#authentication)
2. [Pollution Data Endpoints](#pollution-data-endpoints)
3. [Prediction Endpoints](#prediction-endpoints)
4. [Alert Endpoints](#alert-endpoints)
5. [Admin Dashboard Endpoints](#admin-dashboard-endpoints)
6. [Admin Data Management](#admin-data-management)
7. [Admin Model Management](#admin-model-management)
8. [API Documentation Endpoints](#api-documentation-endpoints)
9. [Error Responses](#error-responses)
10. [Rate Limiting](#rate-limiting)

---

## 🔐 Authentication

### Login
Get JWT token for protected endpoints (admin routes).

**POST** `/api/login`

**Request Body**:
```json
{
  "email": "test@test.com",
  "password": "123456"
}
```

**Success Response (200 OK)**:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

**Error Response (401 Unauthorized)**:
```json
{
  "code": 401,
  "message": "Invalid credentials."
}
```

**Using the Token**:
```bash
curl http://localhost:8000/api/admin/stats \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..."
```

**Token Expiration**: 1 hour (3600 seconds)  
**Test Credentials**:
- Email: `test@test.com`
- Password: `123456`
- Roles: `ROLE_ADMIN`

---

## Pollution Data (Public)

### Get Current Pollution
**GET** `/api/pollution/current`

Returns the most recent pollution measurement.

### Get Historical Data
**GET** `/api/pollution/history?period=24h&pollutant=SO2`

**Query Parameters**:
- `period`: `24h`, `7d`, `30d` (default: 24h)
- `pollutant`: `SO2`, `NH3`, `PM2.5` (optional)

### Get Air Quality Index
**GET** `/api/pollution/aqi`

Returns current AQI and level (Good/Moderate/Unhealthy/Hazardous).

### Get Map Data
**GET** `/api/pollution/map-data`

Returns all measurement points for map visualization.

### Get Heatmap Data
**GET** `/api/pollution/heatmap?pollutant=SO2&timeframe=current`

**Query Parameters**:
- `pollutant`: `SO2`, `NH3`, `PM2.5` (required)
- `timeframe`: `current`, `hour`, `day` (default: current)

### Get Zone Risk Levels
**GET** `/api/pollution/zones`

Returns pollution levels for Gabès zones (industrial, city_center, residential, coastal).

### Submit Pollution Data
**POST** `/api/pollution/data`
```json
{
  "latitude": 33.88,
  "longitude": 10.09,
  "so2": 45.2,
  "nh3": 28.5,
  "pm25": 32.1,
  "temperature": 25.3,
  "humidity": 65.2,
  "pressure": 1013.2
}
```

AQI is automatically calculated and alerts are checked.

### Get Statistics
**GET** `/api/pollution/stats`

Returns min/max/average pollution levels.

---

## Predictions (Public)

### Get Next 6 Hours Predictions
**GET** `/api/predictions/next-6h?hours=6`

**Query Parameters**:
- `hours`: Number of hours ahead (1-6, default: 6)

### Generate Predictions
**POST** `/api/predictions/generate`
```json
{
  "pollutant": "SO2",
  "hours_ahead": 6
}
```

Calls Python ML API to generate new predictions.

### Get Prediction Accuracy
**GET** `/api/predictions/accuracy?pollutant=SO2&hours_ahead=3`

**Query Parameters**:
- `pollutant`: `SO2`, `NH3`, `PM2.5` (required)
- `hours_ahead`: 1-6 (required)

Returns RMSE, MAE, R² metrics.

### Compare Predictions
**GET** `/api/predictions/comparison/{pollutant}`

**Path Parameters**:
- `pollutant`: `SO2`, `NH3`, `PM2.5`

Shows predicted vs actual values.

### Update Actual Values
**POST** `/api/predictions/update-actual`

Updates predictions with actual measured values for accuracy calculation.

---

## Alerts (Public)

### Get Active Alerts
**GET** `/api/alerts/active`

Returns all current active alerts.

### Get Alert History
**GET** `/api/alerts/history?start_date=2025-01-01&end_date=2025-12-31&limit=50`

**Query Parameters**:
- `start_date`: YYYY-MM-DD (optional)
- `end_date`: YYYY-MM-DD (optional)
- `limit`: Number of results (default: 50)

### Simulate Alert
**POST** `/api/alerts/simulate`
```json
{
  "pollutant": "SO2",
  "value": 150
}
```

Creates test alert for given pollutant and value.

### Get Alert Statistics
**GET** `/api/alerts/stats`

Returns alert counts by level and pollutant.

---

## Admin (JWT Required)

### Dashboard Statistics
**GET** `/api/admin/stats`

Returns overview statistics (total measurements, active alerts, predictions count).

### Model Performance
**GET** `/api/admin/model/performance`

Returns ML model performance metrics by pollutant.

### Model Features
**GET** `/api/admin/model/features`

Returns feature importance from ML models.

### Data Quality Report
**GET** `/api/admin/data/quality`

Returns data quality metrics (missing values, outliers, completeness).

### Upload CSV Data
**POST** `/api/admin/data/upload`

**Form Data**:
- `file`: CSV file with columns: latitude, longitude, so2, nh3, pm25, temperature, humidity, pressure, timestamp

### Preview Data
**GET** `/api/admin/data/preview?limit=10`

**Query Parameters**:
- `limit`: Number of records (default: 10)

Returns recent pollution data entries.

### Train Model
**POST** `/api/admin/model/train`
```json
{
  "pollutant": "SO2",
  "epochs": 100,
  "batch_size": 32
}
```

Triggers ML model training via Python API.

### Get Model Metrics
**GET** `/api/admin/model/metrics`

Returns stored model performance metrics (RMSE, MAE, R²).

### Update Model Config
**PUT** `/api/admin/model/config`
```json
{
  "pollutant": "SO2",
  "learning_rate": 0.001,
  "hidden_layers": [64, 32]
}
```

Updates ML model configuration.

---

## WHO Alert Thresholds

| Pollutant | Green | Yellow | Orange | Red |
|-----------|-------|--------|--------|-----|
| SO₂       | < 20  | 20-50  | 50-100 | > 100 |
| NH₃       | < 30  | 30-60  | 60-120 | > 120 |
| PM2.5     | < 15  | 15-35  | 35-55  | > 55 |

All values in µg/m³

---

## Python ML API Integration

The backend expects a Python ML API running at `http://localhost:5000` with these endpoints:

### Predict Endpoint
**POST** `http://localhost:5000/predict`
```json
{
  "pollutant": "SO2",
  "features": {
    "temperature": 25.3,
    "humidity": 65.2,
    "pressure": 1013.2,
    "hour": 14,
    "day_of_week": 3,
    "recent_avg": 42.5
  },
  "hours_ahead": 3
}
```

**Response**:
```json
{
  "pollutant": "SO2",
  "prediction": 48.3,
  "confidence": 0.85,
  "model_version": "v1.2.0"
}
```

### Train Endpoint
**POST** `http://localhost:5000/train`
```json
{
  "pollutant": "SO2",
  "epochs": 100,
  "batch_size": 32
}
```

**Response**:
```json
{
  "status": "success",
  "metrics": {
    "rmse": 5.2,
    "mae": 3.8,
    "r2": 0.92
  },
  "model_version": "v1.3.0"
}
```

---

## Error Responses

All endpoints return errors in this format:
```json
{
  "error": "Error type",
  "message": "Detailed error message"
}
```

**Common HTTP Status Codes**:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized (missing/invalid JWT)
- `404` - Not Found
- `500` - Internal Server Error

---

## Testing with cURL

### Login
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456"}'
```

### Submit Pollution Data
```bash
curl -X POST http://localhost:8000/api/pollution/data \
  -H "Content-Type: application/json" \
  -d '{"latitude":33.88,"longitude":10.09,"so2":45.2,"nh3":28.5,"pm25":32.1,"temperature":25.3,"humidity":65.2,"pressure":1013.2}'
```

### Get Current AQI
```bash
curl http://localhost:8000/api/pollution/aqi
```

### Simulate Alert
```bash
curl -X POST http://localhost:8000/api/alerts/simulate \
  -H "Content-Type: application/json" \
  -d '{"pollutant":"SO2","value":150}'
```

### Access Admin Endpoint (with JWT)
```bash
curl http://localhost:8000/api/admin/stats \
  -H "Authorization: Bearer YOUR_JWT_TOKEN_HERE"
```
