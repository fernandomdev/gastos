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

function requestData(): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return $_GET;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw ?: '', true);
        return is_array($json) ? $json : [];
    }

    return $_POST;
}

function normalizePlatform(string $platform): string
{
    $platform = trim($platform);

    $map = [
        'credito amigo' => 'Crédito Amigo',
        'crédito amigo' => 'Crédito Amigo',
        'ueno' => 'Ueno',
        'itaú' => 'Itaú',
        'itau' => 'Itaú',
    ];

    $key = mb_strtolower($platform, 'UTF-8');
    return $map[$key] ?? '';
}

function validateLoanInput(array $data): array
{
    $platform = normalizePlatform((string) ($data['plataforma'] ?? ''));
    $platformId = trim((string) ($data['id_plataforma'] ?? ''));
    $total = filter_var($data['cuotas_totales'] ?? null, FILTER_VALIDATE_INT);
    $paid = filter_var($data['cuotas_cumplidas'] ?? null, FILTER_VALIDATE_INT);
    $monthlyRaw = (string) ($data['monto_mensual'] ?? '');

    $monthlyRaw = str_replace(['.', ' ', ','], ['', '', '.'], $monthlyRaw);
    $monthly = is_numeric($monthlyRaw) ? (float) $monthlyRaw : 0.0;

    if ($platform === '') {
        throw new InvalidArgumentException('La plataforma seleccionada no es válida.');
    }

    if (!preg_match('/^\d{3}$/', $platformId)) {
        throw new InvalidArgumentException('El ID de plataforma debe tener exactamente 3 números (por ejemplo, 007).');
    }

    if ($total === false || $total < 1 || $total > 999) {
        throw new InvalidArgumentException('La cantidad de cuotas debe estar entre 1 y 999.');
    }

    if ($paid === false || $paid < 0 || $paid > $total) {
        throw new InvalidArgumentException('Las cuotas cumplidas deben estar entre 0 y el total de cuotas.');
    }

    if (!is_finite($monthly) || $monthly <= 0 || $monthly > 999999999999.99) {
        throw new InvalidArgumentException('El monto mensual debe ser mayor a cero.');
    }

    return [
        'plataforma' => $platform,
        'id_plataforma' => $platformId,
        'cuotas_totales' => $total,
        'cuotas_cumplidas' => $paid,
        'monto_mensual' => round($monthly, 2),
    ];
}

function currentPeriod(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m');
}

function paymentWindowOpen(): bool
{
    $day = (int) (new DateTimeImmutable('now'))->format('j');
    return $day >= 1 && $day <= 10;
}

function enrichLoans(array $rows, array $paidPeriods): array
{
    $period = currentPeriod();
    $windowOpen = paymentWindowOpen();

    foreach ($rows as &$row) {
        $total = (int) $row['cuotas_totales'];
        $paid = (int) $row['cuotas_cumplidas'];
        $remaining = max(0, $total - $paid);
        $progress = $total > 0 ? round(($paid / $total) * 100, 1) : 0;
        $paidThisMonth = !empty($paidPeriods[(int) $row['id']]);

        $status = 'Pendiente';
        $action = 'mark';

        if ($paid >= $total) {
            $status = 'Finalizado';
            $action = 'done';
        } elseif ($paidThisMonth) {
            $status = 'Abonado ' . $period;
            $action = 'paid';
        } elseif (!$windowOpen) {
            $day = (int) (new DateTimeImmutable('now'))->format('j');
            $status = $day > 10 ? 'Vencido ' . $period : 'Aún no disponible';
            $action = 'closed';
        }

        $row['cuotas_totales'] = $total;
        $row['cuotas_cumplidas'] = $paid;
        $row['cuotas_restantes'] = $remaining;
        $row['progreso'] = $progress;
        $row['monto_mensual'] = (float) $row['monto_mensual'];
        $row['abonado_mes_actual'] = $paidThisMonth;
        $row['accion_mes'] = $action;
        $row['estado'] = $status;
        $row['periodo_actual'] = $period;
    }
    unset($row);

    return $rows;
}

function listLoans(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            p.id,
            p.plataforma,
            p.id_plataforma,
            p.cuotas_totales,
            p.cuotas_cumplidas,
            p.monto_mensual,
            p.creado_en,
            p.actualizado_en
        FROM prestamos p
        ORDER BY
            CASE p.plataforma
                WHEN 'Crédito Amigo' THEN 1
                WHEN 'Ueno' THEN 2
                WHEN 'Itaú' THEN 3
                ELSE 9
            END,
            p.id_plataforma ASC,
            p.id ASC
    ");
    $rows = $stmt->fetchAll();

    $period = currentPeriod();
    $paymentStmt = $pdo->prepare('SELECT prestamo_id FROM prestamos_pagos WHERE periodo = ?');
    $paymentStmt->execute([$period]);
    $paidPeriods = [];
    foreach ($paymentStmt->fetchAll(PDO::FETCH_COLUMN) as $loanId) {
        $paidPeriods[(int) $loanId] = true;
    }

    return enrichLoans($rows, $paidPeriods);
}

function dashboard(PDO $pdo): array
{
    $loans = listLoans($pdo);

    $totalLoans = count($loans);
    $activeLoans = 0;
    $paidMonth = 0;
    $monthlyTotal = 0.0;
    $remainingAmount = 0.0;
    $totalInstallments = 0;
    $completedInstallments = 0;

    foreach ($loans as $loan) {
        if ((int) $loan['cuotas_restantes'] > 0) {
            $activeLoans++;
        }
        if (!empty($loan['abonado_mes_actual'])) {
            $paidMonth++;
        }
        if ((int) $loan['cuotas_restantes'] > 0) {
            $monthlyTotal += (float) $loan['monto_mensual'];
            $remainingAmount += (float) $loan['cuotas_restantes'] * (float) $loan['monto_mensual'];
        }
        $totalInstallments += (int) $loan['cuotas_totales'];
        $completedInstallments += (int) $loan['cuotas_cumplidas'];
    }

    $overallProgress = $totalInstallments > 0
        ? round(($completedInstallments / $totalInstallments) * 100, 1)
        : 0;

    $day = (int) (new DateTimeImmutable('now'))->format('j');

    return [
        'periodo_actual' => currentPeriod(),
        'dia_actual' => $day,
        'ventana_pago_abierta' => paymentWindowOpen(),
        'total_prestamos' => $totalLoans,
        'prestamos_activos' => $activeLoans,
        'abonados_mes' => $paidMonth,
        'monto_mensual_total' => round($monthlyTotal, 2),
        'monto_restante_estimado' => round($remainingAmount, 2),
        'cuotas_totales' => $totalInstallments,
        'cuotas_cumplidas' => $completedInstallments,
        'progreso_general' => $overallProgress,
        'prestamos' => $loans,
    ];
}

function createLoan(PDO $pdo, array $data): array
{
    $loan = validateLoanInput($data);

    $check = $pdo->prepare('SELECT id FROM prestamos WHERE plataforma = ? AND id_plataforma = ? LIMIT 1');
    $check->execute([$loan['plataforma'], $loan['id_plataforma']]);
    if ($check->fetch()) {
        throw new InvalidArgumentException('Ya existe un préstamo con esa plataforma e ID.');
    }

    $stmt = $pdo->prepare('
        INSERT INTO prestamos (
            plataforma, id_plataforma, cuotas_totales, cuotas_cumplidas,
            monto_mensual
        ) VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $loan['plataforma'],
        $loan['id_plataforma'],
        $loan['cuotas_totales'],
        $loan['cuotas_cumplidas'],
        $loan['monto_mensual'],
    ]);

    return ['id' => (int) $pdo->lastInsertId()];
}

function updateLoan(PDO $pdo, array $data): array
{
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('El préstamo indicado no es válido.');
    }

    $loan = validateLoanInput($data);

    $check = $pdo->prepare('SELECT id FROM prestamos WHERE plataforma = ? AND id_plataforma = ? AND id <> ? LIMIT 1');
    $check->execute([$loan['plataforma'], $loan['id_plataforma'], $id]);
    if ($check->fetch()) {
        throw new InvalidArgumentException('Ya existe otro préstamo con esa plataforma e ID.');
    }

    $currentStmt = $pdo->prepare('SELECT cuotas_cumplidas FROM prestamos WHERE id = ? LIMIT 1');
    $currentStmt->execute([$id]);
    $current = $currentStmt->fetch();
    if (!$current) {
        throw new InvalidArgumentException('El préstamo no existe.');
    }

    $paymentCountStmt = $pdo->prepare('SELECT COUNT(*) FROM prestamos_pagos WHERE prestamo_id = ?');
    $paymentCountStmt->execute([$id]);
    $paymentCount = (int) $paymentCountStmt->fetchColumn();
    $minimumPaid = max((int) $current['cuotas_cumplidas'], $paymentCount);

    if ($loan['cuotas_cumplidas'] < $minimumPaid) {
        throw new InvalidArgumentException('No puedes reducir las cuotas cumplidas por debajo de las ya registradas.');
    }

    $stmt = $pdo->prepare('
        UPDATE prestamos
        SET plataforma = ?,
            id_plataforma = ?,
            cuotas_totales = ?,
            cuotas_cumplidas = ?,
            monto_mensual = ?
        WHERE id = ?
    ');
    $stmt->execute([
        $loan['plataforma'],
        $loan['id_plataforma'],
        $loan['cuotas_totales'],
        $loan['cuotas_cumplidas'],
        $loan['monto_mensual'],
        $id,
    ]);

    return ['id' => $id];
}

function deleteLoan(PDO $pdo, array $data): void
{
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('El préstamo indicado no es válido.');
    }

    $stmt = $pdo->prepare('DELETE FROM prestamos WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new InvalidArgumentException('El préstamo no existe.');
    }
}

function markPaid(PDO $pdo, array $data): array
{
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('El préstamo indicado no es válido.');
    }

    if (!paymentWindowOpen()) {
        throw new RuntimeException('La cuota mensual solo puede marcarse entre el día 1 y el 10 de cada mes.');
    }

    $period = currentPeriod();

    $pdo->beginTransaction();
    try {
        $loanStmt = $pdo->prepare('SELECT id, cuotas_totales, cuotas_cumplidas FROM prestamos WHERE id = ? FOR UPDATE');
        $loanStmt->execute([$id]);
        $loan = $loanStmt->fetch();

        if (!$loan) {
            throw new InvalidArgumentException('El préstamo no existe.');
        }

        if ((int) $loan['cuotas_cumplidas'] >= (int) $loan['cuotas_totales']) {
            throw new RuntimeException('Este préstamo ya está completamente pagado.');
        }

        $insert = $pdo->prepare('INSERT INTO prestamos_pagos (prestamo_id, periodo, monto_abonado) SELECT ?, ?, monto_mensual FROM prestamos WHERE id = ?');

        try {
            $insert->execute([$id, $period, $id]);
        } catch (PDOException $exception) {
            if ((int) $exception->errorInfo[1] === 1062) {
                throw new RuntimeException('La cuota de este mes ya fue marcada como abonada.');
            }
            throw $exception;
        }

        $update = $pdo->prepare('UPDATE prestamos SET cuotas_cumplidas = cuotas_cumplidas + 1 WHERE id = ?');
        $update->execute([$id]);

        $pdo->commit();
        return ['id' => $id, 'periodo' => $period];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function unmarkPaid(PDO $pdo, array $data): array
{
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('El préstamo indicado no es válido.');
    }

    $period = currentPeriod();

    $pdo->beginTransaction();
    try {
        $loanStmt = $pdo->prepare('SELECT cuotas_cumplidas FROM prestamos WHERE id = ? FOR UPDATE');
        $loanStmt->execute([$id]);
        $loan = $loanStmt->fetch();
        if (!$loan) {
            throw new InvalidArgumentException('El préstamo no existe.');
        }

        $paymentStmt = $pdo->prepare('SELECT id FROM prestamos_pagos WHERE prestamo_id = ? AND periodo = ? LIMIT 1');
        $paymentStmt->execute([$id, $period]);
        $payment = $paymentStmt->fetch();
        if (!$payment) {
            throw new RuntimeException('La cuota de este mes no está marcada como abonada.');
        }

        if ((int) $loan['cuotas_cumplidas'] <= 0) {
            throw new RuntimeException('No hay cuotas cumplidas para revertir.');
        }

        $delete = $pdo->prepare('DELETE FROM prestamos_pagos WHERE id = ?');
        $delete->execute([(int) $payment['id']]);

        $update = $pdo->prepare('UPDATE prestamos SET cuotas_cumplidas = cuotas_cumplidas - 1 WHERE id = ?');
        $update->execute([$id]);

        $pdo->commit();
        return ['id' => $id, 'periodo' => $period];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

try {
    $data = requestData();
    $action = (string) ($data['action'] ?? $_GET['action'] ?? 'dashboard');
    $pdo = pdo();

    switch ($action) {
        case 'dashboard':
            jsonResponse(['ok' => true] + dashboard($pdo));
            break;

        case 'create':
            $result = createLoan($pdo, $data);
            jsonResponse(['ok' => true, 'mensaje' => 'Préstamo creado correctamente.', 'id' => $result['id']]);
            break;

        case 'update':
            $result = updateLoan($pdo, $data);
            jsonResponse(['ok' => true, 'mensaje' => 'Préstamo actualizado correctamente.', 'id' => $result['id']]);
            break;

        case 'delete':
            deleteLoan($pdo, $data);
            jsonResponse(['ok' => true, 'mensaje' => 'Préstamo eliminado correctamente.']);
            break;

        case 'mark_paid':
            $result = markPaid($pdo, $data);
            jsonResponse(['ok' => true, 'mensaje' => 'Cuota del mes marcada como abonada.', 'periodo' => $result['periodo']]);
            break;

        case 'unmark_paid':
            $result = unmarkPaid($pdo, $data);
            jsonResponse(['ok' => true, 'mensaje' => 'La cuota del mes fue revertida.', 'periodo' => $result['periodo']]);
            break;

        default:
            jsonResponse(['ok' => false, 'mensaje' => 'Acción no reconocida.'], 400);
    }
} catch (InvalidArgumentException $exception) {
    jsonResponse(['ok' => false, 'mensaje' => $exception->getMessage()], 422);
} catch (RuntimeException $exception) {
    jsonResponse(['ok' => false, 'mensaje' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    jsonResponse([
        'ok' => false,
        'mensaje' => 'Ocurrió un error al procesar la solicitud.',
        'detalle' => $exception->getMessage(),
    ], 500);
}
