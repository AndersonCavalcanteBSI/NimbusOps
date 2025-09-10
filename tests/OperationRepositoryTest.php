<?php

declare(strict_types=1);


use PHPUnit\Framework\TestCase;
use App\Repositories\OperationRepository;


final class OperationRepositoryTest extends TestCase
{
    public function testPaginationReturnsStructure(): void
    {
        $repo = new OperationRepository();
        $result = $repo->paginate([], 1, 1);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
    }
}
