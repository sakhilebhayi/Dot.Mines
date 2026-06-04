<?php

namespace App\Contracts;

interface BellIso15143ServiceInterface
{
    /**
     * Execute a full fleet data sync cycle:
     * fetch XML → parse → validate → persist → KPIs → audit.
     *
     * @return array{success: bool, processed: int, inserted: int, updated: int, error?: string}
     */
    public function sync(): array;
}
