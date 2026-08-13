<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Traits\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BigIntType;

/**
 * Lets a migration point a foreign key at the application's user table.
 *
 * ── THE PROBLEM ────────────────────────────────────────────────────────────
 * Bundle entities map their user relations against HelpersUserInterface, which
 * Doctrine resolves to whatever class the app configured. That works fine at
 * runtime — and not at all in a migration, which is raw SQL with no entity
 * manager and therefore no idea what the table is called or how its primary key
 * is typed. Hardcoding `users`(id) INT works in the project the migration was
 * written in and fails everywhere else with:
 *
 *     errno: 150 "Foreign key constraint is incorrectly formed"
 *
 * which MySQL reports identically whether the table is missing, named something
 * else, or has a BIGINT / UNSIGNED primary key that INT cannot reference.
 *
 * ── WHY NOT try/catch ──────────────────────────────────────────────────────
 * The obvious guard does not work, and it is worth spelling out because it
 * LOOKS like it does:
 *
 *     try { $this->addSql('ALTER TABLE … ADD CONSTRAINT …'); }
 *     catch (\Doctrine\DBAL\Exception $e) { $this->write('skipping'); }
 *
 * addSql() only APPENDS to a list of statements. Nothing is executed until
 * after up() has returned, so the exception is thrown far outside that catch
 * and the migration dies anyway. Any guard has to be a question asked of the
 * Schema during up(), which is what this trait provides.
 */
trait ResolvesUserTableTrait {

  /**
   * Table names to try, in order of how likely they are.
   *
   * Guessing is not elegant, but the alternative is shipping no foreign key at
   * all. A consumer whose table is named something else still gets working
   * tables — just without the constraint — plus a message saying exactly what
   * to run by hand.
   */
  private const USER_TABLE_CANDIDATES = ['users', 'user', 'app_user', 'app_users', 'accounts', 'account'];

  /**
   * Finds the application's user table, if it can.
   *
   * @param Schema $schema The current schema.
   *
   * @return Table|null The table, or null if none of the candidates matched.
   */
  private function resolveUserTable(Schema $schema): ?Table {
    foreach (self::USER_TABLE_CANDIDATES as $name) {
      if ($schema->hasTable($name) && $schema->getTable($name)->hasColumn('id')) {
        return $schema->getTable($name);
      }
    }

    return null;
  }

  /**
   * The column definition a foreign key to that table must use.
   *
   * A referencing column has to match the referenced one in width and
   * signedness or MySQL refuses the constraint — the second most common cause
   * of errno 150 after a missing table, and the one that produces the same
   * message while the table plainly exists.
   *
   * @param Table|null $users The resolved user table, or null.
   *
   * @return string A column type, e.g. 'INT', 'BIGINT UNSIGNED'.
   */
  private function userIdColumnType(?Table $users): string {
    if ($users === null) {
      return 'INT';
    }

    $column = $users->getColumn('id');
    $type = $column->getType() instanceof BigIntType ? 'BIGINT' : 'INT';

    return $column->getUnsigned() ? $type . ' UNSIGNED' : $type;
  }

  /**
   * Tells the operator how to add the constraint themselves.
   *
   * @param string $table The table holding the foreign key.
   * @param string $column The foreign key column.
   * @param string $constraint The constraint name to use.
   *
   * @return string A message for write().
   */
  private function manualForeignKeyNotice(string $table, string $column, string $constraint): string {
    return sprintf(
      '↷ Could not find a user table (tried: %s), so %s.%s has no foreign key. '
      . 'The tables are otherwise complete. Add it by hand with:  '
      . 'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES <your_user_table> (id) ON DELETE SET NULL;',
      implode(', ', self::USER_TABLE_CANDIDATES),
      $table,
      $column,
      $table,
      $constraint,
      $column,
    );
  }
}
