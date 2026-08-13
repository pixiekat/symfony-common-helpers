<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Pixiekat\SymfonyHelpers\Traits\Migration\ResolvesUserTableTrait;

/**
 * Creates the shouts table.
 *
 * One table serves every shoutbox on the site; which box a message belongs to
 * is the `channel` column. See the Shout entity for why that beats a second
 * entity for something with no properties of its own.
 *
 * Written in the same defensive style as the other migrations here — guarded
 * with hasTable()/try-catch — because consuming applications adopt the library
 * at different points and may already have run a schema:update.
 */
final class Version20260812130000 extends AbstractMigration {

  use ResolvesUserTableTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Adding shouts table.';
  }

  /**
   * {@inheritdoc}
   */
  public function up(Schema $schema): void {

    // Resolved BEFORE the CREATE: author_id has to match the referenced
    // column's width and signedness, or MySQL rejects the constraint with
    // the same errno 150 it uses for a missing table.
    $users = $this->resolveUserTable($schema);
    $authorIdType = $this->userIdColumnType($users);

    if (!$schema->hasTable('shouts')) {
      // ip_address is VARCHAR(45): long enough for a full IPv6 address
      // including an IPv4-mapped suffix, which is the real-world maximum.
      //
      // The (channel, created_at) index is the one that matters — "latest N in
      // this channel" is essentially the only read this table ever serves. The
      // ip_address index supports the flood-control COUNT.
      $this->addSql(<<<SQL
        CREATE TABLE shouts (
          id INT AUTO_INCREMENT NOT NULL,
          author_id {$authorIdType} DEFAULT NULL,
          channel VARCHAR(64) DEFAULT 'default' NOT NULL,
          author_name VARCHAR(64) DEFAULT NULL,
          body LONGTEXT NOT NULL,
          ip_address VARCHAR(45) DEFAULT NULL,
          status VARCHAR(16) DEFAULT 'published' NOT NULL,
          flags JSON DEFAULT NULL,
          -- No COMMENT '(DC2Type:datetime_immutable)'. DBAL 3 used those to
          -- recover a column's Doctrine type; DBAL 4 dropped the mechanism, so
          -- one written now only shows up as permanent schema drift.
          created_at DATETIME NOT NULL,
          INDEX idx_shouts_channel_created (channel, created_at),
          INDEX idx_shouts_ip_address (ip_address),
          INDEX IDX_shouts_author_id (author_id),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;
        SQL);
    }
    else {
      $this->write('Table shouts already exists, skipping creation.');
    }

    // ON DELETE SET NULL, not CASCADE: deleting a user account should not
    // silently erase their side of a conversation other people took part in.
    // The shout survives and falls back to displaying "Anonymous".
    //
    // The referenced table belongs to the application, so its name is resolved
    // from the schema rather than hardcoded — see ResolvesUserTableTrait, which
    // also explains why the try/catch this replaced could never have worked.
    if ($users === null) {
      $this->write($this->manualForeignKeyNotice('shouts', 'author_id', 'FK_shouts_author_id'));

      return;
    }

    $this->addSql(sprintf(
      'ALTER TABLE shouts ADD CONSTRAINT FK_shouts_author_id FOREIGN KEY (author_id) REFERENCES `%s` (id) ON DELETE SET NULL;',
      $users->getName(),
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function down(Schema $schema): void {
    if ($schema->hasTable('shouts')) {
      $this->addSql('DROP TABLE shouts;');
    }
  }
}
