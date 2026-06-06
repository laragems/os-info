<?php

declare(strict_types=1);

namespace Laragems\OsInfo\Support;

final class CommandRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, float $timeoutSeconds = 2.0): ?string
    {
        if ($command === [] || !function_exists('proc_open')) {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $startedAt = microtime(true);

        while (true) {
            $stdout .= stream_get_contents($pipes[1]);
            $status = proc_get_status($process);

            if (!$status['running']) {
                break;
            }

            if ((microtime(true) - $startedAt) > $timeoutSeconds) {
                proc_terminate($process);
                $this->closePipes($pipes);
                proc_close($process);

                return null;
            }

            usleep(10000);
        }

        $stdout .= stream_get_contents($pipes[1]);
        $this->closePipes($pipes);
        proc_close($process);

        $stdout = trim(str_replace("\r\n", "\n", $stdout));

        return $stdout === '' ? null : $stdout;
    }

    /**
     * @param array<int, resource> $pipes
     */
    private function closePipes(array $pipes): void
    {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
    }
}

