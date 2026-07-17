<?php
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
requireAuthApi();

try {
    $pdo = getPDO();
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'add_visit':
            $count = max(1, (int)($_POST['count'] ?? 1));
            $stmt = $pdo->prepare("INSERT INTO visits (count) VALUES (?)");
            $stmt->execute([$count]);
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'undo_visit':
            $pdo->exec("DELETE FROM visits ORDER BY id DESC LIMIT 1");
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'add_sale':
            $product = $_POST['product'] ?? '';
            if (!array_key_exists($product, PRODUCTS)) {
                throw new Exception('Produit invalide');
            }
            $quantity = max(1, (int)($_POST['quantity'] ?? 1));
            $price = $_POST['price'] === '' || !isset($_POST['price']) ? null : (float)$_POST['price'];
            $stmt = $pdo->prepare("INSERT INTO sales (product, quantity, price) VALUES (?, ?, ?)");
            $stmt->execute([$product, $quantity, $price]);
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'undo_sale':
            $pdo->exec("DELETE FROM sales ORDER BY id DESC LIMIT 1");
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'set_weather':
            $weather = $_POST['weather'] ?? '';
            if (!array_key_exists($weather, WEATHER)) {
                throw new Exception('Météo invalide');
            }
            $stmt = $pdo->prepare("INSERT INTO day_weather (day, weather) VALUES (CURDATE(), ?)
                ON DUPLICATE KEY UPDATE weather = VALUES(weather)");
            $stmt->execute([$weather]);
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'stats':
            echo json_encode(['ok' => true, 'stats' => getStats($pdo)]);
            break;

        case 'period_stats':
            $from = $_REQUEST['from'] ?? '';
            $to = $_REQUEST['to'] ?? $from;
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
                throw new Exception('Période invalide');
            }
            echo json_encode(['ok' => true, 'period' => getPeriodStats($pdo, $from, $to)]);
            break;

        case 'hours':
            $day = $_REQUEST['day'] ?? 'all';
            if ($day === 'all' || $day === '') {
                $rows = $pdo->query("
                    SELECT HOUR(visited_at) AS h, SUM(count) AS total
                    FROM visits GROUP BY HOUR(visited_at)
                ")->fetchAll(PDO::FETCH_KEY_PAIR);
            } else {
                $stmt = $pdo->prepare("
                    SELECT HOUR(visited_at) AS h, SUM(count) AS total
                    FROM visits WHERE DATE(visited_at) = ? GROUP BY HOUR(visited_at)
                ");
                $stmt->execute([$day]);
                $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            }
            $byHour = [];
            for ($h = 0; $h < 24; $h++) {
                $byHour[] = ['hour' => $h, 'total' => (int)($rows[$h] ?? 0)];
            }
            echo json_encode(['ok' => true, 'byHour' => $byHour]);
            break;

        default:
            throw new Exception('Action inconnue');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function getStats(PDO $pdo): array
{
    $totalVisitors = (int)$pdo->query("SELECT COALESCE(SUM(count),0) FROM visits")->fetchColumn();
    $todayVisitors = (int)$pdo->query("SELECT COALESCE(SUM(count),0) FROM visits WHERE DATE(visited_at) = CURDATE()")->fetchColumn();

    $weatherByDay = $pdo->query("SELECT day, weather FROM day_weather")->fetchAll(PDO::FETCH_KEY_PAIR);
    $todayKey = date('Y-m-d');
    $todayWeather = $weatherByDay[$todayKey] ?? null;

    // Historique complet (toute la durée de l'expo), pour export / vue d'ensemble.
    $byDayRows = $pdo->query("
        SELECT DATE(visited_at) AS day, SUM(count) AS total
        FROM visits GROUP BY DATE(visited_at) ORDER BY day ASC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $byDay = [];
    foreach ($byDayRows as $day => $total) {
        $byDay[] = ['day' => $day, 'total' => (int)$total, 'weather' => $weatherByDay[$day] ?? null];
    }

    // Évolution sur les 7 derniers jours, zéro-remplie même sans visite.
    $last7Rows = $pdo->query("
        SELECT DATE(visited_at) AS day, SUM(count) AS total
        FROM visits
        WHERE visited_at >= (CURDATE() - INTERVAL 6 DAY)
        GROUP BY DATE(visited_at)
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $last7Days = [];
    for ($i = 6; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-$i day"));
        $last7Days[] = ['day' => $day, 'total' => (int)($last7Rows[$day] ?? 0), 'weather' => $weatherByDay[$day] ?? null];
    }

    // Fréquentation par heure de la journée (toute la durée de l'expo), zéro-remplie.
    $hourRows = $pdo->query("
        SELECT HOUR(visited_at) AS h, SUM(count) AS total
        FROM visits GROUP BY HOUR(visited_at)
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $byHour = [];
    for ($h = 0; $h < 24; $h++) {
        $byHour[] = ['hour' => $h, 'total' => (int)($hourRows[$h] ?? 0)];
    }

    // Fréquentation par heure, aujourd'hui uniquement (pour l'export du jour).
    $todayHourRows = $pdo->query("
        SELECT HOUR(visited_at) AS h, SUM(count) AS total
        FROM visits WHERE DATE(visited_at) = CURDATE() GROUP BY HOUR(visited_at)
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    $todayByHour = [];
    for ($h = 0; $h < 24; $h++) {
        $todayByHour[] = ['hour' => $h, 'total' => (int)($todayHourRows[$h] ?? 0)];
    }

    $rows = $pdo->query("SELECT product, SUM(quantity) AS qty, COALESCE(SUM(quantity * price),0) AS revenue FROM sales GROUP BY product")->fetchAll(PDO::FETCH_ASSOC);
    $sales = [];
    foreach (PRODUCTS as $key => $label) {
        $sales[$key] = ['label' => $label, 'qty' => 0, 'revenue' => 0];
    }
    foreach ($rows as $r) {
        if (isset($sales[$r['product']])) {
            $sales[$r['product']]['qty'] = (int)$r['qty'];
            $sales[$r['product']]['revenue'] = (float)$r['revenue'];
        }
    }

    $totalSalesQty = array_sum(array_column($sales, 'qty'));
    $totalRevenue = array_sum(array_column($sales, 'revenue'));

    $todayRevenue = (float)$pdo->query("
        SELECT COALESCE(SUM(quantity * price),0) FROM sales WHERE DATE(sold_at) = CURDATE()
    ")->fetchColumn();
    $todaySalesQty = (int)$pdo->query("
        SELECT COALESCE(SUM(quantity),0) FROM sales WHERE DATE(sold_at) = CURDATE()
    ")->fetchColumn();

    // Ventes par produit, aujourd'hui uniquement (pour l'export des ventes du jour).
    $todaySalesRows = $pdo->query("
        SELECT product, SUM(quantity) AS qty, COALESCE(SUM(quantity * price),0) AS revenue
        FROM sales WHERE DATE(sold_at) = CURDATE() GROUP BY product
    ")->fetchAll(PDO::FETCH_ASSOC);
    $todaySales = [];
    foreach (PRODUCTS as $key => $label) {
        $todaySales[$key] = ['label' => $label, 'qty' => 0, 'revenue' => 0];
    }
    foreach ($todaySalesRows as $r) {
        if (isset($todaySales[$r['product']])) {
            $todaySales[$r['product']]['qty'] = (int)$r['qty'];
            $todaySales[$r['product']]['revenue'] = (float)$r['revenue'];
        }
    }

    return [
        'totalVisitors' => $totalVisitors,
        'todayVisitors' => $todayVisitors,
        'todayWeather' => $todayWeather,
        'byDay' => $byDay,
        'last7Days' => $last7Days,
        'byHour' => $byHour,
        'todayByHour' => $todayByHour,
        'sales' => $sales,
        'todaySales' => $todaySales,
        'totalSalesQty' => $totalSalesQty,
        'totalRevenue' => $totalRevenue,
        'todaySalesQty' => $todaySalesQty,
        'todayRevenue' => $todayRevenue,
    ];
}

// Stats agrégées sur une période arbitraire (un jour si $from === $to, sinon une semaine/plage),
// utilisées pour les exports CSV et JPG paramétrables.
function getPeriodStats(PDO $pdo, string $from, string $to): array
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(count),0) FROM visits WHERE DATE(visited_at) BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    $visitors = (int)$stmt->fetchColumn();

    $weatherByDay = $pdo->query("SELECT day, weather FROM day_weather")->fetchAll(PDO::FETCH_KEY_PAIR);
    $weather = $from === $to ? ($weatherByDay[$from] ?? null) : null;

    $stmt = $pdo->prepare("
        SELECT DATE(visited_at) AS day, SUM(count) AS total
        FROM visits WHERE DATE(visited_at) BETWEEN ? AND ? GROUP BY DATE(visited_at)
    ");
    $stmt->execute([$from, $to]);
    $byDayRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $byDay = [];
    $cursor = new DateTime($from);
    $end = new DateTime($to);
    while ($cursor <= $end) {
        $d = $cursor->format('Y-m-d');
        $byDay[] = ['day' => $d, 'total' => (int)($byDayRows[$d] ?? 0), 'weather' => $weatherByDay[$d] ?? null];
        $cursor->modify('+1 day');
    }

    $stmt = $pdo->prepare("
        SELECT HOUR(visited_at) AS h, SUM(count) AS total
        FROM visits WHERE DATE(visited_at) BETWEEN ? AND ? GROUP BY HOUR(visited_at)
    ");
    $stmt->execute([$from, $to]);
    $hourRows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $byHour = [];
    for ($h = 0; $h < 24; $h++) {
        $byHour[] = ['hour' => $h, 'total' => (int)($hourRows[$h] ?? 0)];
    }

    $stmt = $pdo->prepare("
        SELECT product, SUM(quantity) AS qty, COALESCE(SUM(quantity * price),0) AS revenue
        FROM sales WHERE DATE(sold_at) BETWEEN ? AND ? GROUP BY product
    ");
    $stmt->execute([$from, $to]);
    $sales = [];
    foreach (PRODUCTS as $key => $label) {
        $sales[$key] = ['label' => $label, 'qty' => 0, 'revenue' => 0];
    }
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($sales[$r['product']])) {
            $sales[$r['product']]['qty'] = (int)$r['qty'];
            $sales[$r['product']]['revenue'] = (float)$r['revenue'];
        }
    }

    return [
        'from' => $from,
        'to' => $to,
        'visitors' => $visitors,
        'weather' => $weather,
        'byDay' => $byDay,
        'byHour' => $byHour,
        'sales' => $sales,
        'salesQty' => array_sum(array_column($sales, 'qty')),
        'revenue' => array_sum(array_column($sales, 'revenue')),
    ];
}
