<?php

namespace App\Support;

use RuntimeException;

class TicketPrinterConnector
{
    public static function resolve(array $config): array
    {
        $connection = trim((string) ($config['connection'] ?? 'network'));

        return match ($connection) {
            'network' => self::resolveNetworkConnection($config),
            'windows_share' => self::resolveWindowsShareConnection($config),
            'cups' => self::resolveCupsConnection($config),
            default => throw new RuntimeException('Tipo de conexao da impressora invalido em TICKET_PRINTER_CONNECTION.'),
        };
    }

    private static function resolveNetworkConnection(array $config): array
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
            'connection' => 'network',
            'host' => $host,
            'port' => $port,
        ];
    }

    private static function resolveWindowsShareConnection(array $config): array
    {
        if (PHP_OS === 'Darwin') {
            throw new RuntimeException('Impressora compartilhada do Windows nao e suportada diretamente no macOS. Use o modo cups com uma fila CUPS local.');
        }

        $uri = trim((string) ($config['smb_uri'] ?? ''));

        if ($uri === '') {
            throw new RuntimeException('URI da impressora compartilhada nao configurada em TICKET_PRINTER_SMB_URI.');
        }

        if (!preg_match('/^smb:\/\/.+\/.+$/', $uri)) {
            throw new RuntimeException('URI da impressora compartilhada invalida em TICKET_PRINTER_SMB_URI. Use o formato smb://servidor/impressora.');
        }

        return [
            'connection' => 'windows_share',
            'smb_uri' => $uri,
        ];
    }

    private static function resolveCupsConnection(array $config): array
    {
        $queue = trim((string) ($config['cups_queue'] ?? ''));

        if ($queue === '') {
            throw new RuntimeException('Fila CUPS da impressora nao configurada em TICKET_PRINTER_CUPS_QUEUE.');
        }

        return [
            'connection' => 'cups',
            'cups_queue' => $queue,
        ];
    }
}