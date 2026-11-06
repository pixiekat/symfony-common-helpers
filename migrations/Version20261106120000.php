<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds an optional display-label override to vocabulary terms.
 *
 * name stays the term's identity: what an import calls it and what the
 * (vocabulary_id, name) unique constraint and importers match on. display_label
 * is presentation only: when set, Term::getLabel() returns it instead of name.
 *
 * Nullable with no backfill: every existing row has no override, so it keeps
 * displaying its name exactly as before.
 *
 * Unindexed: the label is read when showing a term, never used to find one.
 *
 * @see \Pixiekat\SymfonyHelpers\Entity\Term::getLabel()
 */
final class Version20261106120000 extends AbstractMigration {

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'Adds a nullable display_label override column to vocabulary_terms.';
  }

  /**
   * Adds vocabulary_terms.display_label, skipping safely if it already exists.
   *
   * @param Schema $schema The current schema.
   */
  public function up(Schema $schema): void {
    $this->write("Checking 'vocabulary_terms' for display_label...");

    if (!$schema->hasTable('vocabulary_terms')) {
      $this->write("Table 'vocabulary_terms' does not exist, skipping.");
      return;
    }

    $table = $schema->getTable('vocabulary_terms');

    if ($table->hasColumn('display_label')) {
      $this->write("Column 'vocabulary_terms.display_label' already exists, skipping.");
      return;
    }

    $this->write("Adding 'vocabulary_terms.display_label'...");
    $this->addSql('ALTER TABLE vocabulary_terms ADD display_label VARCHAR(255) DEFAULT NULL');
    $this->write("Done.");
  }

  /**
   * Drops vocabulary_terms.display_label.
   *
   * Any overrides typed into the admin UI are lost. Names are untouched, so
   * every term falls back to displaying its name.
   *
   * @param Schema $schema The current schema.
   */
  public function down(Schema $schema): void {
    if (!$schema->hasTable('vocabulary_terms')) {
      $this->write("Table 'vocabulary_terms' does not exist, skipping.");
      return;
    }

    if (!$schema->getTable('vocabulary_terms')->hasColumn('display_label')) {
      $this->write("Column 'vocabulary_terms.display_label' does not exist, skipping.");
      return;
    }

    $this->write("Dropping 'vocabulary_terms.display_label'...");
    $this->addSql('ALTER TABLE vocabulary_terms DROP display_label');
  }
}
