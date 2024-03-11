<?php
namespace Manomite\Engine\Http;

use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;

class Webhook
{
    private $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    private function createHash($data, $secretKey)
    {
        ksort($data);
        $jsonData = json_encode($data);
        $hash = hash_hmac('sha512', $jsonData, $secretKey);
        return $hash;
    }

    public function sendDataToWebhook($url, $jsonData, $secretKey, $retryAttempts = 5) {
        $client = new Client();
    
        for ($retry = 1; $retry <= $retryAttempts; $retry++) {
            try {
                $hash = $this->createHash($jsonData, $secretKey);
    
                $response = $client->post($url, [
                    'json' => $jsonData,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        APP_NAME.'-X-Signature' => $hash,
                    ],
                ]);
                return $response->getBody()->getContents();
            } catch (RequestException $e) {
                // Log the error or perform other error handling
                echo "Error: " . $e->getMessage() . "\n";
    
                // Retry if there are attempts remaining
                if ($retry < $retryAttempts) {
                    echo "Retrying... (Attempt $retry)\n";
                    // Introduce a delay before retrying (you can adjust this as needed)
                    sleep(5);
                } else {
                    // No more retry attempts, halt
                    echo "Max retries reached. Halting.\n";
                    throw $e; // You might want to handle this differently based on your use case
                }
            }
        }
    }
}