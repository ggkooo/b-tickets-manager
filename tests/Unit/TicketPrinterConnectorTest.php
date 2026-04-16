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
            'connection' => 'network',
            'host' => '200.132.193.233',
            'port' => '9100',
        ]);

        $this->assertSame('network', $connection['connection']);
        $this->assertSame('200.132.193.233', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_uses_default_port_when_not_informed(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection' => 'network',
            'host' => '10.0.0.25',
        ]);

        $this->assertSame('network', $connection['connection']);
        $this->assertSame('10.0.0.25', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_resolves_cups_queue_from_configuration(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection' => 'cups',
            'cups_queue' => 'FilaTermicaRecepcao',
        ]);

        $this->assertSame('cups', $connection['connection']);
        $this->assertSame('FilaTermicaRecepcao', $connection['cups_queue']);
    }

    public function test_it_handles_windows_share_connection_for_current_platform(): void
    {
        if (PHP_OS === 'Darwin') {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Impressora compartilhada do Windows nao e suportada diretamente no macOS. Use o modo cups com uma fila CUPS local.');

            TicketPrinterConnector::resolve([
                'connection' => 'windows_share',
                'smb_uri' => 'smb://servidor/termica',
            ]);

            return;
        }

        $connection = TicketPrinterConnector::resolve([
            'connection' => 'windows_share',
            'smb_uri' => 'smb://servidor/termica',
        ]);

        $this->assertSame('windows_share', $connection['connection']);
        $this->assertSame('smb://servidor/termica', $connection['smb_uri']);
    }

    public function test_it_requires_host(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Host da impressora nao configurado em TICKET_PRINTER_HOST.');

        TicketPrinterConnector::resolve([
            'connection' => 'network',
            'port' => 9100,
        ]);
    }

    public function test_it_requires_valid_port(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Porta da impressora invalida em TICKET_PRINTER_PORT.');

        TicketPrinterConnector::resolve([
            'connection' => 'network',
            'host' => '200.132.193.233',
            'port' => 70000,
        ]);
    }

    public function test_it_requires_cups_queue(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Fila CUPS da impressora nao configurada em TICKET_PRINTER_CUPS_QUEUE.');

        TicketPrinterConnector::resolve([
            'connection' => 'cups',
        ]);
    }

    public function test_it_requires_valid_connection_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tipo de conexao da impressora invalido em TICKET_PRINTER_CONNECTION.');

        TicketPrinterConnector::resolve([
            'connection' => 'bluetooth',
        ]);
    }
}