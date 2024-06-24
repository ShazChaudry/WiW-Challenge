<?php

use PHPUnit\Framework\TestCase;
use App\Controller\ShiftController;
use Symfony\Component\HttpFoundation\JsonResponse;

class ShiftControllerTest extends TestCase
{
    private $shiftController;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shiftController = new ShiftController();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function testProcessShifts()
    {
        $response = $this->shiftController->processShifts();
        $this->assertInstanceOf(JsonResponse::class, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        $firstItem = $data[0];
        $this->assertIsArray($firstItem);
        $this->assertArrayHasKey('EmployeeID', $firstItem);
        $this->assertArrayHasKey('StartOfWeek', $firstItem);
        $this->assertArrayHasKey('RegularHours', $firstItem);
        $this->assertArrayHasKey('OvertimeHours', $firstItem);
        $this->assertArrayHasKey('InvalidShifts', $firstItem);
    }
}