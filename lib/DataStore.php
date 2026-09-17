<?php

function lf_db_config(): array
{
    $settings = [];
    $file = LF_DATA_DIR . '/settings.json';
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) $settings = $decoded;
    }
    return array_merge([
        'enabled' => false,
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'dbname' => 'learnflow',
        'user' => 'learnflow',
        'pass' => '',
        'sqlite_path' => LF_DATA_DIR . '/db/learnflow.db',
    ], (array)($settings['mysql'] ?? []));
}

function lf_db_enabled(): bool
{
    $cfg = lf_db_config();
    return !empty($cfg['enabled']);
}

function lf_db(): ?PDO
{
    static $pdo = null;
    static $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    $cfg = lf_db_config();
    if (empty($cfg['enabled']) || !class_exists('PDO')) return null;
    try {
        if ($cfg['driver'] === 'sqlite') {
            $path = (string)$cfg['sqlite_path'];
            $dir = dirname($path);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA journal_mode=WAL;');
            $pdo->exec('PRAGMA busy_timeout=5000;');
        } else {
            if (!in_array('mysql', PDO::getAvailableDrivers(), true)) return null;
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], (int)$cfg['port'], $cfg['dbname']);
            $pdo = new PDO($dsn, (string)$cfg['user'], (string)$cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        lf_db_ensure_table($pdo, (string)$cfg['driver']);
        return $pdo;
    } catch (Throwable $e) {
        @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] DB connect failed: ' . $e->getMessage() . "\n", FILE_APPEND);
        $pdo = null;
        return null;
    }
}

function lf_db_ensure_table(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        $pdo->exec('CREATE TABLE IF NOT EXISTS lf_kv (k TEXT PRIMARY KEY, v TEXT NOT NULL, updated_at TEXT NOT NULL)');
    } else {
        $pdo->exec('CREATE TABLE IF NOT EXISTS lf_kv (k VARCHAR(191) NOT NULL PRIMARY KEY, v LONGTEXT NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
}

function lf_db_key(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $rel = str_starts_with($path, LF_DATA_DIR) ? ltrim(substr($path, strlen(LF_DATA_DIR)), '/') : basename($path);
    $rel = preg_replace('#\.json$#', '', $rel);
    return str_replace('/', ':', $rel);
}

function lf_kv_read(string $key): ?array
{
    $pdo = lf_db();
    if ($pdo === null) return null;
    try {
        $stmt = $pdo->prepare('SELECT v FROM lf_kv WHERE k = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $data = json_decode((string)$row['v'], true);
        return is_array($data) ? $data : [];
    } catch (Throwable $e) {
        return null;
    }
}

function lf_kv_write(string $key, array $data): bool
{
    $pdo = lf_db();
    if ($pdo === null) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    try {
        $driver = lf_db_config()['driver'];
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?)');
        } else {
            $stmt = $pdo->prepare('INSERT INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)');
        }
        return $stmt->execute([$key, $json, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
        @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] DB write failed: ' . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}

function lf_kv_seed(string $key, array $data): void
{
    $pdo = lf_db();
    if ($pdo === null || !$data) return;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) return;
    try {
        $driver = lf_db_config()['driver'];
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?)'
            : 'INSERT IGNORE INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?)';
        $pdo->prepare($sql)->execute([$key, $json, date('Y-m-d H:i:s')]);
    } catch (Throwable $e) {
    }
}

function lf_kv_update(string $key, callable $mutator, array $initial = []): array
{
    $pdo = lf_db();
    if ($pdo === null) {
        return $mutator($initial);
    }
    $driver = lf_db_config()['driver'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare($driver === 'sqlite' ? 'SELECT v FROM lf_kv WHERE k = ?' : 'SELECT v FROM lf_kv WHERE k = ? FOR UPDATE');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $current = $row ? (json_decode((string)$row['v'], true) ?: []) : $initial;
        $data = $mutator(is_array($current) ? $current : []);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($driver === 'sqlite') {
            $up = $pdo->prepare('INSERT OR REPLACE INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?)');
        } else {
            $up = $pdo->prepare('INSERT INTO lf_kv (k, v, updated_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = VALUES(updated_at)');
        }
        $up->execute([$key, $json, date('Y-m-d H:i:s')]);
        $pdo->commit();
        return $data;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @file_put_contents(LF_LOG_FILE, '[' . date('Y-m-d H:i:s') . '] DB update failed: ' . $e->getMessage() . "\n", FILE_APPEND);
        return $mutator($initial);
    }
}

function lf_db_status(): array
{
    $cfg = lf_db_config();
    if (empty($cfg['enabled'])) return ['enabled' => false, 'connected' => false, 'driver' => $cfg['driver'], 'message' => '未启用（JSON 文件存储）'];
    $pdo = lf_db();
    if ($pdo === null) return ['enabled' => true, 'connected' => false, 'driver' => $cfg['driver'], 'message' => '已启用但连接失败，已回退 JSON'];
    $count = 0;
    try {
        $count = (int)$pdo->query('SELECT COUNT(*) AS c FROM lf_kv')->fetch()['c'];
    } catch (Throwable $e) {
    }
    return ['enabled' => true, 'connected' => true, 'driver' => $cfg['driver'], 'collections' => $count, 'message' => '运行中'];
}
