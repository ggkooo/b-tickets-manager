<?php

namespace Tests\Unit;

use App\Support\TicketPrinterConnector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TicketPrinterConnectorTest extends TestCase
{
    public function test_it_resolves_network_host_and_port_from_configuration(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'host' => '200.132.193.233',
            'port' => '9100',
        ]);

        $this->assertSame('200.132.193.233', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_uses_default_port_when_not_informed(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'host' => '10.0.0.25',
        ]);

        $this->assertSame('10.0.0.25', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_requires_host(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Host da impressora nao configurado em TICKET_PRINTER_HOST.');

        TicketPrinterConnector::resolve([
            'port' => 9100,
        ]);
    }

    public function test_it_requires_valid_port(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Porta da impressora invalida em TICKET_PRINTER_PORT.');

        TicketPrinterConnector::resolve([
            'host' => '200.132.193.233',
            'port' => 70000,
        ]);
    }
}