<?php
/**
 * PostgreSQL shared sessions, safe behind Supabase's TRANSACTION pooler.
 * All session operations are serialized by a transaction-scoped advisory lock.
 * Lock remains held from session_start/read through session_write_close/close.
 * This pins ONE pooler backend for the duration of each active request; capacity
 * must be checked against Supabase's active-connection quotas.
 */
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit;
}

function new_session_connection(): PDO
{
    return new_pg_connection();
}

final class PgSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private PDO $db;
    private ?string $lockedId = null;
    private int $ttl;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ttl = max(60, (int) (env_value('SESSION_TTL_SECONDS') ?: '7200'));
    }

    public function open(string $path, string $name): bool { return true; }

    private function lock(string $id): void
    {
        if ($this->lockedId === $id && $this->db->inTransaction()) return;
        if ($this->db->inTransaction()) {
            // Session regeneration can change IDs; finish old write and lock cleanly.
            $this->db->commit();
        }
        $this->db->beginTransaction();
        // PostgreSQL 64-bit transaction-level lock; safe with transaction pooling.
        $stmt = $this->db->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?::text, 0))');
        $stmt->execute([$id]);
        $stmt->closeCursor();
        $this->lockedId = $id;
    }

    public function validateId(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM app_sessions WHERE session_id = ? AND expires_at > now()');
        $stmt->execute([$id]);
        $found = $stmt->fetchColumn() !== false;
        $stmt->closeCursor();
        return $found;
    }

    public function read(string $id): string|false
    {
        $this->lock($id);
        $stmt = $this->db->prepare('SELECT payload FROM app_sessions WHERE session_id = ? AND expires_at > now()');
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ($value === false) return '';
        $decoded = base64_decode($value, true);
        return $decoded === false ? false : $decoded;
    }

    public function write(string $id, string $data): bool
    {
        $this->lock($id);
        $stmt = $this->db->prepare(
            "INSERT INTO app_sessions (session_id, payload, updated_at, expires_at)
             VALUES (?, ?, now(), now() + (?::integer * interval '1 second'))
             ON CONFLICT (session_id) DO UPDATE SET payload = EXCLUDED.payload,
               updated_at = EXCLUDED.updated_at, expires_at = EXCLUDED.expires_at"
        );
        return $stmt->execute([$id, base64_encode($data), $this->ttl]);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        $this->lock($id);
        $stmt = $this->db->prepare("UPDATE app_sessions SET updated_at = now(),
            expires_at = now() + (?::integer * interval '1 second') WHERE session_id = ?");
        return $stmt->execute([$this->ttl, $id]);
    }

    public function destroy(string $id): bool
    {
        $this->lock($id);
        $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE session_id = ?');
        $ok = $stmt->execute([$id]);
        return $ok;
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->db->prepare('DELETE FROM app_sessions WHERE expires_at < now()');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function close(): bool
    {
        if ($this->db->inTransaction()) $this->db->commit();
        $this->lockedId = null;
        return true;
    }

    public function __destruct()
    {
        // Fail closed: abandoned transactions are never committed by a destructor.
        if ($this->db->inTransaction()) $this->db->rollBack();
    }
}
