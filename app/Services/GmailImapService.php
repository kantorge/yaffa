<?php

namespace App\Services;

use App\Events\IncomingEmailReceived;
use App\Models\ReceivedMail;
use App\Models\User;
use Exception;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GmailService;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class GmailImapService
{
    protected array $config;
    protected array $whitelist;
    protected ?GmailService $service = null;

    public function __construct()
    {
        $this->config = config('yaffa.gmail');
        $this->whitelist = $this->config['whitelist'] ?? [];
    }

    /**
     * Check if Gmail API is enabled
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] && 
               !empty($this->config['client_id']) && 
               !empty($this->config['client_secret']) &&
               !empty($this->config['refresh_token']);
    }

    /**
     * Check if a sender email is whitelisted
     */
    public function isWhitelisted(string $email): bool
    {
        // If whitelist is empty, allow all senders
        if (empty($this->whitelist)) {
            return true;
        }

        $email = strtolower(trim($email));
        
        foreach ($this->whitelist as $whitelistedEmail) {
            if (strtolower(trim($whitelistedEmail)) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build Gmail search query for whitelisted senders without YAFFA_PROCESSED label
     */
    protected function buildSearchQuery(string $labelId): string
    {
        // If no whitelist, return empty (don't search at all)
        if (empty($this->whitelist)) {
            return '';
        }

        // Build query: (from:sender1 OR from:sender2 OR ...) -label:YAFFA_PROCESSED
        $fromClauses = [];
        foreach ($this->whitelist as $email) {
            $email = trim($email);
            if (!empty($email)) {
                $fromClauses[] = "from:{$email}";
            }
        }

        if (empty($fromClauses)) {
            return '';
        }

        // Combine with OR and add label exclusion
        $query = '(' . implode(' OR ', $fromClauses) . ') -label:YAFFA_PROCESSED';

        return $query;
    }

    /**
     * Initialize Gmail API client
     */
    public function getGmailService(): GmailService
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $client = new GoogleClient();
        $client->setClientId($this->config['client_id']);
        $client->setClientSecret($this->config['client_secret']);
        $client->setAccessType('offline');
        $client->setRedirectUri('urn:ietf:wg:oauth:2.0:oob');
        $client->addScope(GmailService::GMAIL_READONLY);
        $client->addScope(GmailService::GMAIL_MODIFY);

        // Disable SSL verification for local development (Windows with Avast/antivirus)
        if (app()->environment('local')) {
            $httpClient = new \GuzzleHttp\Client([
                'verify' => false,
            ]);
            $client->setHttpClient($httpClient);
        }

        // Set the refresh token
        $client->refreshToken($this->config['refresh_token']);

        $this->service = new GmailService($client);
        
        return $this->service;
    }

    /**
     * Fetch unread emails from Gmail inbox
     */
    public function fetchUnreadEmails(): array
    {
        if (!$this->isEnabled()) {
            Log::info('Gmail API is not enabled or not configured');
            return [];
        }

        try {
            $service = $this->getGmailService();
            $userEmail = $this->config['user_email'] ?? 'me';

            // Get or create the YAFFA_PROCESSED label
            $labelId = $this->getOrCreateLabel($service, $userEmail);

            Log::info('Gmail: Searching for unprocessed emails', ['label_id' => $labelId]);

            // Build Gmail search query to only fetch emails from whitelisted senders
            // that don't have the YAFFA_PROCESSED label
            $searchQuery = $this->buildSearchQuery($labelId);

            if (empty($searchQuery)) {
                Log::info('Gmail: No whitelist configured or empty whitelist');
                return [];
            }

            Log::info('Gmail: Search query', ['query' => $searchQuery]);

            // Search for messages matching our criteria
            $results = $service->users_messages->listUsersMessages(
                $userEmail,
                [
                    'q' => $searchQuery,
                    'maxResults' => 50
                ]
            );

            $messages = $results->getMessages();

            if (empty($messages)) {
                Log::info('Gmail: No unprocessed emails found from whitelisted senders');
                return [];
            }

            Log::info('Gmail: Found unprocessed emails', ['count' => count($messages)]);

            $processedEmails = [];

            foreach ($messages as $message) {
                try {
                    // Fetch full message
                    $fullMessage = $service->users_messages->get(
                        $userEmail,
                        $message->getId(),
                        ['format' => 'full']
                    );

                    // Extract headers
                    $headers = $fullMessage->getPayload()->getHeaders();
                    $fromAddress = null;
                    $subject = null;
                    $messageId = null;

                    foreach ($headers as $header) {
                        if ($header->getName() === 'From') {
                            // Extract email from "Name <email@domain.com>" format
                            if (preg_match('/<(.+?)>/', $header->getValue(), $matches)) {
                                $fromAddress = $matches[1];
                            } else {
                                $fromAddress = $header->getValue();
                            }
                        } elseif ($header->getName() === 'Subject') {
                            $subject = $header->getValue();
                        } elseif ($header->getName() === 'Message-ID') {
                            $messageId = $header->getValue();
                        }
                    }

                    if (!$fromAddress) {
                        Log::warning('Gmail: Could not extract sender email from message', [
                            'message_id' => $message->getId(),
                        ]);
                        
                        // Mark with label to skip in future
                        $this->applyLabel($service, $userEmail, $message->getId(), $labelId);
                        continue;
                    }

                    // Skip whitelist check - we already filtered by whitelist in the search query

                    // Use the Gmail account owner as the user for all imported emails
                    // Find user by the Gmail account email (GMAIL_USER_EMAIL)
                    $user = User::where('email', $this->config['user_email'])->first();
                    
                    if (!$user) {
                        Log::error('Gmail: No user found for Gmail account owner', [
                            'gmail_email' => $this->config['user_email'],
                        ]);
                        
                        // Mark with label to skip in future
                        $this->applyLabel($service, $userEmail, $message->getId(), $labelId);
                        continue;
                    }

                    // Check if we already have this email
                    if (!$messageId) {
                        $messageId = 'gmail-' . $message->getId();
                    }

                    if (ReceivedMail::where('message_id', $messageId)->exists()) {
                        Log::info('Gmail: Email already exists in database', [
                            'message_id' => $messageId,
                        ]);
                        
                        // Mark with label to skip in future
                        $this->applyLabel($service, $userEmail, $message->getId(), $labelId);
                        continue;
                    }

                    // Extract body content
                    $htmlBody = '';
                    $textBody = '';
                    $this->extractBody($fullMessage->getPayload(), $htmlBody, $textBody);

                    // If no text body, strip HTML
                    if (empty($textBody) && !empty($htmlBody)) {
                        $textBody = strip_tags($htmlBody);
                    }

                    // Store the original sender in the email for reference
                    $emailNote = "From: {$fromAddress}\n\n";

                    // Create received mail record
                    $receivedMail = ReceivedMail::create([
                        'message_id' => $messageId,
                        'user_id' => $user->id,
                        'subject' => $subject ?? __('(No subject)'),
                        'html' => $htmlBody,
                        'text' => $emailNote . $textBody,
                        'processed' => false,
                        'handled' => false,
                    ]);

                    // Fire event to process the email
                    event(new IncomingEmailReceived($receivedMail));

                    // Apply YAFFA_PROCESSED label (don't mark as read)
                    $this->applyLabel($service, $userEmail, $message->getId(), $labelId);

                    $processedEmails[] = [
                        'message_id' => $messageId,
                        'from' => $fromAddress,
                        'subject' => $subject ?? 'No subject',
                    ];

                    Log::info('Gmail: Successfully processed email', [
                        'message_id' => $messageId,
                        'from' => $fromAddress,
                        'subject' => $subject,
                    ]);

                } catch (Exception $e) {
                    Log::error('Gmail: Error processing individual message', [
                        'message_id' => $message->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $processedEmails;

        } catch (Exception $e) {
            Log::error('Gmail: Error fetching emails', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get or create the YAFFA_PROCESSED label
     */
    protected function getOrCreateLabel(GmailService $service, string $userEmail): string
    {
        try {
            // List all labels
            $labels = $service->users_labels->listUsersLabels($userEmail);
            
            // Check if YAFFA_PROCESSED label exists
            foreach ($labels->getLabels() as $label) {
                if ($label->getName() === 'YAFFA_PROCESSED') {
                    Log::info('Gmail: Found existing YAFFA_PROCESSED label', ['id' => $label->getId()]);
                    return $label->getId();
                }
            }

            // Create the label if it doesn't exist
            $newLabel = new \Google\Service\Gmail\Label();
            $newLabel->setName('YAFFA_PROCESSED');
            $newLabel->setLabelListVisibility('labelShow');
            $newLabel->setMessageListVisibility('show');

            $createdLabel = $service->users_labels->create($userEmail, $newLabel);
            
            Log::info('Gmail: Created YAFFA_PROCESSED label', ['id' => $createdLabel->getId()]);
            
            return $createdLabel->getId();

        } catch (Exception $e) {
            Log::error('Gmail: Error getting/creating label', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply the YAFFA_PROCESSED label to a message
     */
    protected function applyLabel(GmailService $service, string $userEmail, string $messageId, string $labelId): void
    {
        try {
            $service->users_messages->modify(
                $userEmail,
                $messageId,
                new \Google\Service\Gmail\ModifyMessageRequest([
                    'addLabelIds' => [$labelId]
                ])
            );
        } catch (Exception $e) {
            Log::error('Gmail: Error applying label', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Recursively extract body content from message parts
     */
    protected function extractBody($part, &$htmlBody, &$textBody): void
    {
        if ($part->getBody()->getSize() > 0) {
            $data = $part->getBody()->getData();
            $data = base64_decode(strtr($data, '-_', '+/'));

            if ($part->getMimeType() === 'text/html') {
                $htmlBody .= $data;
            } elseif ($part->getMimeType() === 'text/plain') {
                $textBody .= $data;
            }
        }

        if ($part->getParts()) {
            foreach ($part->getParts() as $subPart) {
                $this->extractBody($subPart, $htmlBody, $textBody);
            }
        }
    }
}
