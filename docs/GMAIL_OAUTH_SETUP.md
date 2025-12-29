# Gmail OAuth2 Setup for YAFFA

This guide will help you set up Gmail integration using OAuth2 authentication (Sign in with Google) to automatically fetch receipt emails.

## Features

- ✅ **OAuth2 Authentication**: Secure "Sign in with Google" instead of passwords
- ✅ **Sender Whitelist**: Only import emails from approved senders
- ✅ **User Matching**: Only processes emails from registered YAFFA users  
- ✅ **AI Processing**: Automatically processes receipts using OpenAI
- ✅ **Duplicate Prevention**: Won't import the same email twice

## Prerequisites

1. A Google Cloud Platform account
2. Gmail account to fetch emails from
3. YAFFA user account with matching email address
4. Composer package already installed: `google/apiclient` ✅

## Setup Steps

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Note your project name

### 2. Enable Gmail API

1. In your project, go to **APIs & Services** → **Library**
2. Search for "Gmail API"
3. Click on it and press **Enable**

### 3. Create OAuth 2.0 Credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth client ID**
3. If prompted, configure the OAuth consent screen:
   - User Type: **External** (or Internal if using Google Workspace)
   - App name: `YAFFA Receipt Importer`
   - User support email: Your email
   - Developer contact: Your email
   - Scopes: Add `gmail.readonly` and `gmail.modify`
   - Test users: Add your Gmail address
4. Create OAuth client ID:
   - Application type: **Desktop app**
   - Name: `YAFFA Desktop Client`
5. Download the credentials JSON or copy the **Client ID** and **Client Secret**

### 4. Configure YAFFA Environment

Edit your `.env` file and add:

```properties
# Gmail OAuth2 Configuration
GMAIL_CLIENT_ID=your-client-id.apps.googleusercontent.com
GMAIL_CLIENT_SECRET=your-client-secret
GMAIL_API_ENABLED=false  # Will enable after getting refresh token
GMAIL_USER_EMAIL=your-email@gmail.com

# Sender Whitelist (comma-separated)
GMAIL_SENDER_WHITELIST=receipts@amazon.com,orders@walmart.com,noreply@paypal.com
```

### 5. Get Refresh Token

Run the interactive setup command:

```bash
php artisan yaffa:setup-gmail-oauth
```

This will:
1. Generate an authorization URL
2. Ask you to open it in your browser
3. Prompt you to sign in with Google and authorize the app
4. Display the authorization code
5. Exchange the code for a refresh token
6. Show you the refresh token to add to `.env`

Copy the `GMAIL_REFRESH_TOKEN` value it displays and add it to your `.env` file:

```properties
GMAIL_REFRESH_TOKEN=your-refresh-token-here
GMAIL_API_ENABLED=true
```

### 6. Test the Integration

Fetch emails manually to test:

```bash
php artisan yaffa:fetch-gmail-emails
```

You should see output like:
```
Fetching unread emails from Gmail...
Successfully processed 3 email(s):
  - From: receipts@amazon.com | Subject: Your Amazon.com order #123-456
  - From: orders@walmart.com | Subject: Order confirmation
  - From: noreply@paypal.com | Subject: Receipt for your payment
```

### 7. Automate Email Fetching (Optional)

To automatically check for new emails every 5 minutes, add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Existing schedules...
    
    $schedule->command('yaffa:fetch-gmail-emails')
        ->everyFiveMinutes()
        ->withoutOverlapping();
}
```

Make sure your Laravel scheduler is running:
```bash
php artisan schedule:work
```

Or add to crontab:
```
* * * * * cd /path/to/yaffa && php artisan schedule:run >> /dev/null 2>&1
```

## Sender Whitelist

The `GMAIL_SENDER_WHITELIST` setting controls which senders' emails will be imported. This is important to avoid flooding your YAFFA inbox with all emails.

### Format
Comma-separated list of email addresses:
```properties
GMAIL_SENDER_WHITELIST=sender1@example.com,sender2@example.com,sender3@example.com
```

### Tips
- Add receipt and invoice senders you want to track
- Include utility bill senders
- Add subscription service billing emails
- Leave empty to allow ALL senders (not recommended)

### Example Whitelist
```properties
GMAIL_SENDER_WHITELIST=receipts@amazon.com,orders@walmart.com,noreply@paypal.com,auto-confirm@amazon.com,orders@ebay.com,receipts@etsy.com,noreply@uber.com,receipts@doordash.com,billing@netflix.com,receipts@spotify.com
```

## User Matching

Important: YAFFA will **only process emails from senders who have a user account** in YAFFA with that same email address.

For example:
- Gmail sender: `receipts@amazon.com`
- Must have YAFFA user with email: `receipts@amazon.com`
- If no matching user exists, the email is skipped

This prevents processing emails from unauthorized senders.

## Troubleshooting

### "No refresh token received"

This happens if you've already authorized the app before. Solution:
1. Go to https://myaccount.google.com/permissions
2. Find your YAFFA app and remove access
3. Run `php artisan yaffa:setup-gmail-oauth` again

### "Gmail API is not enabled"

Make sure you've enabled the Gmail API in Google Cloud Console for your project.

### "No user found for sender email"

Create a YAFFA user account with the same email address as the sender.

### "Skipping email from non-whitelisted sender"

Add the sender's email address to `GMAIL_SENDER_WHITELIST` in your `.env` file.

### SSL Certificate Errors

If you get SSL errors when running composer or artisan commands:
1. Update your CA certificates
2. Or temporarily disable Avast/antivirus SSL scanning

## Security Notes

- **Refresh Token**: Keep your `GMAIL_REFRESH_TOKEN` secure - it provides ongoing access to your Gmail
- **Whitelist**: Always use a sender whitelist to limit which emails are imported
- **OAuth Scopes**: Only grants read and modify (mark as read) permissions, not full account access
- **User Matching**: Built-in protection by only processing emails from registered users

## How It Works

1. Artisan command runs (manually or via scheduler)
2. Connects to Gmail API using OAuth2 refresh token
3. Searches for unread emails
4. Checks each sender against whitelist
5. Matches sender to YAFFA user account
6. Checks for duplicate (by message ID)
7. Creates `ReceivedMail` record in database
8. Fires `IncomingEmailReceived` event
9. AI processing job is queued (if OpenAI configured)
10. Marks email as read in Gmail
11. Transaction appears in YAFFA!

## Next Steps

After setting up Gmail integration:

1. Configure OpenAI API key for receipt parsing (if not already done)
2. Set up sender whitelist with your common receipt senders
3. Create YAFFA user accounts matching sender emails (if needed)
4. Schedule the fetch command to run automatically
5. Monitor the logs for any issues: `storage/logs/laravel.log`

## Support

For issues or questions about Gmail integration:
- Check the logs: `tail -f storage/logs/laravel.log`
- Run with verbose output: `php artisan yaffa:fetch-gmail-emails -v`
- Review the code: `app/Services/GmailImapService.php`
