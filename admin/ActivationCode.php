<?php
/**
 * 激活码管理类
 */

require_once __DIR__ . '/../classes/Database.php';

class ActivationCode {
    private $db;

    public $id;
    public $code;
    public $status;
    public $usedBy;
    public $usedAt;
    public $createdAt;
    public $expiresAt;
    public $remark;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 生成激活码
     * @param int $count 生成数量
     * @param string $remark 备注
     * @param string|null $expiresAt 过期时间
     * @return array
     */
    public static function generate($count = 1, $remark = '', $expiresAt = null) {
        $db = Database::getInstance();
        $generated = [];

        try {
            for ($i = 0; $i < $count; $i++) {
                $code = self::generateCode();

                $stmt = $db->prepare(
                    "INSERT INTO activation_codes (code, remark, expires_at) VALUES (:code, :remark, :expires_at)"
                );
                $stmt->bindValue(':code', $code, SQLITE3_TEXT);
                $stmt->bindValue(':remark', $remark, SQLITE3_TEXT);
                $stmt->bindValue(':expires_at', $expiresAt, SQLITE3_TEXT);

                if ($stmt->execute()) {
                    $generated[] = [
                        'id' => $db->lastInsertId(),
                        'code' => $code,
                        'remark' => $remark,
                        'expires_at' => $expiresAt
                    ];
                }
            }

            return ['success' => true, 'data' => $generated];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '生成失败：' . $e->getMessage()];
        }
    }

    /**
     * 生成随机激活码
     */
    private static function generateCode() {
        // 生成16字符的激活码：XXXX-XXXX-XXXX-XXXX
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 移除易混淆字符
        $parts = [];

        for ($i = 0; $i < 4; $i++) {
            $part = '';
            for ($j = 0; $j < 4; $j++) {
                $part .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $parts[] = $part;
        }

        return implode('-', $parts);
    }

    /**
     * 验证激活码
     * @param string $code 激活码
     * @return array
     */
    public static function validate($code) {
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare(
                "SELECT * FROM activation_codes WHERE code = :code"
            );
            $stmt->bindValue(':code', strtoupper($code), SQLITE3_TEXT);
            $result = $stmt->execute();
            $row = $result->fetchArray(SQLITE3_ASSOC);

            if (!$row) {
                return ['success' => false, 'message' => '激活码不存在'];
            }

            if ($row['status'] !== 'unused') {
                return ['success' => false, 'message' => '激活码已被使用'];
            }

            // 检查是否过期
            if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
                // 更新状态为已过期
                $updateStmt = $db->prepare(
                    "UPDATE activation_codes SET status = 'expired' WHERE id = :id"
                );
                $updateStmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                $updateStmt->execute();

                return ['success' => false, 'message' => '激活码已过期'];
            }

            return ['success' => true, 'data' => $row];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '验证失败：' . $e->getMessage()];
        }
    }

    /**
     * 使用激活码
     * @param string $code 激活码
     * @param int $userId 用户ID
     * @return array
     */
    public static function use($code, $userId) {
        $db = Database::getInstance();

        // 先验证激活码
        $validation = self::validate($code);
        if (!$validation['success']) {
            return $validation;
        }

        try {
            $stmt = $db->prepare(
                "UPDATE activation_codes SET status = 'used', used_by = :user_id, used_at = datetime('now') WHERE code = :code"
            );
            $stmt->bindValue(':code', strtoupper($code), SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $userId, SQLITE3_INTEGER);

            if ($stmt->execute()) {
                return ['success' => true, 'message' => '激活成功'];
            } else {
                return ['success' => false, 'message' => '激活失败'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => '使用失败：' . $e->getMessage()];
        }
    }

    /**
     * 获取所有激活码列表
     * @return array
     */
    public static function getAll() {
        $db = Database::getInstance();

        try {
            $result = $db->query(
                "SELECT ac.*, u.mobile as used_by_mobile
                 FROM activation_codes ac
                 LEFT JOIN users u ON ac.used_by = u.id
                 ORDER BY ac.created_at DESC"
            );

            $codes = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $codes[] = $row;
            }

            return ['success' => true, 'data' => $codes];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '查询失败：' . $e->getMessage()];
        }
    }

    /**
     * 删除激活码
     * @param array $ids 激活码ID数组
     * @return array
     */
    public static function delete($ids) {
        $db = Database::getInstance();
        $deleted = 0;

        try {
            foreach ($ids as $id) {
                $stmt = $db->prepare("DELETE FROM activation_codes WHERE id = :id");
                $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                if ($stmt->execute()) {
                    $deleted++;
                }
            }

            return ['success' => true, 'deleted' => $deleted];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '删除失败：' . $e->getMessage()];
        }
    }

    /**
     * 获取统计信息
     */
    public static function getStats() {
        $db = Database::getInstance();

        try {
            $total = $db->querySingle("SELECT COUNT(*) FROM activation_codes");
            $unused = $db->querySingle("SELECT COUNT(*) FROM activation_codes WHERE status = 'unused'");
            $used = $db->querySingle("SELECT COUNT(*) FROM activation_codes WHERE status = 'used'");
            $expired = $db->querySingle("SELECT COUNT(*) FROM activation_codes WHERE status = 'expired'");

            return [
                'success' => true,
                'data' => [
                    'total' => $total,
                    'unused' => $unused,
                    'used' => $used,
                    'expired' => $expired
                ]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'message' => '统计失败：' . $e->getMessage()];
        }
    }
}
