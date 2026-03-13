<?php

namespace Tests\Unit;

use App\Support\TicketPrinterConnector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TicketPrinterConnectorTest extends TestCase
{
    public function test_it_injects_smb_credentials_from_configuration(): void
    {
        $connector = TicketPrinterConnector::resolve([
            'connector' => 'smb://PRINT-SERVER/EPSON-TM-T20X',
            'username' => 'print-user',
            'password' => 'print-secret',
        ]);

        $this->assertSame('smb://print-user:print-secret@PRINT-SERVER/EPSON-TM-T20X', $connector);
    }

    public function test_it_keeps_existing_credentials_in_connector(): void
    {
        $connector = TicketPrinterConnector::resolve([
            'connector' => 'smb://embedded-user:embedded-pass@PRINT-SERVER/EPSON-TM-T20X',
            'username' => 'print-user',
            'password' => 'print-secret',
        ]);

        $this->assertSame('smb://embedded-user:embedded-pass@PRINT-SERVER/EPSON-TM-T20X', $connector);
    }

    public function test_it_requires_username_when_password_is_informed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('TICKET_PRINTER_PASSWORD requer TICKET_PRINTER_USERNAME para conexao SMB.');

        TicketPrinterConnector::resolve([
            'connector' => 'smb://PRINT-SERVER/EPSON-TM-T20X',
            'password' => 'print-secret',
        ]);
    }
}