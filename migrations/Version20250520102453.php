<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250520102453 extends AbstractMigration {
  public function getDescription(): string {
    return 'Adding audit_logs and bans tables';
  }

  public function up(Schema $schema): void {
    $table = $schema->hasTable('audit_logs');
    if (!$table) {
      $this->addSql('CREATE TABLE audit_logs (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(255) NOT NULL, entity_type VARCHAR(255) NOT NULL, performed_by VARCHAR(255) NOT NULL, additional_data LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
    else {
      $this->write('Table audit_logs already exists, skipping creation.');
    }

    $table = $schema->hasTable('bans');
    if (!$table) {
      $this->addSql('CREATE TABLE bans (id INT AUTO_INCREMENT NOT NULL, ip_address VARCHAR(255) NOT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
    else {
      $this->write('Table bans already exists, skipping creation.');
    }
  }

  public function down(Schema $schema): void {
    $table = $schema->hasTable('audit_logs');
    if ($table) {
      $this->addSql('DROP TABLE audit_logs');
    }
    else {
      $this->write('Table audit_logs does not exist, skipping deletion.');
    }
    $table = $schema->hasTable('bans');
    if ($table) {
      $this->addSql('DROP TABLE bans');
    }
    else {
      $this->write('Table bans does not exist, skipping deletion.');
    }
  }
}
