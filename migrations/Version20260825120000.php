<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Gives vocabulary terms hierarchy, an external code, and provenance.
 *
 * Three generic columns that almost every real controlled vocabulary needs and
 * that a bare name cannot express — see the class docblock on Entity\Term for
 * what each is for. All nullable, so every existing row and every application
 * already using this bundle is unaffected: nothing to backfill, nothing to
 * change, three empty columns that a flat vocabulary never notices.
 *
 * Also adds the unique constraint that makes terms importable. A term's name is
 * unique WITHIN its vocabulary, not globally — "English" may legitimately be a
 * term in both a Languages and a Course Titles vocabulary.
 */
final class Version20260825120000 extends AbstractMigration {

  public function getDescription(): string {
    return 'Adds parent_id, code and source to vocabulary_terms, plus a unique (vocabulary_id, name) constraint.';
  }

  public function up(Schema $schema): void {
    if (!$schema->hasTable('vocabulary_terms')) {
      $this->write("Table 'vocabulary_terms' does not exist, skipping.");
      return;
    }

    $table = $schema->getTable('vocabulary_terms');

    // ── parent_id ───────────────────────────────────────────────────────────
    // ON DELETE SET NULL, deliberately not CASCADE: deleting a parent promotes
    // its children to roots rather than destroying them. Cascading would mean
    // removing one redundant term silently deletes every term beneath it.
    // Orphaned children are visible and fixable; deleted ones are gone.
    if ($table->hasColumn('parent_id')) {
      $this->write("Column 'vocabulary_terms.parent_id' already exists, skipping.");
    }
    else {
      $this->write("Adding 'vocabulary_terms.parent_id'...");
      $this->addSql('ALTER TABLE vocabulary_terms ADD parent_id INT DEFAULT NULL');
      $this->addSql('CREATE INDEX IDX_TERM_PARENT ON vocabulary_terms (parent_id)');
      $this->addSql(<<<'SQL'
        ALTER TABLE vocabulary_terms
          ADD CONSTRAINT FK_TERM_PARENT
          FOREIGN KEY (parent_id) REFERENCES vocabulary_terms (id) ON DELETE SET NULL;
      SQL);
    }

    // ── code ────────────────────────────────────────────────────────────────
    // Indexed but NOT unique: two vocabularies may legitimately reuse a code,
    // and plenty of terms have none at all. The index exists because looking a
    // term up BY its external code — "which term is SNOMED 22298006?" — is the
    // main reason to store one.
    if ($table->hasColumn('code')) {
      $this->write("Column 'vocabulary_terms.code' already exists, skipping.");
    }
    else {
      $this->write("Adding 'vocabulary_terms.code'...");
      $this->addSql('ALTER TABLE vocabulary_terms ADD code VARCHAR(64) DEFAULT NULL');
      $this->addSql('CREATE INDEX IDX_TERM_CODE ON vocabulary_terms (code)');
    }

    // ── source ──────────────────────────────────────────────────────────────
    // Unindexed: it is read when displaying a term, never used to find one.
    if ($table->hasColumn('source')) {
      $this->write("Column 'vocabulary_terms.source' already exists, skipping.");
    }
    else {
      $this->write("Adding 'vocabulary_terms.source'...");
      $this->addSql('ALTER TABLE vocabulary_terms ADD source VARCHAR(64) DEFAULT NULL');
    }

    // ── unique (vocabulary_id, name) ────────────────────────────────────────
    // This is the one statement here that can fail on a populated database: if
    // duplicate terms already exist within a vocabulary, MySQL refuses to build
    // the index. That failure is the correct outcome — it is telling you the
    // data contains ambiguity a machine cannot resolve, and silently keeping
    // one row per pair would be a guess. De-duplicate first, then re-run.
    if ($table->hasIndex('UNIQ_TERM_VOCABULARY_NAME')) {
      $this->write("Unique index on (vocabulary_id, name) already exists, skipping.");
    }
    else {
      $this->write("Adding unique index on (vocabulary_id, name)...");
      $this->addSql(<<<'SQL'
        CREATE UNIQUE INDEX UNIQ_TERM_VOCABULARY_NAME
          ON vocabulary_terms (vocabulary_id, name);
      SQL);
    }
  }

  public function down(Schema $schema): void {
    if (!$schema->hasTable('vocabulary_terms')) {
      return;
    }

    $table = $schema->getTable('vocabulary_terms');

    if ($table->hasIndex('UNIQ_TERM_VOCABULARY_NAME')) {
      $this->addSql('DROP INDEX UNIQ_TERM_VOCABULARY_NAME ON vocabulary_terms');
    }

    if ($table->hasColumn('source')) {
      $this->addSql('ALTER TABLE vocabulary_terms DROP source');
    }

    if ($table->hasColumn('code')) {
      $this->addSql('DROP INDEX IDX_TERM_CODE ON vocabulary_terms');
      $this->addSql('ALTER TABLE vocabulary_terms DROP code');
    }

    // Dropping parent_id flattens the hierarchy irrecoverably — which branch a
    // term sat on cannot be reconstructed from anything else in the row.
    if ($table->hasColumn('parent_id')) {
      $this->addSql('ALTER TABLE vocabulary_terms DROP FOREIGN KEY FK_TERM_PARENT');
      $this->addSql('DROP INDEX IDX_TERM_PARENT ON vocabulary_terms');
      $this->addSql('ALTER TABLE vocabulary_terms DROP parent_id');
    }
  }
}
