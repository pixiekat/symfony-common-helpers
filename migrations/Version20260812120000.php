<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the blocks and block_items tables.
 *
 * A "block" is a named chunk of page content placed by the developer with
 * {{ place_block('some_name') }}; a "block item" is one entry inside it,
 * typically a link. See the entity docblocks for the full design.
 *
 * Written in the same defensive style as the other migrations in this bundle —
 * guarded with hasTable()/try-catch — because consuming applications adopt the
 * library at different points and may already have run a schema:update that
 * created these tables from the entity metadata.
 */
final class Version20260812120000 extends AbstractMigration {

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Adding blocks and block_items tables.';
  }

  /**
   * {@inheritdoc}
   */
  public function up(Schema $schema): void {

    if (!$schema->hasTable('blocks')) {
      $this->addSql(<<<'SQL'
        CREATE TABLE blocks (
          id INT AUTO_INCREMENT NOT NULL,
          name VARCHAR(255) NOT NULL,
          label VARCHAR(255) DEFAULT NULL,
          description LONGTEXT DEFAULT NULL,
          body LONGTEXT DEFAULT NULL,
          template VARCHAR(255) DEFAULT NULL,
          weight INT DEFAULT 0 NOT NULL,
          is_enabled TINYINT(1) DEFAULT 1 NOT NULL,
          locked TINYINT(1) DEFAULT 1 NOT NULL,
          flags JSON DEFAULT NULL,
          -- No COMMENT '(DC2Type:uuid)' here. DBAL 3 used those comments to
          -- recover a column's Doctrine type; DBAL 4 dropped the mechanism, so
          -- writing one now just leaves a comment the entity does not declare
          -- and a permanent "CHANGE uuid uuid BINARY(16) NOT NULL" in every
          -- schema diff.
          uuid BINARY(16) NOT NULL,
          UNIQUE INDEX uniq_block_name (name),
          UNIQUE INDEX uniq_block_uuid (uuid),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        SQL);
    }
    else {
      $this->write('Table blocks already exists, skipping creation.');
    }

    if (!$schema->hasTable('block_items')) {
      // url is 512 rather than the usual 255: profile and share URLs carry long
      // query strings and silently truncating one produces a broken link.
      $this->addSql(<<<'SQL'
        CREATE TABLE block_items (
          id INT AUTO_INCREMENT NOT NULL,
          block_id INT NOT NULL,
          name VARCHAR(255) NOT NULL,
          label VARCHAR(255) DEFAULT NULL,
          wrapper_label VARCHAR(255) DEFAULT NULL,
          url VARCHAR(512) DEFAULT NULL,
          icon VARCHAR(255) DEFAULT NULL,
          weight INT DEFAULT 0 NOT NULL,
          is_enabled TINYINT(1) DEFAULT 1 NOT NULL,
          flags JSON DEFAULT NULL,
          INDEX IDX_block_items_block_id (block_id),
          UNIQUE INDEX uniq_block_item_name (block_id, name),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        SQL);
    }
    else {
      $this->write('Table block_items already exists, skipping creation.');
    }

    // ON DELETE CASCADE matches the orphanRemoval on the association: deleting a
    // block takes its items with it, at the database level as well as the ORM's,
    // so a direct SQL delete cannot leave orphans behind either.
    try {
      $this->addSql(<<<'SQL'
        ALTER TABLE block_items
          ADD CONSTRAINT FK_block_items_block_id
          FOREIGN KEY (block_id) REFERENCES blocks (id) ON DELETE CASCADE;
        SQL);
    }
    catch (\Doctrine\DBAL\Exception $e) {
      $this->write('Foreign key constraint on block_items.block_id already exists, skipping addition.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function down(Schema $schema): void {
    // block_items first — the foreign key makes the order matter.
    if ($schema->hasTable('block_items')) {
      $this->addSql('DROP TABLE block_items;');
    }

    if ($schema->hasTable('blocks')) {
      $this->addSql('DROP TABLE blocks;');
    }
  }
}
