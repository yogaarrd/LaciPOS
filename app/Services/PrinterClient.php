<?php

namespace App\Services;

use Native\Laravel\Client\Client;
use Illuminate\Http\Client\Response;

class PrinterClient extends Client
{
    public function get(string $endpoint, array|string|null $query = null): Response
    {
        return $this->client->connectTimeout(3)->timeout(5)->get($endpoint, $query);
    }

    public function post(string $endpoint, array $data = []): Response
    {
        // Do not retry: a timeout can happen after the OS has accepted the job.
        return $this->client->connectTimeout(3)->timeout(60)->post($endpoint, $data);
    }
}
