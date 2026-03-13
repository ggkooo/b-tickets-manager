<?php

namespace App\Support;

use RuntimeException;

class TicketPrinterConnector
{
    public static function resolve(array $config): string
    {
        $connector = trim((string) ($config['connector'] ?? ''));

        if ($connector === '') {
            throw new RuntimeException('Conector da impressora nao configurado em TICKET_PRINTER_CONNECTOR.');
        }

        if (!str_starts_with(strtolower($connector), 'smb://')) {
            return $connector;
        }

        $parts = parse_url($connector);

        if ($parts === false) {
            return $connector;
        }

        if (isset($parts['user'])) {
            return $connector;
        }

        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');

        if ($password !== '' && $username === '') {
            throw new RuntimeException('TICKET_PRINTER_PASSWORD requer TICKET_PRINTER_USERNAME para conexao SMB.');
        }

        if ($username === '') {
            return $connector;
        }

        $authority = $parts['host'] ?? null;

        if ($authority === null || !isset($parts['path'])) {
            return $connector;
        }

        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $credentials = $username;

        if ($password !== '') {
            $credentials .= ':' . $password;
        }

        return 'smb://' . $credentials . '@' . $authority . $parts['path'];
    }
}