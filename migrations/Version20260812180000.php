<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pixiekat\SymfonyHelpers\Traits\Migration\ResolvesUserTableTrait;

/**
 * Reworks audit_logs: real actor and target, IP address, machine-key actions.
 *
 * Renames (old -> new):
 *   entity_type      -> target_type
 *   performed_by     -> actor_label
 *   additional_data  -> context
 *
 * Adds: actor_id (FK to the app's user table), target_id, target_label,
 * ip_address, and the indexes the admin listing needs.
 *
 * Also rewrites the three legacy action values, which were display text and are
 * now machine keys. Without that, historical rows render through the generic
 * fallback phrasing and no longer match a filter on 'created'.
 *
 * Every step is guarded: consuming applications adopt this library at different
 * points, and some will have created the table from entity metadata rather than
 * from the earlier migration.
 */
final class Version20260812180000 extends AbstractMigration {

  use ResolvesUserTableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Rework audit_logs: actor/target columns, IP address, machine-key actions.';
  }

  /**
   * {@inheritdoc}
   */
  public function up(Schema $schema): void {
    if (!$schema->hasTable('audit_logs')) {
      $this->write('↷ Table audit_logs does not exist — nothing to rework.');

      return;
    }

    $table = $schema->getTable('audit_logs');

    // ── Renames ─────────────────────────────────────────────────────────────
    // CHANGE rather than RENAME COLUMN: RENAME COLUMN needs MySQL 8.0 /
    // MariaDB 10.5.2, while CHANGE works everywhere this library runs.
    if ($table->hasColumn('entity_type') && !$table->hasColumn('target_type')) {
      $this->addSql('ALTER TABLE audit_logs CHANGE entity_type target_type VARCHAR(64) DEFAULT NULL;');
    }

    if ($table->hasColumn('performed_by') && !$table->hasColumn('actor_label')) {
      $this->addSql("ALTER TABLE audit_logs CHANGE performed_by actor_label VARCHAR(255) DEFAULT 'system' NOT NULL;");
    }

    if ($table->hasColumn('additional_data') && !$table->hasColumn('context')) {
      $this->addSql('ALTER TABLE audit_logs CHANGE additional_data context JSON DEFAULT NULL;');
    }

    // ── New columns ─────────────────────────────────────────────────────────
    // Resolved before the column is added: actor_id has to match the
    // referenced primary key's width and signedness, or MySQL rejects the
    // constraint with the same errno 150 it uses for a missing table.
    $users = $this->resolveUserTable($schema);

    if (!$table->hasColumn('actor_id')) {
      $this->addSql(sprintf('ALTER TABLE audit_logs ADD actor_id %s DEFAULT NULL;', $this->userIdColumnType($users)));
    }

    if (!$table->hasColumn('target_id')) {
      $this->addSql('ALTER TABLE audit_logs ADD target_id VARCHAR(64) DEFAULT NULL;');
    }

    if (!$table->hasColumn('target_label')) {
      $this->addSql('ALTER TABLE audit_logs ADD target_label VARCHAR(255) DEFAULT NULL;');
    }

    if (!$table->hasColumn('ip_address')) {
      $this->addSql('ALTER TABLE audit_logs ADD ip_address VARCHAR(45) DEFAULT NULL;');
    }

    // action was VARCHAR(255) holding words like 'Added'. It is a key, it is
    // indexed, and 64 is ample.
    $this->addSql('ALTER TABLE audit_logs CHANGE action action VARCHAR(64) NOT NULL;');

    // ── Data: display text -> machine keys ──────────────────────────────────
    // Ordered so 'Added' becomes 'created' rather than 'added'; the other two
    // only needed lowercasing.
    $this->addSql("UPDATE audit_logs SET action = 'created' WHERE action IN ('Added', 'added');");
    $this->addSql("UPDATE audit_logs SET action = 'updated' WHERE action = 'Updated';");
    $this->addSql("UPDATE audit_logs SET action = 'deleted' WHERE action = 'Deleted';");

    // Rows written before this rework have no actor label at all if the column
    // was empty; name them rather than leaving a blank in the listing.
    $this->addSql("UPDATE audit_logs SET actor_label = 'system' WHERE actor_label IS NULL OR actor_label = '';");

    // ── Indexes ─────────────────────────────────────────────────────────────
    foreach ([
      'idx_audit_created_at' => 'created_at',
      'idx_audit_action' => 'action',
      'idx_audit_target' => 'target_type, target_id',
      'IDX_audit_actor_id' => 'actor_id',
    ] as $name => $columns) {
      if ($table->hasIndex($name)) {
        continue;
      }

      $this->addSql(sprintf('CREATE INDEX %s ON audit_logs (%s);', $name, $columns));
    }

    // The referenced table belongs to the application, so its name is resolved
    // from the schema rather than hardcoded — see ResolvesUserTableTrait, which
    // also explains why the try/catch this replaced could never have worked.
    if ($users === null) {
      $this->write($this->manualForeignKeyNotice('audit_logs', 'actor_id', 'FK_audit_logs_actor_id'));

      return;
    }

    $this->addSql(sprintf(
      'ALTER TABLE audit_logs ADD CONSTRAINT FK_audit_logs_actor_id FOREIGN KEY (actor_id) REFERENCES `%s` (id) ON DELETE SET NULL;',
      $users->getName(),
    ));
  }

  /**
   * {@inheritdoc}
   *
   * Reverses the structural changes. The action values are NOT converted back:
   * rows written after this migration use keys that have no old-style
   * equivalent ('user.deleted' was never 'Added'), so a blanket reversal would
   * invent data. Structure down, history left alone.
   */
  public function down(Schema $schema): void {
    if (!$schema->hasTable('audit_logs')) {
      return;
    }

    $table = $schema->getTable('audit_logs');

    // Asked of the schema, not wrapped in a try/catch — addSql() only queues
    // the statement, so a catch here would never see the failure.
    if ($table->hasForeignKey('FK_audit_logs_actor_id')) {
      $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_audit_logs_actor_id;');
    }

    foreach (['idx_audit_created_at', 'idx_audit_action', 'idx_audit_target', 'IDX_audit_actor_id'] as $index) {
      if ($table->hasIndex($index)) {
        $this->addSql(sprintf('DROP INDEX %s ON audit_logs;', $index));
      }
    }

    foreach (['actor_id', 'target_id', 'target_label', 'ip_address'] as $column) {
      if ($table->hasColumn($column)) {
        $this->addSql(sprintf('ALTER TABLE audit_logs DROP %s;', $column));
      }
    }

    if ($table->hasColumn('target_type')) {
      $this->addSql('ALTER TABLE audit_logs CHANGE target_type entity_type VARCHAR(255) NOT NULL;');
    }

    if ($table->hasColumn('actor_label')) {
      $this->addSql('ALTER TABLE audit_logs CHANGE actor_label performed_by VARCHAR(255) NOT NULL;');
    }

    if ($table->hasColumn('context')) {
      $this->addSql('ALTER TABLE audit_logs CHANGE context additional_data JSON DEFAULT NULL;');
    }

    $this->addSql('ALTER TABLE audit_logs CHANGE action action VARCHAR(255) NOT NULL;');
  }
}
