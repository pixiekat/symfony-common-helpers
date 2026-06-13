<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Converts audit_logs.additional_data from the legacy Doctrine 'array' type
 * (PHP serialize(), stored as LONGTEXT) to 'json'.
 *
 * Why: DBAL 4 removed the 'array' type, so the AuditLog entity now maps the
 * column as 'json'. New databases get a JSON column straight away (via the
 * normal schema), but databases created by the older lib migration hold
 * SERIALIZED data in a LONGTEXT column — this migration rewrites that data as
 * JSON in place and then retypes the column, so upgrading consumers don't lose
 * their audit history.
 *
 * Safe to run repeatedly / on fresh installs:
 *   - skips entirely if audit_logs is missing,
 *   - skips on non-MySQL/MariaDB platforms (the lib's other migrations are
 *     MySQL-flavoured; Postgres consumers manage this column via schema diff),
 *   - skips rows whose value is already valid JSON.
 */
final class Version20260613120000 extends AbstractMigration {

  public function getDescription(): string {
    return 'Convert audit_logs.additional_data from serialized array to JSON.';
  }

  public function up(Schema $schema): void {
    if (!$this->isMySql()) {
      $this->write('↷ Not MySQL/MariaDB — skipping additional_data array→json conversion.');
      return;
    }
    if (!$schema->hasTable('audit_logs')) {
      $this->write('↷ Table audit_logs does not exist — nothing to convert.');
      return;
    }

    // 1. Rewrite each serialized value as JSON, in PHP, before retyping the
    //    column (MySQL would reject invalid JSON when the column becomes JSON).
    $rows = $this->connection->fetchAllAssociative(
      'SELECT id, additional_data FROM audit_logs WHERE additional_data IS NOT NULL'
    );

    $converted = 0;
    foreach ($rows as $row) {
      $raw = (string) $row['additional_data'];

      // Already JSON? Leave it alone (idempotent / mixed-state safe).
      json_decode($raw, true);
      if (json_last_error() === \JSON_ERROR_NONE) {
        continue;
      }

      // Legacy serialized payload — audit data is plain scalars/arrays, so
      // disallow object instantiation while unserializing.
      $data = @unserialize($raw, ['allowed_classes' => false]);
      if ($data === false && $raw !== 'b:0;') {
        // Unrecognised value: don't guess, just skip it (logged for visibility).
        $this->write(sprintf('  ⚠ Row #%s: additional_data is neither JSON nor serialized — left unchanged.', $row['id']));
        continue;
      }

      $this->connection->executeStatement(
        'UPDATE audit_logs SET additional_data = :json WHERE id = :id',
        ['json' => json_encode($data), 'id' => $row['id']]
      );
      $converted++;
    }
    $this->write(sprintf('✔ Converted %d audit_logs row(s) to JSON.', $converted));

    // 2. Retype the column to JSON (drops the DC2Type:array comment).
    $this->write('✚ Retyping audit_logs.additional_data → JSON …');
    $this->addSql('ALTER TABLE audit_logs MODIFY additional_data JSON DEFAULT NULL');
  }

  public function down(Schema $schema): void {
    if (!$this->isMySql() || !$schema->hasTable('audit_logs')) {
      $this->write('↷ Nothing to revert.');
      return;
    }

    // Re-serialize JSON back to the legacy 'array' representation, then restore
    // the LONGTEXT column + DC2Type:array comment. Best-effort, lossy for any
    // value that wasn't a plain array to begin with.
    $this->addSql("ALTER TABLE audit_logs MODIFY additional_data LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)'");

    $rows = $this->connection->fetchAllAssociative(
      'SELECT id, additional_data FROM audit_logs WHERE additional_data IS NOT NULL'
    );
    foreach ($rows as $row) {
      $data = json_decode((string) $row['additional_data'], true);
      if ($data === null && json_last_error() !== \JSON_ERROR_NONE) {
        continue; // not JSON; leave as-is
      }
      $this->connection->executeStatement(
        'UPDATE audit_logs SET additional_data = :ser WHERE id = :id',
        ['ser' => serialize($data), 'id' => $row['id']]
      );
    }
  }

  private function isMySql(): bool {
    return $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
  }
}
