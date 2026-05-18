<?php
declare(strict_types=1);
namespace Pixiekat\SymfonyHelpers\Tests\Entity;

use PHPUnit\Framework\TestCase;
use Pixiekat\SymfonyHelpers\Entity\Ban;
use Pixiekat\SymfonyHelpers\Interfaces\Entity\BanInterface;

class BanTest extends TestCase {
    private Ban $ban;

    public function testImplementsBanInterface(): void {
        $this->assertInstanceOf(BanInterface::class, $this->ban);
    }

    public function testConstructorSetsCreatedAt(): void {
        $before = new \DateTimeImmutable('-1 second');
        $after = new \DateTimeImmutable('+1 second');

        $this->assertGreaterThan($before, $this->ban->getCreatedAt());
        $this->assertLessThan($after, $this->ban->getCreatedAt());
    }

    public function testIdIsNullByDefault(): void {
        $this->assertNull($this->ban->getId());
    }

    public function testSetAndGetId(): void {
        $this->ban->setId(42);
        $this->assertSame(42, $this->ban->getId());
    }

    public function testSetAndGetIpAddress(): void {
        $this->ban->setIpAddress('192.168.1.100');
        $this->assertSame('192.168.1.100', $this->ban->getIpAddress());
    }

    public function testSetIpAddressReturnsSelf(): void {
        $result = $this->ban->setIpAddress('10.0.0.1');
        $this->assertSame($this->ban, $result);
    }

    public function testExpiresAtIsNullByDefault(): void {
        $this->assertNull($this->ban->getExpiresAt());
    }

    public function testSetAndGetExpiresAt(): void {
        $expiresAt = new \DateTimeImmutable('2027-01-01 00:00:00');
        $this->ban->setExpiresAt($expiresAt);
        $this->assertSame($expiresAt, $this->ban->getExpiresAt());
    }

    public function testSetExpiresAtToNull(): void {
        $this->ban->setExpiresAt(new \DateTimeImmutable('2027-01-01'));
        $this->ban->setExpiresAt(null);
        $this->assertNull($this->ban->getExpiresAt());
    }

    public function testSetExpiresAtReturnsSelf(): void {
        $result = $this->ban->setExpiresAt(null);
        $this->assertSame($this->ban, $result);
    }

    public function testSetCreatedAt(): void {
        $createdAt = new \DateTimeImmutable('2025-06-15 12:00:00');
        $this->ban->setCreatedAt($createdAt);
        $this->assertSame($createdAt, $this->ban->getCreatedAt());
    }

    protected function setUp(): void {
        $this->ban = new Ban();
    }
}
