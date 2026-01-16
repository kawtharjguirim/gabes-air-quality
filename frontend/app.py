import streamlit as st
import pandas as pd
import plotly.express as px
import folium
from streamlit_folium import st_folium
from datetime import datetime, timedelta

# === CONFIGURATION DE LA PAGE ===
st.set_page_config(
    page_title="Pollution Air - Gabès",
    page_icon="🌍",
    layout="wide"
)

# === TITRE ===
st.title("📊 Tableau de Bord : Prédiction de la Pollution de l'Air à Gabès")
st.markdown("Système de prédiction en temps réel des concentrations de SO₂ et NH₃")

# === DONNÉES MOCKÉES (à remplacer plus tard par vraies prédictions) ===
# Heures futures
future_hours = [f"H+{i}" for i in range(1, 7)]
so2_pred = [18, 25, 40, 60, 85, 110]  # µg/m³
nh3_pred = [8, 12, 18, 22, 28, 35]    # µg/m³

df_future = pd.DataFrame({
    "Heure": future_hours,
    "SO₂ (µg/m³)": so2_pred,
    "NH₃ (µg/m³)": nh3_pred
})

# Données historiques (dernières 10h)
now = datetime.now()
dates_hist = [now - timedelta(hours=i) for i in range(10)][::-1]
pred_so2_hist = [20, 22, 25, 30, 35, 40, 50, 60, 75, 85]
real_so2_hist = [21, 23, 27, 32, 38, 42, 52, 65, 80, 90]

df_hist = pd.DataFrame({
    "Date": dates_hist,
    "Prédiction SO₂": pred_so2_hist,
    "Réalité SO₂": real_so2_hist
})

# Niveau d'alerte actuel (basé sur dernière prédiction)
current_so2 = so2_pred[0]  # H+1
if current_so2 < 20:
    alert = ("🟢 Vert", "green")
elif current_so2 < 50:
    alert = ("🟡 Jaune", "yellow")
elif current_so2 < 100:
    alert = ("🟠 Orange", "orange")
else:
    alert = ("🔴 Rouge", "red")

# === SECTION 1 : CARTE DE GABÈS ===
st.header("📍 Carte Interactive de Gabès")
m = folium.Map(location=[33.8833, 10.1000], zoom_start=11)

# Zones critiques (exemples basés sur le complexe chimique)
folium.Marker(
    [33.8750, 10.0900],
    popup="Complexe Chimique - Zone Industrielle",
    icon=folium.Icon(color="red", icon="industry", prefix="fa")
).add_to(m)

folium.Marker(
    [33.8900, 10.1100],
    popup="Zone Résidentielle Nord",
    icon=folium.Icon(color="blue", icon="home", prefix="fa")
).add_to(m)

folium.Circle(
    location=[33.8750, 10.0900],
    radius=1500,
    color="red",
    fill=True,
    fillColor="red",
    fillOpacity=0.1
).add_to(m)

st_folium(m, width=800, height=500)

# === SECTION 2 : PRÉDICTIONS 6H ===
st.header("📈 Prédictions des 6 Prochaines Heures")
fig_pred = px.line(
    df_future,
    x="Heure",
    y=["SO₂ (µg/m³)", "NH₃ (µg/m³)"],
    title="Concentrations prédites (SO₂ et NH₃)",
    markers=True
)
fig_pred.update_layout(yaxis_title="Concentration (µg/m³)")
st.plotly_chart(fig_pred, use_container_width=True)

# === SECTION 3 : NIVEAU D'ALERTE ACTUEL ===
st.header("🚨 Niveau d'Alerte Actuel")
st.markdown(f"### {alert[0]}")
st.markdown(
    f"<div style='background-color:{alert[1]}; padding:15px; border-radius:10px; text-align:center; color:black;'>"
    f"<b>Concentration SO₂ estimée dans 1h : {current_so2} µg/m³</b>"
    "</div>",
    unsafe_allow_html=True
)

# === SECTION 4 : HISTORIQUE PRÉDICTIONS VS RÉALITÉ ===
st.header("📉 Historique : Prédictions vs Réalité (Dernières 10h)")
fig_hist = px.line(
    df_hist,
    x="Date",
    y=["Prédiction SO₂", "Réalité SO₂"],
    title="Comparaison des valeurs prédites et réelles",
    markers=True
)
fig_hist.update_layout(yaxis_title="SO₂ (µg/m³)")
st.plotly_chart(fig_hist, use_container_width=True)

# === SECTION 5 : SIMULATION D'ALERTE ===
st.header("⚙️ Simulation d'Alerte Sanitaire")
if st.button("⚠️ Simuler une alerte rouge (SO₂ > 100 µg/m³)"):
    st.error("🚨 ALERTE SANITAIRE ROUGE !")
    st.markdown("""
    **Mesures recommandées :**
    - Fermer portes et fenêtres
    - Éviter les activités extérieures
    - Personnes sensibles : rester à l'intérieur
    - Autorités locales informées automatiquement
    """)

# === FOOTER ===
st.markdown("---")
st.caption("Projet réalisé dans le cadre du système de prédiction de pollution de l'air à Gabès • Données simulées à titre de démonstration")