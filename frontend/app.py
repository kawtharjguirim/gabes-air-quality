# app.py
import streamlit as st
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
import joblib
from tensorflow.keras.models import load_model
import folium
from streamlit_folium import folium_static
import plotly.graph_objects as go

# -----------------------------
# Configuration
# -----------------------------
st.set_page_config(page_title="🌿 AlertAir – Gabès", layout="wide")
st.title("🌿 Système d’Alerte Sanitaire – Qualité de l’Air à Gabès")

# Seuils OMS adaptés (µg/m³)
SO2_ALERT_YELLOW = 100
SO2_ALERT_RED = 250

# -----------------------------
# 1. CHARGEMENT DES DONNÉES RÉCENTES (simulées à partir du CSV fourni)
# -----------------------------
@st.cache_data
def load_recent_data():
    df = pd.read_csv("gabes_air_quality_synthetic(1).csv")
    df = df.rename(columns={
        'datetime': 'timestamp',
        'temperature_C': 'temp',
        'humidity_%': 'humidity',
        'wind_speed_m_s': 'wind_speed',
        'wind_dir_deg': 'wind_dir',
        'pressure_hPa': 'pressure',
        'precip_mm': 'precip',
        'SO2_ug_m3': 'so2', # Renommé pour le tracé
        'NH3_ug_m3': 'nh3'  # Renommé pour le tracé
    })
    df["timestamp"] = pd.to_datetime(df["timestamp"])
    df = df.sort_values("timestamp").reset_index(drop=True)
    return df

df = load_recent_data()
last_6_rows = df.tail(6).copy()

# -----------------------------
# 2. CHARGEMENT DU MODÈLE ET DU SCALER
# -----------------------------
try:
    model = load_model("best_model_so2_3h_lstm.h5", compile=False)
    # Re-compile the model with the original optimizer and loss
    # Assuming learning_rate=0.001 and loss='mse' were used during training
    from tensorflow.keras.optimizers import Adam
    from tensorflow.keras.losses import MeanSquaredError
    model.compile(optimizer=Adam(learning_rate=0.001), loss=MeanSquaredError())
    scaler = joblib.load("scaler_so2_3h.pkl")
except FileNotFoundError:
    st.error("⚠️ Modèle ou scaler non trouvé. Veuillez exécuter d'abord le script de comparaison.")
    st.stop()

# Colonnes utilisées pour la prédiction (doivent correspondre à l'entraînement)
feature_cols = [
    "temp", "humidity", "wind_speed", "wind_dir", "pressure", "precip",
    "hour", "dayofweek", "is_weekend",
    "SO2_lag1", "SO2_lag2", "NH3_lag1", "NH3_lag2",
    "industrial_index", "traffic_index"
]

# Préparer les données pour la prédiction
X_last = last_6_rows[feature_cols].values
X_scaled = scaler.transform(X_last)
X_seq = X_scaled.reshape(1, 6, len(feature_cols))

# Prédire
prediction = model.predict(X_seq)[0][0]

# -----------------------------
# 3. INTERFACE UTILISATEUR
# -----------------------------

# Sidebar : carte de Gabès
with st.sidebar:
    st.subheader("📍 Localisation")
    m = folium.Map(location=[33.88, 10.11], zoom_start=11)
    folium.Marker(
        [33.88, 10.11],
        popup="Gabès – Zone industrielle (GCT)",
        tooltip="Pollution industrielle",
        icon=folium.Icon(color="red", icon="info-sign")
    ).add_to(m)
    folium_static(m)

    st.markdown("---")
    st.write("**Dernière mise à jour**")
    st.write(f"{df['timestamp'].iloc[-1].strftime('%Y-%m-%d %H:%M')}")

# Alertes sanitaires
st.subheader("🚨 Alerte Sanitaire (SO₂)")
col1, col2 = st.columns([1, 2])
with col1:
    if prediction >= SO2_ALERT_RED:
        st.error(f"🔴 **ALERTE SANITAIRE**\n\nSO₂ prévu à **{prediction:.1f} µg/m³** dans 3h")
    elif prediction >= SO2_ALERT_YELLOW:
        st.warning(f"🟠 **Vigilance accrue**\n\nSO₂ prévu à **{prediction:.1f} µg/m³** dans 3h")
    else:
        st.success(f"🟢 **Qualité de l’air normale**\n\nSO₂ prévu à **{prediction:.1f} µg/m³** dans 3h")

with col2:
    # Légende seuils
    st.markdown("""
    **Seuils de référence (OMS adaptés)** :
    - 🟢 Normal : < 100 µg/m³
    - 🟠 Vigilance : ≥ 100 µg/m³
    - 🔴 Alerte : ≥ 250 µg/m³
    """)

# Graphique historique + prévision
st.subheader("📊 Évolution du SO₂ (observé et prévu)")

# Données pour le graphique
plot_df = df.tail(24).copy()  # dernières 24h
future_time = plot_df["timestamp"].iloc[-1] + timedelta(hours=3)
plot_df = pd.concat([
    plot_df[["timestamp", "so2"]],
    pd.DataFrame({"timestamp": [future_time], "so2": [np.nan]})
], ignore_index=True)

fig = go.Figure()
fig.add_trace(go.Scatter(
    x=plot_df["timestamp"][:-1],
    y=plot_df["so2"][:-1],
    mode='lines+markers',
    name="Observé (dernières 24h)",
    line=dict(color='blue')
))
fig.add_trace(go.Scatter(
    x=[future_time],
    y=[prediction],
    mode='markers',
    name="Prédit (dans 3h)",
    marker=dict(color='red', size=10)
))
fig.update_layout(
    xaxis_title="Heure",
    yaxis_title="SO₂ (µg/m³)",
    hovermode="x unified"
)
st.plotly_chart(fig, width='stretch')

# Informations supplémentaires
st.info("ℹ️ Ce système est en phase de démonstration. Les alertes ne sont pas envoyées réellement (conformément au cahier des charges).")
