<?php
declare(strict_types=1);

/**
 * Trả về đối tượng PDO dùng chung (chỉ tạo kết nối một lần).
 * Dùng: $stmt = db()->prepare('SELECT ... WHERE id = ?');
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // lỗi SQL -> ném exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // fetch() trả mảng kết hợp
                PDO::ATTR_EMULATE_PREPARES   => false,                    // dùng prepared statement thật
            ]);
        } catch (PDOException $e) {
            error_log('DB connect error: ' . $e->getMessage());          // ghi log, không in cho người dùng
            http_response_code(500);
            exit('Không kết nối được cơ sở dữ liệu. Hãy bật MySQL trong XAMPP và kiểm tra config/config.php.');
        }
    }
    return $pdo;
}
