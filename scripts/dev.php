<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
chdir($projectRoot);

$dryRun = in_array('--dry-run', $argv, true);
$isWindows = DIRECTORY_SEPARATOR === '\\';
$isSail = filter_var(getenv('LARAVEL_SAIL'), FILTER_VALIDATE_BOOL)
    || getenv('LARAVEL_SAIL') === '1';

$commands = $isSail
    ? [
        [
            'name' => 'vite',
            'command' => ['npm', 'run', 'dev', '--', '--host', '0.0.0.0'],
        ],
    ]
    : [
        [
            'name' => 'server',
            'command' => [PHP_BINARY, 'artisan', 'serve'],
        ],
        [
            'name' => 'vite',
            'command' => ['npm', 'run', 'dev'],
        ],
    ];

if ($dryRun) {
    foreach ($commands as $process) {
        fwrite(STDOUT, $process['name'].': '.formatCommand($process['command'], $isWindows).PHP_EOL);
    }

    exit(0);
}

if ($isSail) {
    fwrite(STDOUT, 'Sail already serves Laravel on http://localhost; starting Vite only.'.PHP_EOL);
}

$processes = [];

foreach ($commands as $process) {
    $descriptorSpec = [
        0 => ['file', $isWindows ? 'NUL' : '/dev/null', 'r'],
        1 => ['file', 'php://stdout', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ];

    $command = $process['command'];

    if ($isWindows) {
        $command = formatCommand($command, true);
    }

    $resource = proc_open($command, $descriptorSpec, $pipes, $projectRoot, null, [
        'bypass_shell' => !$isWindows,
    ]);

    if (! is_resource($resource)) {
        terminateProcesses($processes);
        fwrite(STDERR, sprintf('Failed to start "%s".', $process['name']).PHP_EOL);
        exit(1);
    }

    $processes[] = [
        'name' => $process['name'],
        'resource' => $resource,
    ];
}

$exitCode = 0;

try {
    while ($processes !== []) {
        foreach ($processes as $index => $process) {
            $status = proc_get_status($process['resource']);

            if ($status['running']) {
                continue;
            }

            $code = $status['exitcode'];
            proc_close($process['resource']);
            unset($processes[$index]);

            if ($code !== 0) {
                $exitCode = $code;
                terminateProcesses($processes);
                break 2;
            }
        }

        usleep(200000);
    }
} finally {
    terminateProcesses($processes);
}

exit($exitCode);

/**
 * @param  array<int, string>  $command
 */
function formatCommand(array $command, bool $isWindows): string
{
    $escaped = array_map(
        static function (string $part) use ($isWindows): string {
            return $isWindows ? windowsEscape($part) : escapeshellarg($part);
        },
        $command,
    );

    return implode(' ', $escaped);
}

/**
 * @param  array<int, array{name: string, resource: resource}>  $processes
 */
function terminateProcesses(array $processes): void
{
    foreach ($processes as $process) {
        if (! is_resource($process['resource'])) {
            continue;
        }

        $status = proc_get_status($process['resource']);

        if ($status['running']) {
            proc_terminate($process['resource']);
        }

        proc_close($process['resource']);
    }
}

function windowsEscape(string $value): string
{
    if ($value === '') {
        return '""';
    }

    if (! preg_match('/[\s"]/u', $value)) {
        return $value;
    }

    $value = preg_replace('/(\\\\*)"/', '$1$1\\"', $value) ?? $value;
    $value = preg_replace('/(\\\\+)$/', '$1$1', $value) ?? $value;

    return '"'.$value.'"';
}
