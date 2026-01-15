# Air Quality Backend - Complete API Reference

**Version**: 1.0.0  
**Base URL**: `http://localhost:8000`  
**Protocol**: REST  
**Format**: JSON  
**Last Updated**: December 14, 2025

---

## 📋 Quick Links
- [Interactive Documentation](#interactive-documentation)
- [Authentication](#authentication)
- [Pollution Endpoints](#pollution-data-endpoints)
- [Predictions](#prediction-endpoints)
- [Alerts](#alert-endpoints)
- [Admin APIs](#admin-endpoints)
- [Error Codes](#error-handling)
- [Examples](#complete-examples)

---

## 🌐 Interactive Documentation

### Swagger UI
**URL**: `http://localhost:8000/api/doc`  
Browse and test all endpoints interactively with Swagger UI interface.

### OpenAPI JSON
**URL**: `http://localhost:8000/api/doc.json`  
Download the complete OpenAPI 3.0 specification.

---

## 🔐 Authentication

### Overview
- **Public Endpoints**: `/api/pollution/*`, `/api/predictions/*`, `/api/alerts/*`
- **Protected Endpoints**: `/api/admin/*` (requires JWT)
- **Token Type**: Bearer JWT (RS256)
- **Token Lifetime**: 3600 seconds (1 hour)

### Get JWT Token

**Endpoint**: `POST /api/login`

**Request Headers**:
```
Content-Type: application/json
```

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
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3MDI1MjQw..."
}
```

**Error Responses**:

`401 Unauthorized` - Invalid credentials:
```json
{
  "code": 401,
  "message": "Invalid credentials."
}
```

`400 Bad Request` - Missing fields:
```json
{
  "error": "Validation failed",
  "message": "Email and password are required"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456"}'
```

### Using Authentication Token

**Header Format**:
```
Authorization: Bearer <your_jwt_token>
```

**Example**:
```bash
curl http://localhost:8000/api/admin/stats \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci..."
```

**Test Credentials**:
- **Email**: test@test.com
- **Password**: 123456
- **Roles**: ROLE_ADMIN

---

## 🌡️ Pollution Data Endpoints

All endpoints are **PUBLIC** (no authentication required).

### 1. Get Current Pollution

Get the most recent pollution measurement.

**Endpoint**: `GET /api/pollution/current`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "timestamp": "2025-12-14 15:30:00",
  "pollutants": {
    "so2": 45.2,
    "nh3": 28.5,
    "pm25": 32.1
  },
  "weather": {
    "temperature": 25.3,
    "humidity": 65.2,
    "wind_speed": 5.2,
    "wind_direction": 180.0,
    "pressure": 1013.2
  },
  "aqi": {
    "overall": 85,
    "category": "Moderate",
    "dominant_pollutant": "PM2.5",
    "breakdown": {
      "SO2": 68,
      "NH3": 52,
      "PM2.5": 85
    }
  },
  "location": {
    "latitude": 33.88,
    "longitude": 10.09
  },
  "source": "sensor_01"
}
```

**Error Response (404 Not Found)**:
```json
{
  "error": "No data available"
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/pollution/current
```

---

### 2. Get Historical Data

Retrieve pollution data over time with filtering options.

**Endpoint**: `GET /api/pollution/history`

**Query Parameters**:
| Parameter | Type | Required | Default | Options | Description |
|-----------|------|----------|---------|---------|-------------|
| `period` | string | No | 24h | 24h, 7d, 30d | Time period to retrieve |
| `pollutant` | string | No | all | SO2, NH3, PM2.5 | Specific pollutant filter |

**Success Response (200 OK)**:
```json
{
  "period": "24h",
  "pollutant": "SO2",
  "count": 144,
  "data": [
    {
      "timestamp": "2025-12-13 15:30:00",
      "value": 42.5
    },
    {
      "timestamp": "2025-12-13 16:30:00",
      "value": 45.2
    }
  ]
}
```

**When pollutant is "all"**:
```json
{
  "period": "24h",
  "pollutant": "all",
  "count": 144,
  "data": [
    {
      "timestamp": "2025-12-13 15:30:00",
      "so2": 42.5,
      "nh3": 28.3,
      "pm25": 31.2,
      "aqi": 82.0
    }
  ]
}
```

**cURL Examples**:
```bash
# Get last 24 hours of SO2 data
curl "http://localhost:8000/api/pollution/history?period=24h&pollutant=SO2"

# Get last 7 days, all pollutants
curl "http://localhost:8000/api/pollution/history?period=7d"
```

---

### 3. Get AQI Information

Get current Air Quality Index calculation.

**Endpoint**: `GET /api/pollution/aqi`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "overall": 85,
  "category": "Moderate",
  "color": "#FFFF00",
  "dominant_pollutant": "PM2.5",
  "health_message": "Air quality is acceptable. However, sensitive groups may experience minor respiratory symptoms.",
  "breakdown": {
    "SO2": {
      "aqi": 68,
      "concentration": 45.2,
      "category": "Moderate"
    },
    "NH3": {
      "aqi": 52,
      "concentration": 28.5,
      "category": "Moderate"
    },
    "PM2.5": {
      "aqi": 85,
      "concentration": 32.1,
      "category": "Moderate"
    }
  }
}
```

**AQI Categories**:
| Range | Category | Color | Description |
|-------|----------|-------|-------------|
| 0-50 | Good | Green | Air quality is satisfactory |
| 51-100 | Moderate | Yellow | Acceptable, but sensitive groups may have issues |
| 101-150 | Unhealthy for Sensitive Groups | Orange | Sensitive groups should limit outdoor exposure |
| 151-200 | Unhealthy | Red | Everyone may experience health effects |
| 201-300 | Very Unhealthy | Purple | Health alert, everyone affected |
| 301+ | Hazardous | Maroon | Emergency conditions |

**cURL Example**:
```bash
curl http://localhost:8000/api/pollution/aqi
```

---

### 4. Get Map Data

Get recent measurement points for map visualization.

**Endpoint**: `GET /api/pollution/map-data`

**Request**: No parameters (automatically retrieves last hour)

**Success Response (200 OK)**:
```json
{
  "points": [
    {
      "lat": 33.88,
      "lng": 10.09,
      "so2": 45.2,
      "nh3": 28.5,
      "pm25": 32.1,
      "aqi": 85.0,
      "timestamp": "2025-12-14 15:30:00"
    },
    {
      "lat": 33.8815,
      "lng": 10.0982,
      "so2": 52.3,
      "nh3": 31.2,
      "pm25": 35.8,
      "aqi": 92.0,
      "timestamp": "2025-12-14 15:28:00"
    }
  ],
  "count": 2
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/pollution/map-data
```

---

### 5. Get Heatmap Data

Generate grid or point data for heatmap visualization.

**Endpoint**: `GET /api/pollution/heatmap`

**Query Parameters**:
| Parameter | Type | Required | Default | Options | Description |
|-----------|------|----------|---------|---------|-------------|
| `pollutant` | string | Yes | SO2 | SO2, NH3, PM2.5 | Pollutant to visualize |
| `timeframe` | string | No | current | current, hour, day | Time aggregation |

**Success Response (200 OK)**:
```json
{
  "pollutant": "SO2",
  "timeframe": "current",
  "type": "grid",
  "data": [
    {
      "lat": 33.87,
      "lng": 10.08,
      "value": 45.2,
      "intensity": 0.68
    },
    {
      "lat": 33.88,
      "lng": 10.09,
      "value": 52.3,
      "intensity": 0.75
    }
  ],
  "min_value": 20.1,
  "max_value": 68.5,
  "avg_value": 44.3,
  "count": 25
}
```

**cURL Examples**:
```bash
# Current SO2 heatmap
curl "http://localhost:8000/api/pollution/heatmap?pollutant=SO2&timeframe=current"

# Hourly average PM2.5 heatmap
curl "http://localhost:8000/api/pollution/heatmap?pollutant=PM2.5&timeframe=hour"
```

---

### 6. Get Zone Risk Levels

Get pollution risk analysis for predefined zones in Gabès.

**Endpoint**: `GET /api/pollution/zones`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "zones": {
    "industrial": {
      "location": {
        "latitude": 33.8797,
        "longitude": 10.0958
      },
      "pollutants": {
        "so2": 78.5,
        "nh3": 45.2,
        "pm25": 52.3
      },
      "aqi": 142,
      "risk_level": "unhealthy_sensitive",
      "message": "Personnes sensibles devraient limiter les activités extérieures prolongées"
    },
    "city_center": {
      "location": {
        "latitude": 33.8815,
        "longitude": 10.0982
      },
      "pollutants": {
        "so2": 45.2,
        "nh3": 28.5,
        "pm25": 32.1
      },
      "aqi": 85,
      "risk_level": "moderate",
      "message": "Qualité de l'air acceptable"
    },
    "residential": {
      "location": {
        "latitude": 33.8840,
        "longitude": 10.1020
      },
      "pollutants": {
        "so2": 32.1,
        "nh3": 22.3,
        "pm25": 25.8
      },
      "aqi": 68,
      "risk_level": "moderate",
      "message": "Qualité de l'air acceptable"
    },
    "coastal": {
      "location": {
        "latitude": 33.8780,
        "longitude": 10.1050
      },
      "pollutants": {
        "so2": 18.5,
        "nh3": 15.2,
        "pm25": 20.1
      },
      "aqi": 45,
      "risk_level": "good",
      "message": "Qualité de l'air bonne"
    }
  },
  "timestamp": "2025-12-14 15:30:00"
}
```

**Risk Levels**:
- `good` - AQI 0-50
- `moderate` - AQI 51-100
- `unhealthy_sensitive` - AQI 101-150
- `unhealthy` - AQI 151-200
- `very_unhealthy` - AQI 201-300
- `hazardous` - AQI 301+

**cURL Example**:
```bash
curl http://localhost:8000/api/pollution/zones
```

---

### 7. Submit Pollution Data

Add new pollution measurement to the system.

**Endpoint**: `POST /api/pollution/data`

**Request Headers**:
```
Content-Type: application/json
```

**Request Body**:
```json
{
  "latitude": 33.88,
  "longitude": 10.09,
  "so2": 45.2,
  "nh3": 28.5,
  "pm25": 32.1,
  "temperature": 25.3,
  "humidity": 65.2,
  "wind_speed": 5.2,
  "wind_direction": 180.0,
  "pressure": 1013.2,
  "timestamp": "2025-12-14 15:30:00",
  "source": "sensor_01"
}
```

**Field Descriptions**:
| Field | Type | Required | Unit | Description |
|-------|------|----------|------|-------------|
| `latitude` | float | No | degrees | GPS latitude |
| `longitude` | float | No | degrees | GPS longitude |
| `so2` | float | Yes | µg/m³ | Sulfur dioxide concentration |
| `nh3` | float | Yes | µg/m³ | Ammonia concentration |
| `pm25` | float | Yes | µg/m³ | Particulate matter 2.5 |
| `temperature` | float | No | °C | Air temperature |
| `humidity` | float | No | % | Relative humidity |
| `wind_speed` | float | No | m/s | Wind speed |
| `wind_direction` | float | No | degrees | Wind direction (0-360) |
| `pressure` | float | No | hPa | Atmospheric pressure |
| `timestamp` | string | No | - | ISO 8601 format, defaults to now |
| `source` | string | No | - | Data source identifier |

**Success Response (201 Created)**:
```json
{
  "message": "Data added successfully",
  "id": 1523,
  "aqi": {
    "overall": 85,
    "category": "Moderate",
    "dominant_pollutant": "PM2.5",
    "breakdown": {
      "SO2": 68,
      "NH3": 52,
      "PM2.5": 85
    }
  },
  "alerts_created": 1
}
```

**Automatic Processing**:
1. AQI is calculated automatically
2. WHO threshold alerts are checked and created if needed
3. Data is validated and stored
4. Heatmap cache is updated

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/pollution/data \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 33.88,
    "longitude": 10.09,
    "so2": 45.2,
    "nh3": 28.5,
    "pm25": 32.1,
    "temperature": 25.3,
    "humidity": 65.2,
    "wind_speed": 5.2,
    "wind_direction": 180,
    "pressure": 1013.2
  }'
```

---

### 8. Get Statistics

Get overall pollution statistics.

**Endpoint**: `GET /api/pollution/stats`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "total_records": 15234,
  "averages": {
    "so2": 42.35,
    "nh3": 28.12,
    "pm25": 31.58
  },
  "maximums": {
    "so2": 125.80,
    "nh3": 98.50,
    "pm25": 85.30
  }
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/pollution/stats
```

---

## 🔮 Prediction Endpoints

All endpoints are **PUBLIC** (no authentication required).

### 1. Get Next Hours Predictions

Retrieve predictions for the next N hours.

**Endpoint**: `GET /api/predictions/next-6h`

**Query Parameters**:
| Parameter | Type | Required | Default | Range | Description |
|-----------|------|----------|---------|-------|-------------|
| `hours` | integer | No | 6 | 1-6 | Number of hours to predict |

**Success Response (200 OK)**:
```json
{
  "hours": 6,
  "predictions": {
    "SO2": [
      {
        "hours_ahead": 1,
        "value": 47.3,
        "prediction_for": "2025-12-14 16:30:00",
        "created_at": "2025-12-14 15:30:00",
        "model_version": "v1.2.0"
      },
      {
        "hours_ahead": 2,
        "value": 49.1,
        "prediction_for": "2025-12-14 17:30:00",
        "created_at": "2025-12-14 15:30:00",
        "model_version": "v1.2.0"
      }
    ],
    "NH3": [...],
    "PM2.5": [...]
  },
  "total_predictions": 18,
  "timestamp": "2025-12-14 15:30:00"
}
```

**cURL Examples**:
```bash
# Get next 3 hours
curl "http://localhost:8000/api/predictions/next-6h?hours=3"

# Get next 6 hours (default)
curl http://localhost:8000/api/predictions/next-6h
```

---

### 2. Generate Predictions

Trigger ML model to generate new predictions.

**Endpoint**: `POST /api/predictions/generate`

**Request Headers**:
```
Content-Type: application/json
```

**Request Body**:
```json
{
  "hours": 6,
  "model_version": "v1.2.0"
}
```

**Success Response (201 Created)**:
```json
{
  "message": "Predictions generated successfully",
  "count": 18,
  "hours_ahead": 6,
  "model_version": "v1.2.0"
}
```

**Error Response (500 Internal Server Error)**:
```json
{
  "error": "Failed to generate predictions",
  "message": "Python ML API is not available. Using fallback model."
}
```

**Note**: Falls back to persistence model if Python ML API is unavailable.

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/predictions/generate \
  -H "Content-Type: application/json" \
  -d '{"hours": 6, "model_version": "v1.2.0"}'
```

---

### 3. Get Prediction Accuracy

Calculate accuracy metrics for predictions.

**Endpoint**: `GET /api/predictions/accuracy`

**Query Parameters**:
| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `pollutant` | string | No | SO2, NH3, PM2.5 | Filter by pollutant |
| `hours_ahead` | integer | No | 1-6 | Filter by prediction horizon |

**Success Response (200 OK)**:
```json
{
  "metrics": {
    "SO2": {
      "1_hour": {
        "rmse": 3.25,
        "mae": 2.18,
        "r2": 0.94,
        "samples": 145
      },
      "3_hour": {
        "rmse": 5.82,
        "mae": 4.15,
        "r2": 0.87,
        "samples": 145
      },
      "6_hour": {
        "rmse": 8.45,
        "mae": 6.23,
        "r2": 0.78,
        "samples": 145
      }
    },
    "NH3": {...},
    "PM2.5": {...}
  },
  "overall_r2": 0.86
}
```

**When filtered by pollutant and hours_ahead**:
```json
{
  "pollutant": "SO2",
  "hours_ahead": 3,
  "rmse": 5.82,
  "mae": 4.15,
  "r2": 0.87,
  "samples": 145
}
```

**Metric Explanations**:
- **RMSE** (Root Mean Square Error): Lower is better, in µg/m³
- **MAE** (Mean Absolute Error): Average prediction error, in µg/m³
- **R²** (R-squared): 0-1, closer to 1 is better fit
- **Samples**: Number of predictions evaluated

**cURL Examples**:
```bash
# All metrics
curl http://localhost:8000/api/predictions/accuracy

# SO2 only, 3 hours ahead
curl "http://localhost:8000/api/predictions/accuracy?pollutant=SO2&hours_ahead=3"
```

---

### 4. Compare Predictions vs Actual

Get predicted vs actual values for evaluation.

**Endpoint**: `GET /api/predictions/comparison/{pollutant}`

**Path Parameters**:
| Parameter | Type | Required | Options | Description |
|-----------|------|----------|---------|-------------|
| `pollutant` | string | Yes | SO2, NH3, PM2.5 | Pollutant to analyze |

**Query Parameters**:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 100 | Max records to return |

**Success Response (200 OK)**:
```json
{
  "pollutant": "SO2",
  "data": [
    {
      "prediction_for": "2025-12-14 14:30:00",
      "predicted_value": 47.3,
      "actual_value": 45.8,
      "error": 1.5,
      "error_percentage": 3.27,
      "hours_ahead": 1,
      "model_version": "v1.2.0"
    },
    {
      "prediction_for": "2025-12-14 15:30:00",
      "predicted_value": 49.1,
      "actual_value": 52.3,
      "error": -3.2,
      "error_percentage": -6.12,
      "hours_ahead": 2,
      "model_version": "v1.2.0"
    }
  ],
  "count": 100
}
```

**cURL Example**:
```bash
curl "http://localhost:8000/api/predictions/comparison/SO2?limit=50"
```

---

### 5. Update Actual Values

Match predictions with actual measured values.

**Endpoint**: `POST /api/predictions/update-actual`

**Request**: No body required

**Success Response (200 OK)**:
```json
{
  "message": "Actual values updated",
  "updated_count": 42
}
```

**Process**:
1. Finds predictions where actual_value is NULL
2. Matches with pollution_data by pollutant and timestamp
3. Updates prediction records with actual values
4. Used for accuracy calculation

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/predictions/update-actual
```

---

## 🚨 Alert Endpoints

All endpoints are **PUBLIC** (no authentication required).

### 1. Get Active Alerts

Retrieve all currently active pollution alerts.

**Endpoint**: `GET /api/alerts/active`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "active_alerts": [
    {
      "id": 523,
      "pollutant": "SO2",
      "value": 125.5,
      "level": "red",
      "message": "Niveau SO₂ très élevé (125.5 µg/m³) - Air très malsain. Évitez les activités extérieures. Personnes sensibles restez à l'intérieur.",
      "latitude": 33.8797,
      "longitude": 10.0958,
      "created_at": "2025-12-14 14:25:00"
    },
    {
      "id": 524,
      "pollutant": "PM2.5",
      "value": 58.3,
      "level": "orange",
      "message": "Niveau PM2.5 élevé (58.3 µg/m³) - Air malsain pour groupes sensibles. Réduisez les activités prolongées à l'extérieur.",
      "latitude": 33.8815,
      "longitude": 10.0982,
      "created_at": "2025-12-14 15:10:00"
    }
  ],
  "count": 2,
  "timestamp": "2025-12-14 15:30:00"
}
```

**Alert Levels**:
| Level | Color | Icon | Description |
|-------|-------|------|-------------|
| `green` | #00FF00 | ✓ | Air quality good |
| `yellow` | #FFFF00 | ⚠ | Acceptable, sensitive groups be aware |
| `orange` | #FFA500 | ⚠️ | Unhealthy for sensitive groups |
| `red` | #FF0000 | ⛔ | Very unhealthy, avoid outdoor activities |

**cURL Example**:
```bash
curl http://localhost:8000/api/alerts/active
```

---

### 2. Get Alert History

Retrieve historical alerts with optional filtering.

**Endpoint**: `GET /api/alerts/history`

**Query Parameters**:
| Parameter | Type | Required | Format | Description |
|-----------|------|----------|--------|-------------|
| `start_date` | string | No | YYYY-MM-DD | Filter from date |
| `end_date` | string | No | YYYY-MM-DD | Filter to date |
| `limit` | integer | No | - | Max records (default: 50) |

**Success Response (200 OK)**:
```json
{
  "alerts": [
    {
      "id": 523,
      "pollutant": "SO2",
      "value": 125.5,
      "level": "red",
      "message": "Niveau SO₂ très élevé...",
      "is_active": false,
      "created_at": "2025-12-14 14:25:00",
      "resolved_at": "2025-12-14 15:20:00",
      "latitude": 33.8797,
      "longitude": 10.0958
    },
    {
      "id": 522,
      "pollutant": "NH3",
      "value": 68.2,
      "level": "orange",
      "message": "Niveau NH₃ élevé...",
      "is_active": false,
      "created_at": "2025-12-14 12:15:00",
      "resolved_at": "2025-12-14 13:45:00",
      "latitude": 33.8815,
      "longitude": 10.0982
    }
  ],
  "count": 2,
  "filters": {
    "start_date": "2025-12-01",
    "end_date": "2025-12-14",
    "limit": 50
  }
}
```

**cURL Examples**:
```bash
# Last 50 alerts
curl http://localhost:8000/api/alerts/history

# Alerts in December 2025
curl "http://localhost:8000/api/alerts/history?start_date=2025-12-01&end_date=2025-12-31"

# Last 100 alerts
curl "http://localhost:8000/api/alerts/history?limit=100"
```

---

### 3. Simulate Alert

Create a test alert for a given pollutant and value.

**Endpoint**: `POST /api/alerts/simulate`

**Request Headers**:
```
Content-Type: application/json
```

**Request Body**:
```json
{
  "pollutant": "SO2",
  "value": 150.5
}
```

**Success Response (201 Created)**:
```json
{
  "message": "Alert simulated successfully",
  "alert": {
    "id": 525,
    "pollutant": "SO2",
    "value": 150.5,
    "level": "red",
    "message": "Niveau SO₂ très élevé (150.5 µg/m³) - Air très malsain. Évitez les activités extérieures. Personnes sensibles restez à l'intérieur.",
    "created_at": "2025-12-14 15:30:00"
  }
}
```

**Error Response (400 Bad Request)**:
```json
{
  "error": "Simulation failed",
  "message": "Invalid pollutant. Must be SO2, NH3, or PM2.5"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/alerts/simulate \
  -H "Content-Type: application/json" \
  -d '{"pollutant": "SO2", "value": 150}'
```

---

### 4. Get Alert Statistics

Get aggregated statistics about alerts.

**Endpoint**: `GET /api/alerts/stats`

**Request**: No parameters

**Success Response (200 OK)**:
```json
{
  "total_alerts": 1523,
  "active_alerts": 3,
  "by_level": {
    "green": 234,
    "yellow": 856,
    "orange": 325,
    "red": 108
  },
  "by_pollutant": {
    "SO2": 623,
    "NH3": 445,
    "PM2.5": 455
  },
  "last_24h": 12,
  "last_7d": 85,
  "last_30d": 342,
  "avg_duration_minutes": 45.3
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/alerts/stats
```

---

## 👨‍💼 Admin Endpoints

All admin endpoints require **JWT Authentication**.

**Authorization Header Required**:
```
Authorization: Bearer <your_jwt_token>
```

### Admin Dashboard

#### 1. Get Dashboard Statistics

**Endpoint**: `GET /api/admin/stats`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Success Response (200 OK)**:
```json
{
  "pollution_data": {
    "total_records": 15234,
    "last_24h": 144,
    "sources": ["sensor_01", "sensor_02", "api", "manual"]
  },
  "alerts": {
    "total": 1523,
    "active": 3,
    "last_24h": 12
  },
  "predictions": {
    "total": 8652,
    "last_generated": "2025-12-14 15:00:00",
    "model_version": "v1.2.0"
  },
  "data_quality": {
    "completeness": 98.5,
    "missing_values_percent": 1.5,
    "outliers_count": 23
  },
  "system": {
    "ml_api_status": "online",
    "database_size_mb": 452.3,
    "uptime_hours": 720
  }
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/admin/stats \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

#### 2. Get Model Performance

**Endpoint**: `GET /api/admin/model/performance`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Success Response (200 OK)**:
```json
{
  "models": {
    "SO2": {
      "current_version": "v1.2.0",
      "rmse": 4.25,
      "mae": 3.12,
      "r2": 0.92,
      "last_trained": "2025-12-10 10:30:00",
      "samples_trained": 5000,
      "performance_trend": "improving"
    },
    "NH3": {
      "current_version": "v1.2.0",
      "rmse": 3.85,
      "mae": 2.95,
      "r2": 0.94,
      "last_trained": "2025-12-10 10:30:00",
      "samples_trained": 5000,
      "performance_trend": "stable"
    },
    "PM2.5": {
      "current_version": "v1.2.0",
      "rmse": 5.12,
      "mae": 3.88,
      "r2": 0.89,
      "last_trained": "2025-12-10 10:30:00",
      "samples_trained": 5000,
      "performance_trend": "improving"
    }
  },
  "overall_performance": "good"
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/admin/model/performance \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

#### 3. Get Feature Importance

**Endpoint**: `GET /api/admin/model/features`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Success Response (200 OK)**:
```json
{
  "SO2": {
    "features": [
      {
        "name": "hour_of_day",
        "importance": 0.32,
        "rank": 1
      },
      {
        "name": "temperature",
        "importance": 0.25,
        "rank": 2
      },
      {
        "name": "wind_speed",
        "importance": 0.18,
        "rank": 3
      },
      {
        "name": "humidity",
        "importance": 0.15,
        "rank": 4
      },
      {
        "name": "pressure",
        "importance": 0.10,
        "rank": 5
      }
    ]
  },
  "NH3": {...},
  "PM2.5": {...}
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/admin/model/features \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

### Admin Data Management

#### 1. Get Data Quality Report

**Endpoint**: `GET /api/admin/data/quality`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Success Response (200 OK)**:
```json
{
  "overall_score": 95.8,
  "total_records": 15234,
  "date_range": {
    "first_record": "2025-01-01 00:00:00",
    "last_record": "2025-12-14 15:30:00"
  },
  "completeness": {
    "latitude": 98.5,
    "longitude": 98.5,
    "pressure": 87.3,
    "so2": 100.0,
    "nh3": 100.0,
    "pm25": 100.0,
    "temperature": 99.8,
    "humidity": 99.5
  },
  "outliers": {
    "so2": 12,
    "nh3": 8,
    "pm25": 15,
    "total": 35
  },
  "missing_values": {
    "latitude": 228,
    "longitude": 228,
    "pressure": 1935,
    "total": 2391
  },
  "data_gaps": [
    {
      "start": "2025-11-15 03:00:00",
      "end": "2025-11-15 05:30:00",
      "duration_hours": 2.5
    }
  ]
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/admin/data/quality \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

#### 2. Upload CSV Data

**Endpoint**: `POST /api/admin/data/upload`

**Request Headers**:
```
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Form Data**:
- `file`: CSV file

**CSV Format**:
```csv
latitude,longitude,so2,nh3,pm25,temperature,humidity,pressure,wind_speed,wind_direction,timestamp
33.88,10.09,45.2,28.5,32.1,25.3,65.2,1013.2,5.2,180,2025-12-14 10:30:00
33.8815,10.0982,52.3,31.2,35.8,26.1,62.5,1012.8,6.1,175,2025-12-14 11:00:00
```

**Success Response (201 Created)**:
```json
{
  "message": "Data imported successfully",
  "imported_count": 1523,
  "skipped_count": 12,
  "errors": [
    {
      "line": 45,
      "error": "Invalid SO2 value"
    },
    {
      "line": 128,
      "error": "Missing required field: pm25"
    }
  ],
  "processing_time_seconds": 2.3
}
```

**Error Response (400 Bad Request)**:
```json
{
  "error": "Invalid file format",
  "message": "CSV file must contain required columns: so2, nh3, pm25"
}
```

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/admin/data/upload \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -F "file=@pollution_data.csv"
```

---

#### 3. Preview Data

**Endpoint**: `GET /api/admin/data/preview`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Query Parameters**:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | integer | No | 10 | Number of records |

**Success Response (200 OK)**:
```json
{
  "data": [
    {
      "id": 15234,
      "timestamp": "2025-12-14 15:30:00",
      "so2": 45.2,
      "nh3": 28.5,
      "pm25": 32.1,
      "temperature": 25.3,
      "humidity": 65.2,
      "aqi": 85.0,
      "source": "sensor_01"
    }
  ],
  "count": 10,
  "total_records": 15234
}
```

**cURL Example**:
```bash
curl "http://localhost:8000/api/admin/data/preview?limit=20" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

### Admin Model Management

#### 1. Train Model

**Endpoint**: `POST /api/admin/model/train`

**Request Headers**:
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body**:
```json
{
  "pollutant": "SO2",
  "epochs": 100,
  "batch_size": 32
}
```

**Success Response (202 Accepted)**:
```json
{
  "message": "Training started",
  "job_id": "train_so2_20251214_153000",
  "estimated_time_minutes": 15
}
```

**Note**: Training is asynchronous. Use job_id to check status.

**cURL Example**:
```bash
curl -X POST http://localhost:8000/api/admin/model/train \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Content-Type: application/json" \
  -d '{"pollutant": "SO2", "epochs": 100, "batch_size": 32}'
```

---

#### 2. Get Model Metrics

**Endpoint**: `GET /api/admin/model/metrics`

**Request Headers**:
```
Authorization: Bearer <token>
```

**Success Response (200 OK)**:
```json
{
  "metrics": [
    {
      "id": 523,
      "model_name": "LSTM_SO2",
      "pollutant": "SO2",
      "metric_type": "rmse",
      "value": 4.25,
      "created_at": "2025-12-10 10:30:00"
    },
    {
      "id": 524,
      "model_name": "LSTM_SO2",
      "pollutant": "SO2",
      "metric_type": "mae",
      "value": 3.12,
      "created_at": "2025-12-10 10:30:00"
    },
    {
      "id": 525,
      "model_name": "LSTM_SO2",
      "pollutant": "SO2",
      "metric_type": "r2",
      "value": 0.92,
      "created_at": "2025-12-10 10:30:00"
    }
  ],
  "count": 45
}
```

**cURL Example**:
```bash
curl http://localhost:8000/api/admin/model/metrics \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..."
```

---

#### 3. Update Model Config

**Endpoint**: `PUT /api/admin/model/config`

**Request Headers**:
```
Authorization: Bearer <token>
Content-Type: application/json
```

**Request Body**:
```json
{
  "pollutant": "SO2",
  "learning_rate": 0.001,
  "hidden_layers": [64, 32, 16],
  "dropout": 0.2
}
```

**Success Response (200 OK)**:
```json
{
  "message": "Configuration updated",
  "pollutant": "SO2",
  "config": {
    "learning_rate": 0.001,
    "hidden_layers": [64, 32, 16],
    "dropout": 0.2
  }
}
```

**cURL Example**:
```bash
curl -X PUT http://localhost:8000/api/admin/model/config \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1Qi..." \
  -H "Content-Type: application/json" \
  -d '{
    "pollutant": "SO2",
    "learning_rate": 0.001,
    "hidden_layers": [64, 32, 16],
    "dropout": 0.2
  }'
```

---

## ⚠️ Error Handling

### Error Response Format

All errors follow this structure:
```json
{
  "error": "Error type",
  "message": "Detailed error description",
  "code": 400
}
```

### HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | Successful GET request |
| 201 | Created | Successful POST request |
| 202 | Accepted | Async operation started |
| 400 | Bad Request | Invalid input data |
| 401 | Unauthorized | Missing or invalid JWT token |
| 403 | Forbidden | Valid token but insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Server error |
| 503 | Service Unavailable | External service (ML API) down |

### Common Errors

#### Missing Authentication
```json
{
  "code": 401,
  "message": "JWT Token not found"
}
```

#### Invalid Token
```json
{
  "code": 401,
  "message": "Invalid JWT Token"
}
```

#### Expired Token
```json
{
  "code": 401,
  "message": "Expired JWT Token"
}
```

#### Validation Error
```json
{
  "error": "Validation failed",
  "message": "Field 'so2' is required",
  "field": "so2"
}
```

#### Resource Not Found
```json
{
  "error": "Not found",
  "message": "No pollution data available"
}
```

#### External Service Error
```json
{
  "error": "Service unavailable",
  "message": "Python ML API is not responding. Using fallback model."
}
```

---

## 📊 WHO Pollution Thresholds

### Alert Levels

| Pollutant | Green (Good) | Yellow (Moderate) | Orange (Unhealthy Sensitive) | Red (Very Unhealthy) |
|-----------|--------------|-------------------|------------------------------|----------------------|
| SO₂ | < 20 µg/m³ | 20-50 µg/m³ | 50-100 µg/m³ | > 100 µg/m³ |
| NH₃ | < 30 µg/m³ | 30-60 µg/m³ | 60-120 µg/m³ | > 120 µg/m³ |
| PM2.5 | < 15 µg/m³ | 15-35 µg/m³ | 35-55 µg/m³ | > 55 µg/m³ |

### Health Messages (French)

#### Green Level
"Qualité de l'air bonne. Aucune restriction."

#### Yellow Level
"Qualité de l'air acceptable. Les personnes sensibles doivent rester vigilantes."

#### Orange Level
"Air malsain pour les groupes sensibles. Réduisez les activités prolongées à l'extérieur."

#### Red Level
"Air très malsain. Évitez les activités extérieures. Les personnes sensibles doivent rester à l'intérieur."

---

## 🔄 Rate Limiting

**Current Status**: No rate limiting implemented  
**Recommended**: 
- Public endpoints: 100 requests/minute
- Admin endpoints: 60 requests/minute

---

## 📱 Complete Examples

### Example 1: Monitor Current Air Quality

```bash
# 1. Get current pollution
curl http://localhost:8000/api/pollution/current

# 2. Check AQI
curl http://localhost:8000/api/pollution/aqi

# 3. Get active alerts
curl http://localhost:8000/api/alerts/active

# 4. Get next 6 hours predictions
curl http://localhost:8000/api/predictions/next-6h
```

### Example 2: Submit Sensor Data

```bash
curl -X POST http://localhost:8000/api/pollution/data \
  -H "Content-Type: application/json" \
  -d '{
    "latitude": 33.88,
    "longitude": 10.09,
    "so2": 45.2,
    "nh3": 28.5,
    "pm25": 32.1,
    "temperature": 25.3,
    "humidity": 65.2,
    "wind_speed": 5.2,
    "wind_direction": 180,
    "pressure": 1013.2,
    "source": "sensor_01"
  }'
```

### Example 3: Admin Dashboard Data

```bash
# 1. Get JWT token
TOKEN=$(curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456"}' \
  | jq -r '.token')

# 2. Get dashboard stats
curl http://localhost:8000/api/admin/stats \
  -H "Authorization: Bearer $TOKEN"

# 3. Check data quality
curl http://localhost:8000/api/admin/data/quality \
  -H "Authorization: Bearer $TOKEN"

# 4. View model performance
curl http://localhost:8000/api/admin/model/performance \
  -H "Authorization: Bearer $TOKEN"
```

### Example 4: Import Historical Data

```bash
# Create CSV file
cat > pollution_data.csv << EOF
latitude,longitude,so2,nh3,pm25,temperature,humidity,pressure,wind_speed,wind_direction,timestamp
33.88,10.09,45.2,28.5,32.1,25.3,65.2,1013.2,5.2,180,2025-12-14 10:00:00
33.8815,10.0982,52.3,31.2,35.8,26.1,62.5,1012.8,6.1,175,2025-12-14 11:00:00
EOF

# Get token
TOKEN=$(curl -s -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"123456"}' \
  | jq -r '.token')

# Upload CSV
curl -X POST http://localhost:8000/api/admin/data/upload \
  -H "Authorization: Bearer $TOKEN" \
  -F "file=@pollution_data.csv"
```

### Example 5: Visualize Heatmap

```bash
# Get SO2 heatmap data
curl "http://localhost:8000/api/pollution/heatmap?pollutant=SO2&timeframe=current" \
  | jq '.data[] | {lat, lng, value}'

# Get zone risk levels
curl http://localhost:8000/api/pollution/zones \
  | jq '.zones'
```

---

## 🔗 External Integration

### Python ML API Expected

The system expects a Python ML API at `http://localhost:5000` with these endpoints:

#### POST /predict
```json
{
  "pollutant": "SO2",
  "hours_ahead": 3,
  "features": {
    "temperature": 25.3,
    "humidity": 65.2,
    "pressure": 1013.2,
    "wind_speed": 5.2
  }
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

#### POST /train
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
    "rmse": 4.25,
    "mae": 3.12,
    "r2": 0.92
  },
  "model_version": "v1.3.0"
}
```

---

## 📄 Changelog

### Version 1.0.0 (December 14, 2025)
- ✅ Complete API implementation
- ✅ JWT authentication
- ✅ 27+ endpoints
- ✅ WHO threshold alerts
- ✅ AQI calculation
- ✅ ML predictions integration
- ✅ Heatmap generation
- ✅ Zone analysis
- ✅ CSV import
- ✅ OpenAPI documentation

---

## 📞 Support

**Documentation**: http://localhost:8000/api/doc  
**OpenAPI Spec**: http://localhost:8000/api/doc.json  
**Project Docs**: See PROJECT_DOCUMENTATION.md  
**Implementation Status**: See IMPLEMENTATION_STATUS.md

---

**Last Updated**: December 14, 2025  
**API Version**: 1.0.0  
**Symfony Version**: 7.4
