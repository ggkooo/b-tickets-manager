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
            'connection_type' => 'network',
            'host' => 'IJ50D19',
            'port' => '9100',
        ]);

        $this->assertSame('network', $connection['connection_type']);
        $this->assertSame('IJ50D19', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_uses_default_port_when_not_informed(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection_type' => 'network',
            'host' => '10.0.0.25',
        ]);

        $this->assertSame('network', $connection['connection_type']);
        $this->assertSame('10.0.0.25', $connection['host']);
        $this->assertSame(9100, $connection['port']);
    }

    public function test_it_resolves_shared_windows_printer_path(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection_type' => 'shared_windows',
            'share_path' => '\\\\PC-ATENDIMENTO\\EPSON-TM-T20',
        ]);

        $this->assertSame('shared_windows', $connection['connection_type']);
        $this->assertSame('smb://PC-ATENDIMENTO/EPSON-TM-T20', $connection['share_path']);
    }

    public function test_it_converts_unc_path_to_smb_format_with_ip(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection_type' => 'shared_windows',
            'share_path' => '\\\\200.132.194.29\\EPSON-TM-T20X',
        ]);

        $this->assertSame('shared_windows', $connection['connection_type']);
        $this->assertSame('smb://200.132.194.29/EPSON-TM-T20X', $connection['share_path']);
    }

    public function test_it_handles_smb_path_already_in_correct_format(): void
    {
        $connection = TicketPrinterConnector::resolve([
            'connection_type' => 'shared_windows',
            'share_path' => 'smb://200.132.194.29/EPSON-TM-T20X',
        ]);

        $this->assertSame('shared_windows', $connection['connection_type']);
        $this->assertSame('smb://200.132.194.29/EPSON-TM-T20X', $connection['share_path']);
    }

    public function test_it_requires_host(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Host da impressora nao configurado.');

        TicketPrinterConnector::resolve([
            'connection_type' => 'network',
            'port' => 9100,
        ]);
    }

    public function test_it_requires_valid_port(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Porta da impressora invalida.');

        TicketPrinterConnector::resolve([
            'connection_type' => 'network',
            'host' => '200.132.193.233',
            'port' => 70000,
        ]);
    }

    public function test_it_requires_share_path_for_shared_windows_mode(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Caminho da impressora compartilhada nao configurado em share_path.');

        TicketPrinterConnector::resolve([
            'connection_type' => 'shared_windows',
        ]);
    }
}