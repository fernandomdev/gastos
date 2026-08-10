<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Asuncion');

const DB_HOST = '127.0.0.1';
const DB_NAME = 'gastos_app';
const DB_USER = 'root';
const DB_PASS = '';

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function moneyToFloat(string $value): float
{
    $value = trim($value);
    if ($value === '') {
        return 0.0;
    }

    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    return (float) $value;
}

function normalizeText(string $text): string
{
    $text = trim(mb_strtoupper($text, 'UTF-8'));

    if (class_exists('Normalizer')) {
        $text = Normalizer::normalize($text, Normalizer::FORM_D);
        $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;
    }

    $text = preg_replace('/\s+/', ' ', $text) ?? $text;
    return $text;
}

function parseDateTime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $formats = [
        'd-M-Y H:i:s',
        'd-M-y H:i:s',
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'Y-m-d H:i:s',
        'd-M-Y',
        'd/m/Y',
    ];

    foreach ($formats as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value);
        if ($dt instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();
            if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }

    $ts = strtotime($value);
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }

    return null;
}

function loadRules(PDO $pdo): array
{
    $sql = "
        SELECT
            r.keyword,
            r.priority,
            c.id AS categoria_id,
            c.nombre AS categoria_nombre
        FROM categoria_reglas r
        INNER JOIN categorias c ON c.id = r.categoria_id
        WHERE r.activo = 1
        ORDER BY r.priority DESC, LENGTH(r.keyword) DESC, r.id ASC
    ";

    return $pdo->query($sql)->fetchAll();
}

function detectCategory(string $descripcion, array $rules, ?int $defaultId): ?int
{
    $desc = normalizeText($descripcion);

    foreach ($rules as $rule) {
        $keyword = normalizeText((string) $rule['keyword']);
        if ($keyword !== '' && mb_stripos($desc, $keyword, 0, 'UTF-8') !== false) {
            return (int) $rule['categoria_id'];
        }
    }

    return $defaultId;
}

function currentDayOfMonth(): int
{
    return (int) (new DateTimeImmutable('now'))->format('j');
}

function monthLabel(string $ym): string
{
    [$year, $month] = explode('-', $ym);
    $month = (int) $month;

    $months = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    if ($month < 1 || $month > 12) {
        return $ym;
    }

    return $months[$month] . ' ' . $year;
}

function hexColorForIndex(int $index): string
{
    $palette = [
        '#38bdf8', '#22c55e', '#f59e0b', '#a855f7', '#ef4444',
        '#14b8a6', '#f97316', '#e11d48', '#84cc16', '#06b6d4',
    ];

    return $palette[$index % count($palette)];
}

function buildChartData(PDO $pdo): array
{
    $dayLimit = currentDayOfMonth();
    $stmt = $pdo->query("
        SELECT fecha_movimiento, debito, credito
        FROM movimientos
        ORDER BY fecha_movimiento ASC, id ASC
    ");
    $rows = $stmt->fetchAll();

    if (!$rows) {
        return [
            'labels' => array_map('strval', range(1, $dayLimit)),
            'datasets' => [],
            'meta' => [
                'day_limit' => $dayLimit,
                'label' => 'Sin datos cargados',
            ],
        ];
    }

    $monthBuckets = [];
    foreach ($rows as $row) {
        $dt = new DateTimeImmutable($row['fecha_movimiento']);
        $monthKey = $dt->format('Y-m');
        $day = (int) $dt->format('j');
        $delta = (float) $row['credito'] - (float) $row['debito'];

        if (!isset($monthBuckets[$monthKey])) {
            $monthBuckets[$monthKey] = [
                'label' => monthLabel($monthKey),
                'days' => [],
            ];
        }

        if (!isset($monthBuckets[$monthKey]['days'][$day])) {
            $monthBuckets[$monthKey]['days'][$day] = 0.0;
        }

        $monthBuckets[$monthKey]['days'][$day] += $delta;
    }

    ksort($monthBuckets);

    $labels = array_map('strval', range(1, $dayLimit));
    $datasets = [];

    foreach (array_values($monthBuckets) as $index => $bucket) {
        $running = 0.0;
        $values = [];

        for ($day = 1; $day <= $dayLimit; $day++) {
            if (isset($bucket['days'][$day])) {
                $running += (float) $bucket['days'][$day];
            }

            $values[] = $running;
        }

        $datasets[] = [
            'label' => $bucket['label'],
            'borderColor' => hexColorForIndex($index),
            'backgroundColor' => hexColorForIndex($index),
            'data' => $values,
        ];
    }

    return [
        'labels' => $labels,
        'datasets' => $datasets,
        'meta' => [
            'day_limit' => $dayLimit,
            'label' => 'Día ' . $dayLimit . ' del mes',
        ],
    ];
}

function fetchMovimientos(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            m.id,
            DATE_FORMAT(m.fecha_movimiento, '%d-%m-%Y %H:%i:%s') AS fecha_movimiento,
            m.nro_comprobante,
            m.descripcion,
            m.moneda,
            m.debito,
            m.credito,
            m.monto_neto,
            m.sentido,
            COALESCE(c.nombre, 'Sin categoría') AS categoria,
            m.imported_at
        FROM movimientos m
        LEFT JOIN categorias c ON c.id = m.categoria_id
        ORDER BY m.fecha_movimiento DESC, m.id DESC
    ");

    return $stmt->fetchAll();
}

function fetchStats(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            COUNT(*) AS total_movimientos,
            COALESCE(SUM(debito), 0) AS total_gastos,
            COALESCE(SUM(credito), 0) AS total_ingresos,
            COALESCE(SUM(monto_neto), 0) AS saldo_neto
        FROM movimientos
    ");

    $row = $stmt->fetch() ?: [];

    return [
        'total_movimientos' => (int) ($row['total_movimientos'] ?? 0),
        'total_gastos' => (float) ($row['total_gastos'] ?? 0),
        'total_ingresos' => (float) ($row['total_ingresos'] ?? 0),
        'saldo_neto' => (float) ($row['saldo_neto'] ?? 0),
    ];
}

function readCsvRows(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir el archivo CSV.');
    }

    $header = null;
    $rows = [];

    while (($data = fgetcsv($handle, 0, ';', '"')) !== false) {
        if ($data === [null] || $data === false) {
            continue;
        }

        if ($header === null) {
            $header = array_map(static function ($value) {
                return normalizeText((string) $value);
            }, $data);
            continue;
        }

        $mapped = [];
        foreach ($header as $index => $name) {
            $mapped[$name] = $data[$index] ?? '';
        }

        $rows[] = $mapped;
    }

    fclose($handle);
    return $rows;
}

try {
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'dashboard');
    $db = pdo();

    if ($action === 'dashboard') {
        jsonResponse([
            'ok' => true,
            'stats' => fetchStats($db),
            'movimientos' => fetchMovimientos($db),
            'chart' => buildChartData($db),
        ]);
    }

    if ($action === 'movimientos') {
        jsonResponse([
            'ok' => true,
            'movimientos' => fetchMovimientos($db),
        ]);
    }

    if ($action === 'chart') {
        jsonResponse([
            'ok' => true,
            'chart' => buildChartData($db),
        ]);
    }

    if ($action !== 'importar') {
        jsonResponse([
            'ok' => false,
            'mensaje' => 'Acción no válida.',
        ], 400);
    }

    if (!isset($_FILES['archivos'])) {
        jsonResponse([
            'ok' => false,
            'mensaje' => 'No se recibieron archivos.',
        ], 400);
    }

    $rules = loadRules($db);
    $defaultCategoryStmt = $db->prepare("SELECT id FROM categorias WHERE nombre = 'Otros / sin clasificar' LIMIT 1");
    $defaultCategoryStmt->execute();
    $defaultCategoryId = $defaultCategoryStmt->fetchColumn();
    $defaultCategoryId = $defaultCategoryId !== false ? (int) $defaultCategoryId : null;

    $files = $_FILES['archivos'];
    $countFiles = is_array($files['name']) ? count($files['name']) : 0;

    if ($countFiles === 0) {
        jsonResponse([
            'ok' => false,
            'mensaje' => 'Subí al menos un CSV.',
        ], 400);
    }

    $insertStmt = $db->prepare("
        INSERT INTO movimientos (
            source_file,
            row_hash,
            fecha_movimiento,
            raw_fecha,
            nro_comprobante,
            descripcion,
            moneda,
            debito,
            credito,
            monto_neto,
            sentido,
            categoria_id
        ) VALUES (
            :source_file,
            :row_hash,
            :fecha_movimiento,
            :raw_fecha,
            :nro_comprobante,
            :descripcion,
            :moneda,
            :debito,
            :credito,
            :monto_neto,
            :sentido,
            :categoria_id
        )
    ");

    $totalInserted = 0;
    $totalDuplicates = 0;
    $processedFiles = 0;

    for ($i = 0; $i < $countFiles; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpPath = $files['tmp_name'][$i] ?? '';
        $originalName = (string) ($files['name'][$i] ?? 'archivo.csv');

        if (!is_uploaded_file($tmpPath)) {
            continue;
        }

        $rows = readCsvRows($tmpPath);
        if (!$rows) {
            continue;
        }

        $db->beginTransaction();

        try {
            foreach ($rows as $row) {
                $fechaRaw = trim((string) ($row['FECHA'] ?? $row['Fecha'] ?? $row['fecha'] ?? ''));
                $nro = trim((string) ($row['NRO_COMPROBANTE'] ?? $row['Nro Comprobante'] ?? $row['nro_comprobante'] ?? ''));
                $descripcion = trim((string) ($row['DESCRIPCION'] ?? $row['Descripcion'] ?? $row['descripcion'] ?? ''));
                $moneda = trim((string) ($row['MONEDA'] ?? $row['moneda'] ?? 'PYG'));
                $debito = moneyToFloat((string) ($row['DEBITO'] ?? $row['debito'] ?? '0'));
                $credito = moneyToFloat((string) ($row['CREDITO'] ?? $row['credito'] ?? '0'));

                if ($fechaRaw === '' || $descripcion === '' || ($debito <= 0 && $credito <= 0)) {
                    continue;
                }

                $fechaMov = parseDateTime($fechaRaw);
                if ($fechaMov === null) {
                    continue;
                }

                $categoriaId = detectCategory($descripcion, $rules, $defaultCategoryId);
                $sentido = $credito > 0 && $debito <= 0 ? 'CREDITO' : 'DEBITO';
                $rowHash = md5(implode('|', [
                    $originalName,
                    $fechaRaw,
                    $nro,
                    $descripcion,
                    $moneda,
                    number_format($debito, 2, '.', ''),
                    number_format($credito, 2, '.', ''),
                ]));

                try {
                    $insertStmt->execute([
                        ':source_file' => $originalName,
                        ':row_hash' => $rowHash,
                        ':fecha_movimiento' => $fechaMov,
                        ':raw_fecha' => $fechaRaw,
                        ':nro_comprobante' => $nro,
                        ':descripcion' => $descripcion,
                        ':moneda' => $moneda ?: 'PYG',
                        ':debito' => $debito,
                        ':credito' => $credito,
                        ':monto_neto' => $credito - $debito,
                        ':sentido' => $sentido,
                        ':categoria_id' => $categoriaId,
                    ]);
                    $totalInserted++;
                } catch (PDOException $e) {
                    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                        $totalDuplicates++;
                        continue;
                    }
                    throw $e;
                }
            }

            $db->commit();
            $processedFiles++;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    jsonResponse([
        'ok' => true,
        'mensaje' => 'Importación completada.',
        'resumen' => [
            'archivos' => $processedFiles,
            'insertados' => $totalInserted,
            'duplicados' => $totalDuplicates,
        ],
        'stats' => fetchStats($db),
        'movimientos' => fetchMovimientos($db),
        'chart' => buildChartData($db),
    ]);
} catch (Throwable $e) {
    jsonResponse([
        'ok' => false,
        'mensaje' => 'Error: ' . $e->getMessage(),
    ], 500);
}
