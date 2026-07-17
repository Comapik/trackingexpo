<?php
require __DIR__ . '/auth.php';
requireAuth();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title>Suivi Expo Photo</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <h1>📸 Suivi de l'expo</h1>
    <nav class="tabs">
        <button class="tab-btn active" data-tab="visitors">Visiteurs</button>
        <button class="tab-btn" data-tab="sales">Ventes</button>
        <button class="tab-btn" data-tab="stats">Statistiques</button>
    </nav>
</header>

<main>
    <section id="tab-visitors" class="tab-panel active">
        <div class="big-counter">
            <span id="today-visitors">0</span>
            <small>visiteurs aujourd'hui</small>
        </div>
        <button id="btn-add-visit" class="btn-huge">+ 1 visiteur</button>
        <div class="group-row">
            <input type="number" id="group-count" min="1" value="2" inputmode="numeric">
            <button id="btn-add-group" class="btn-secondary">Ajouter un groupe</button>
        </div>
        <button id="btn-undo-visit" class="btn-link">Annuler la dernière entrée</button>

        <h2>Météo du jour</h2>
        <div class="weather-grid" id="weather-grid"></div>
    </section>

    <section id="tab-sales" class="tab-panel">
        <div class="products-grid" id="products-grid"></div>
        <div class="sale-form">
            <label>Produit sélectionné : <strong id="selected-product">—</strong></label>
            <div class="sale-inputs">
                <label>Quantité
                    <input type="number" id="sale-quantity" min="1" value="1" inputmode="numeric">
                </label>
                <label>Prix unitaire (€, optionnel)
                    <input type="number" id="sale-price" min="0" step="0.5" inputmode="decimal">
                </label>
            </div>
            <button id="btn-add-sale" class="btn-huge">Enregistrer la vente</button>
            <button id="btn-undo-sale" class="btn-link">Annuler la dernière vente</button>
        </div>
    </section>

    <section id="tab-stats" class="tab-panel">
        <div class="stats-cards">
            <div class="card">
                <span class="card-value" id="stat-total-visitors">0</span>
                <span class="card-label">Visiteurs (total)</span>
            </div>
            <div class="card">
                <span class="card-value" id="stat-today-visitors">0</span>
                <span class="card-label">Visiteurs (aujourd'hui)</span>
            </div>
            <div class="card">
                <span class="card-value" id="stat-total-revenue">0 €</span>
                <span class="card-label">Chiffre d'affaires</span>
            </div>
            <div class="card">
                <span class="card-value" id="stat-today-revenue">0 €</span>
                <span class="card-label">CA aujourd'hui</span>
            </div>
        </div>

        <h2>Ventes par produit</h2>
        <div id="sales-bars" class="bars"></div>

        <h2>Évolution des visiteurs (7 derniers jours)</h2>
        <div id="last7-columns" class="columns"></div>

        <h2>Fréquentation par heure</h2>
        <div class="hour-filter-row">
            <label for="hour-day-select">Afficher</label>
            <select id="hour-day-select">
                <option value="all">Cumul depuis le début</option>
            </select>
        </div>
        <div id="hours-columns" class="columns columns-hours"></div>

        <h2>Historique complet par jour</h2>
        <div id="visits-bars" class="bars"></div>

        <h2>Export des données</h2>
        <div class="export-period" id="export-period">
            <div class="period-choice">
                <label><input type="radio" name="export-period" value="today" checked> Aujourd'hui</label>
                <label><input type="radio" name="export-period" value="day"> Un jour précis</label>
                <label><input type="radio" name="export-period" value="week"> Une semaine</label>
                <label><input type="radio" name="export-period" value="all"> Toute la période</label>
            </div>
            <input type="date" id="export-day-input" class="export-period-input hidden">
            <input type="week" id="export-week-input" class="export-period-input hidden">
        </div>
        <div class="export-row">
            <button id="btn-export-visits" class="btn-export">Exporter visiteurs (CSV)</button>
            <button id="btn-export-sales" class="btn-export">Exporter ventes (CSV)</button>
        </div>
        <div class="export-row">
            <button id="btn-export-day-image" class="btn-export">Exporter la journée (JPG)</button>
            <button id="btn-export-sales-image" class="btn-export">Exporter les ventes (JPG)</button>
        </div>
    </section>
</main>

<canvas id="firework-canvas"></canvas>
<div id="toast"></div>

<script>
window.PRODUCTS = <?= json_encode(PRODUCTS, JSON_UNESCAPED_UNICODE) ?>;
window.WEATHER = <?= json_encode(WEATHER, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="app.js"></script>
</body>
</html>
