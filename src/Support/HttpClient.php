<?php

namespace Phpwebterm\Support;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

trait HttpClient
{
    /**
     * Send a POST request with Guzzle
     *
     * @param  string  $url  The endpoint URL
     * @param  array  $data  The data to send in the request body
     * @param  array  $headers  Optional headers for the request
     * @param  bool  $asJson  Whether to send the data as JSON (default: false)
     * @return array|null Response data as an array [status, body, headers]
     *
     * @throws Exception on request failure
     */
    public function sendPostRequest(string $url, array $data = [], array $headers = [], bool $asJson = false): ?array
    {
        $client = new Client;

        try {
            $options = [
                $asJson ? 'json' : 'form_params' => $data,
                'headers' => $headers,
            ];

            $response = $client->post($url, $options);

            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException|GuzzleException $e) {
            throw new Exception('Request failed: '.$e->getMessage(), $e->getCode(), $e);
        }
    }
}
