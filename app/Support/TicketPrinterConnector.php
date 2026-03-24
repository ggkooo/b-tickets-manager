<?php

namespace App\Support;

use RuntimeException;

class TicketPrinterConnector
{
    public static function resolve(array $config): array
    {
        $host = trim((string) ($config['host'] ?? ''));

        if ($host === '') {
            throw new RuntimeException('Host da impressora nao configurado em TICKET_PRINTER_HOST.');
        }

        $portRaw = $config['port'] ?? 9100;

        if (is_string($portRaw)) {
            $portRaw = trim($portRaw);
        }

        if ($portRaw === '' || $portRaw === null) {
            $portRaw = 9100;
        }

        if (!is_numeric($portRaw)) {
            throw new RuntimeException('Porta da impressora invalida em TICKET_PRINTER_PORT.');
        }

        $port = (int) $portRaw;

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Porta da impressora invalida em TICKET_PRINTER_PORT.');
        }

        return [
            'host' => $host,
            'port' => $port,
        ];
    }
}