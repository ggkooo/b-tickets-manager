<?php

namespace App\Support;

use RuntimeException;

class TicketPrinterConnector
{
    public static function resolve(array $config): array
    {
        $connectionType = trim((string) ($config['connection_type'] ?? 'network'));

        if ($connectionType === 'shared_windows') {
            $sharePath = trim((string) ($config['share_path'] ?? ''));

            if ($sharePath === '') {
                throw new RuntimeException('Caminho da impressora compartilhada nao configurado em share_path.');
            }

            $resolvedSharePath = self::buildSharedWindowsPath($sharePath);

            return [
                'connection_type' => 'shared_windows',
                'share_path' => $resolvedSharePath,
                'display_share_path' => self::redactCredentials($resolvedSharePath),
            ];
        }

        if ($connectionType !== 'network') {
            throw new RuntimeException('Tipo de conexao de impressora invalido.');
        }

        $host = trim((string) ($config['host'] ?? ''));

        if ($host === '') {
            throw new RuntimeException('Host da impressora nao configurado.');
        }

        $portRaw = $config['port'] ?? 9100;

        if (is_string($portRaw)) {
            $portRaw = trim($portRaw);
        }

        if ($portRaw === '' || $portRaw === null) {
            $portRaw = 9100;
        }

        if (!is_numeric($portRaw)) {
            throw new RuntimeException('Porta da impressora invalida.');
        }

        $port = (int) $portRaw;

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Porta da impressora invalida.');
        }

        return [
            'connection_type' => 'network',
            'host' => $host,
            'port' => $port,
        ];
    }

    private static function buildSharedWindowsPath(string $sharePath): string
    {
        $smbPath = self::convertUncPathToSmb($sharePath);

        if (!str_starts_with($smbPath, 'smb://')) {
            return $smbPath;
        }

        $parsed = parse_url($smbPath);

        if ($parsed === false || !isset($parsed['host'], $parsed['path'])) {
            throw new RuntimeException('share_path da impressora compartilhada e invalido.');
        }

        if (isset($parsed['user'])) {
            return $smbPath;
        }

        $host = $parsed['host'];
        $path = ltrim($parsed['path'], '/');
        $workgroup = trim((string) config('app.printer_smb_workgroup', ''));

        if ($workgroup !== '' && !str_contains($path, '/')) {
            $path = $workgroup . '/' . $path;
        }

        $username = trim((string) config('app.printer_smb_username', ''));
        $password = (string) config('app.printer_smb_password', '');

        if ($username === '') {
            return 'smb://' . $host . '/' . $path;
        }

        $credentials = $username;

        if ($password !== '') {
            $credentials .= ':' . $password;
        }

        return 'smb://' . $credentials . '@' . $host . '/' . $path;
    }

    public static function redactCredentials(string $sharePath): string
    {
        $parsed = parse_url($sharePath);

        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            return $sharePath;
        }

        $path = $parsed['path'] ?? '';

        if (!isset($parsed['user'])) {
            return $parsed['scheme'] . '://' . $parsed['host'] . $path;
        }

        return $parsed['scheme'] . '://' . '***:***@' . $parsed['host'] . $path;
    }

    /**
     * Converte um caminho UNC (\\computer\printer) para formato SMB (smb://computer/printer)
     * necessário pelo WindowsPrintConnector da biblioteca escpos
     */
    private static function convertUncPathToSmb(string $uncPath): string
    {
        // Remove espaços em branco
        $uncPath = trim($uncPath);

        // Se já está em formato SMB, retorna como está
        if (str_starts_with($uncPath, 'smb://')) {
            return $uncPath;
        }

        // Remove barras invertidas do início (\\)
        if (str_starts_with($uncPath, '\\\\')) {
            $uncPath = substr($uncPath, 2);
        }

        // Replace a primeira barra invertida por barra normal
        $smbPath = str_replace('\\', '/', $uncPath);

        return 'smb://' . $smbPath;
    }
}