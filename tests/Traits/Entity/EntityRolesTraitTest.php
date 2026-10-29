<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Tests\Traits\Entity;

use PHPUnit\Framework\TestCase;
use Pixiekat\SymfonyHelpers\Traits\Entity\EntityRolesTrait;

/**
 * getRoles() returns what is stored, plus ROLE_USER when nothing is, and
 * never grants anything implicitly. See the docblock on
 * EntityRolesTrait::getRoles() for the two rules that used to.
 */
class EntityRolesTraitTest extends TestCase {

    /**
     * A minimal user: the trait plus the two methods the old implicit rules
     * looked for, so these tests would catch either rule coming back.
     */
    private function makeUser(?int $id = null, ?string $lastName = null): object {
        return new class($id, $lastName) {
            use EntityRolesTrait;

            public function __construct(private ?int $id, private ?string $lastName) {}

            public function getId(): ?int { return $this->id; }

            public function getLastName(): ?string { return $this->lastName; }
        };
    }

    public function testNoStoredRolesMeansJustRoleUser(): void {
        $this->assertSame(['ROLE_USER'], array_values($this->makeUser()->getRoles()));
    }

    public function testStoredRolesAreReturnedAsIs(): void {
        $user = $this->makeUser()->setRoles(['ROLE_ADMIN', 'ROLE_TESTER']);

        $this->assertSame(['ROLE_ADMIN', 'ROLE_TESTER'], array_values($user->getRoles()));
    }

    public function testDuplicatesAreRemoved(): void {
        $user = $this->makeUser()->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN']);

        $this->assertSame(['ROLE_ADMIN'], array_values($user->getRoles()));
    }

    public function testUserOneIsNotImplicitlySuperAdmin(): void {
        $this->assertNotContains('ROLE_SUPER_ADMIN', $this->makeUser(id: 1)->getRoles());
    }

    public function testTestInLastNameGrantsNothing(): void {
        $this->assertNotContains('ROLE_TESTER', $this->makeUser(lastName: 'McTESTerson')->getRoles());
    }

    /**
     * Used to throw a TypeError: str_contains() was given a null last name.
     */
    public function testUserWithoutALastNameDoesNotCrash(): void {
        $this->assertSame(['ROLE_USER'], array_values($this->makeUser(id: 5, lastName: null)->getRoles()));
    }
}
