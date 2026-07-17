<?php
require __DIR__ . '/db.php';

$pdo = getPDO();
$type = $_GET['type'] ?? 'visits';

if (!in_array($type, ['visits', 'sales'], true)) {
    http_response_code(400);
    exit('Type invalide');
}

// Filtre de période optionnel (jour précis ou plage de dates pour une semaine).
// Sans from/to : export de tout l'historique (comportement d'origine).
$isDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$from = $isDate($_GET['from'] ?? null) ? $_GET['from'] : null;
$to = $isDate($_GET['to'] ?? null) ? $_GET['to'] : $from;
if ($from !== null && $from > $to) {
    [$from, $to] = [$to, $from];
}

$periodSuffix = $from !== null ? ($from === $to ? "_$from" : "_{$from}_au_{$to}") : '';
$filename = ($type === 'visits' ? 'visiteurs' : 'ventes') . $periodSuffix . '_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel

if ($type === 'visits') {
    $weatherByDay = $pdo->query("SELECT day, weather FROM day_weather")->fetchAll(PDO::FETCH_KEY_PAIR);
    fputcsv($out, ['id', 'date', 'heure', 'nombre_visiteurs', 'meteo'], ';', '"', '\\');
    if ($from !== null) {
        $stmt = $pdo->prepare("SELECT id, visited_at, count FROM visits WHERE DATE(visited_at) BETWEEN ? AND ? ORDER BY visited_at ASC");
        $stmt->execute([$from, $to]);
    } else {
        $stmt = $pdo->query("SELECT id, visited_at, count FROM visits ORDER BY visited_at ASC");
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ts = strtotime($row['visited_at']);
        $day = date('Y-m-d', $ts);
        $weather = isset($weatherByDay[$day]) ? (WEATHER[$weatherByDay[$day]]['label'] ?? $weatherByDay[$day]) : '';
        fputcsv($out, [$row['id'], $day, date('H:i:s', $ts), $row['count'], $weather], ';', '"', '\\');
    }
} else {
    fputcsv($out, ['id', 'date', 'heure', 'produit', 'quantite', 'prix_unitaire', 'total'], ';', '"', '\\');
    if ($from !== null) {
        $stmt = $pdo->prepare("SELECT id, sold_at, product, quantity, price FROM sales WHERE DATE(sold_at) BETWEEN ? AND ? ORDER BY sold_at ASC");
        $stmt->execute([$from, $to]);
    } else {
        $stmt = $pdo->query("SELECT id, sold_at, product, quantity, price FROM sales ORDER BY sold_at ASC");
    }
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $ts = strtotime($row['sold_at']);
        $label = PRODUCTS[$row['product']] ?? $row['product'];
        $total = $row['price'] !== null ? $row['price'] * $row['quantity'] : '';
        fputcsv($out, [$row['id'], date('Y-m-d', $ts), date('H:i:s', $ts), $label, $row['quantity'], $row['price'], $total], ';', '"', '\\');
    }
}

fclose($out);
