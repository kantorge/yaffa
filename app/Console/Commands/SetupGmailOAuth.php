<?php

namespace App\Console\Commands;

use Google\Client as GoogleClient;
use Google\Service\Gmail as GmailService;
use Illuminate\Console\Command;

class SetupGmailOAuth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'yaffa:setup-gmail-oauth';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactive setup for Gmail OAuth2 authentication';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Gmail OAuth2 Setup for YAFFA');
        $this->line('');

        // Step 1: Check if credentials exist
        $clientId = config('yaffa.gmail.client_id');
        $clientSecret = config('yaffa.gmail.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            $this->error('Gmail Client ID and Secret are not configured!');
            $this->line('');
            $this->info('Steps to set up:');
            $this->line('1. Go to https://console.cloud.google.com/');
            $this->line('2. Create a new project or select existing one');
            $this->line('3. Enable Gmail API');
            $this->line('4. Create OAuth 2.0 credentials (Desktop app)');
            $this->line('5. Add the following to your .env file:');
            $this->line('   GMAIL_CLIENT_ID=your-client-id');
            $this->line('   GMAIL_CLIENT_SECRET=your-client-secret');
            $this->line('');
            return Command::FAILURE;
        }

        $this->info('Client ID and Secret found!');
        $this->line('');

        // Step 2: Get authorization code
        $client = new GoogleClient();
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setAccessType('offline');
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->addScope(GmailService::GMAIL_READONLY);
        $client->addScope(GmailService::GMAIL_MODIFY);

        // Disable SSL verification for local development (Windows with Avast/antivirus)
        $httpClient = new \GuzzleHttp\Client([
            'verify' => false,
        ]);
        $client->setHttpClient($httpClient);

        $authUrl = $client->createAuthUrl();

        $this->info('Step 1: Authorize this application');
        $this->line('Open this URL in your browser:');
        $this->line($authUrl);
        $this->line('');

        $authCode = $this->ask('Enter the authorization code from Google');

        if (empty($authCode)) {
            $this->error('No authorization code provided!');
            return Command::FAILURE;
        }

        try {
            // Exchange authorization code for refresh token
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                $this->error('Error getting access token: ' . $accessToken['error']);
                return Command::FAILURE;
            }

            if (!isset($accessToken['refresh_token'])) {
                $this->error('No refresh token received. You may need to revoke access and try again.');
                $this->line('Go to: https://myaccount.google.com/permissions');
                return Command::FAILURE;
            }

            $refreshToken = $accessToken['refresh_token'];

            $this->line('');
            $this->info('Success! Add this to your .env file:');
            $this->line('');
            $this->line('GMAIL_REFRESH_TOKEN=' . $refreshToken);
            $this->line('GMAIL_API_ENABLED=true');
            $this->line('GMAIL_USER_EMAIL=your-email@gmail.com');
            $this->line('');
            $this->info('Then you can run: php artisan yaffa:fetch-gmail-emails');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

