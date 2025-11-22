<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251121194354 extends AbstractMigration {
  public function getDescription(): string {
    return 'Adding taxonomy tables tables';
  }

  public function up(Schema $schema): void {









    $table = $schema->hasTable('vocabularies');
    if (!$table) {
      $this->addSql('CREATE TABLE vocabularies (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_enabled TINYINT(1) DEFAULT 1 NOT NULL, locked TINYINT(1) DEFAULT 1 NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', UNIQUE INDEX UNIQ_A20A445BD17F50A6 (uuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
    }
    else {
      $this->write('Table vocabularies already exists, skipping creation.');
    }

    $table = $schema->hasTable('vocabulary_terms');
    if (!$table) {
      $this->addSql('CREATE TABLE vocabulary_terms (id INT AUTO_INCREMENT NOT NULL, vocabulary_id INT NOT NULL, name VARCHAR(255) NOT NULL, weight INT DEFAULT 0 NOT NULL, INDEX IDX_BA21B29BAD0E05F6 (vocabulary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
    }
    else {
      $this->write('Table vocabulary_terms already exists, skipping creation.');
    }

    // Add foreign key constraint
    try {
      $this->addSql('ALTER TABLE vocabulary_terms ADD CONSTRAINT FK_BA21B29BAD0E05F6 FOREIGN KEY (vocabulary_id) REFERENCES vocabularies (id);');
    }
    catch (\Doctrine\DBAL\Exception $e) {
      $this->write('Foreign key constraint on vocabulary_terms.vocabulary_id already exists, skipping addition.');
    }
  }

  public function down(Schema $schema): void {
    $table = $schema->hasTable('vocabulary_terms');
    if ($table) {
      $this->addSql('DROP TABLE vocabulary_terms');
    }
    else {
      $this->write('Table vocabulary_terms does not exist, skipping deletion.');
    }
    $table = $schema->hasTable('vocabularies');
    if ($table) {
      $this->addSql('DROP TABLE vocabularies');
    }
    else {
      $this->write('Table vocabularies does not exist, skipping deletion.');
    }
  }
}
