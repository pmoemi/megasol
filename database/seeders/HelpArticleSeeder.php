<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $app = config('app.name', 'MegaSol');

        $articles = [
            // ── Getting Started ───────────────────────────────────────
            [
                'slug' => 'welcome-to-megasol',
                'title' => 'Welcome to '.$app,
                'category' => 'getting-started',
                'sort_order' => 1,
                'excerpt' => 'A quick tour of the dashboard and the main features available to you.',
                'content' => "Welcome to {$app}! This platform helps you manage your customers and reach them over SMS and email through campaigns, templates, and automated workflows.\n\nThe sidebar on the left is your main navigation. Start with Customers to build your audience, then create reusable Templates, and finally launch a Campaign to message them. The Dashboard gives you an at-a-glance view of recent activity and key numbers.\n\nIf you are an administrator, head to Settings to configure your SMS gateway, email (SMTP) server, branding colors, and default timezone before sending anything.",
                'related_feature' => 'dashboard',
            ],
            [
                'slug' => 'configuring-settings-first',
                'title' => 'Configure Your Settings First',
                'category' => 'getting-started',
                'sort_order' => 2,
                'excerpt' => 'Set up your SMS gateway, email server, timezone, and branding before sending.',
                'content' => "Before launching campaigns, configure your account under Settings.\n\nUnder Settings > General, set your application name, default timezone, and currency. The timezone is used for scheduling and timestamps across the app.\n\nUnder Settings > SMS Gateway, confirm your Africa's Talking credentials are in place. Under Settings > Email / SMTP, enter your outgoing mail server details and send a test email to verify delivery.\n\nUnder Settings > Branding and Theme Studio you can customize the brand colors used across the interface.",
                'related_feature' => 'settings',
            ],

            // ── Customers ─────────────────────────────────────────────
            [
                'slug' => 'adding-and-importing-customers',
                'title' => 'Adding and Importing Customers',
                'category' => 'customers',
                'sort_order' => 10,
                'excerpt' => 'Create customers one by one or bulk-import them from a spreadsheet.',
                'content' => "You can add customers individually from Customers > Add New Customer, filling in their name, phone number, email, and account details.\n\nTo add many at once, use Customers > Import. Upload a CSV file and map its columns to the customer fields. Make sure phone numbers are in international format (for example +2547XXXXXXXX) so SMS delivery works reliably, and that email addresses are valid for email campaigns.\n\nAfter importing, review the results summary to see how many customers were created and whether any rows failed validation.",
                'related_feature' => 'customers',
            ],
            [
                'slug' => 'customer-groups-and-segments',
                'title' => 'Customer Groups and Segments',
                'category' => 'customers',
                'sort_order' => 11,
                'excerpt' => 'Organize customers into groups and dynamic segments for targeted messaging.',
                'content' => "There are two ways to target a subset of customers.\n\nGroups (Customers > Groups) are manual lists — you add specific customers to a named group, then send a campaign to that group.\n\nSegments are dynamic: they use rules (such as payment status or lifecycle stage) to automatically include any customer who matches. When you send a campaign to a segment, the audience is recalculated at send time, so it always reflects your current data.\n\nWhen building a campaign you can also pick \"Specific Customers\" to hand-select recipients, or filter by payment status / lifecycle stage directly.",
                'related_feature' => 'customers',
            ],

            // ── Campaigns ─────────────────────────────────────────────
            [
                'slug' => 'creating-your-first-campaign',
                'title' => 'Creating Your First Campaign',
                'category' => 'campaigns',
                'sort_order' => 20,
                'excerpt' => 'Walk through the four steps to build and send an SMS or email campaign.',
                'content' => "Creating a campaign takes four steps.\n\n1. Basics — name your campaign and choose the channel (SMS or Email). For email you can also choose a Regular send or an A/B test.\n\n2. Content — write your SMS message, or design your email with the visual builder or by selecting an email template. You can send yourself a test email before continuing.\n\n3. Audience — choose who receives it: all customers, a group, a segment, specific customers, or a filter by payment status / lifecycle stage. The recipient count updates live.\n\n4. Schedule — send immediately, or pick a date and time. You can also control the send rate and batching to pace delivery.\n\nDraft campaigns can be edited any time; sent campaigns show a full report.",
                'related_feature' => 'campaigns',
            ],
            [
                'slug' => 'ab-testing-campaigns',
                'title' => 'A/B Testing Email Campaigns',
                'category' => 'campaigns',
                'sort_order' => 21,
                'excerpt' => 'Test two subject lines against each other to find the better performer.',
                'content' => "A/B testing lets you compare two subject lines on a single email campaign.\n\nIn Step 1 of the campaign editor, choose the Email channel and set the type to \"A/B Test\". You will then be asked for Subject A and Subject B, and a split percentage that decides how much of your audience receives each variant.\n\nWhen the campaign sends, recipients are divided according to your split and each group receives its assigned subject line. The campaign report then shows opens and clicks broken down by variant, so you can see which subject performed best.",
                'related_feature' => 'campaigns',
            ],
            [
                'slug' => 'understanding-campaign-reports',
                'title' => 'Understanding Campaign Reports',
                'category' => 'campaigns',
                'sort_order' => 22,
                'excerpt' => 'Read delivery, open, and click metrics for a sent campaign.',
                'content' => "Open any sent campaign and choose \"View Report\" to see how it performed.\n\nThe top cards show recipients, delivered, and (for email) opens and clicks with their rates. For email campaigns, opens are tracked with an invisible pixel and clicks are tracked by rewriting links — so both require the recipient's mail client to load images or click through.\n\nThe tabs let you drill into the recipient list (filterable by status), the people who opened, the people who clicked, and the most-clicked links. A/B campaigns also show a per-variant breakdown.",
                'related_feature' => 'campaigns',
            ],

            // ── SMS ───────────────────────────────────────────────────
            [
                'slug' => 'sms-segments-and-length',
                'title' => 'SMS Length and Segments',
                'category' => 'sms',
                'sort_order' => 30,
                'excerpt' => 'How character count affects the number of SMS segments (and cost).',
                'content' => "A single SMS fits 160 characters using the standard GSM alphabet. Longer messages are split into multiple segments and billed per segment, so keeping messages concise saves money.\n\nUsing special characters or emoji switches the message to Unicode encoding, which reduces the per-segment limit to 70 characters. The template preview and campaign editor show a live segment estimate as you type.\n\nMerge tags like {first_name} or {balance} are replaced per recipient at send time — remember that longer replacement values can push a message into an extra segment.",
                'related_feature' => 'sms',
            ],

            // ── Email ─────────────────────────────────────────────────
            [
                'slug' => 'setting-up-smtp',
                'title' => 'Setting Up Your Email (SMTP) Server',
                'category' => 'email',
                'sort_order' => 40,
                'excerpt' => 'Connect an outgoing mail server so email campaigns can be delivered.',
                'content' => "Go to Settings > Email / SMTP to configure outgoing email.\n\nEnter your mail host, port, username, password, and encryption (TLS for port 587, SSL for port 465). Set the default From address and From name that recipients will see.\n\nUse the \"Send Test\" button to confirm the settings work before sending a real campaign. Each campaign can override the From name and address under its Content step.\n\nFor best deliverability, use a sending domain you control and make sure its SPF, DKIM, and DMARC DNS records are configured with your provider.",
                'related_feature' => 'email',
            ],
            [
                'slug' => 'using-the-email-builder',
                'title' => 'Designing Emails with the Builder',
                'category' => 'email',
                'sort_order' => 41,
                'excerpt' => 'Use drag-and-drop blocks to build responsive emails without code.',
                'content' => "The email builder lets you assemble an email from content blocks — headers, text, images, buttons, columns, dividers, spacers, social links, and footers.\n\nDrag blocks from the palette into the canvas, select a block to edit its properties on the right, and reorder or duplicate blocks as needed. The builder compiles everything into responsive, email-client-friendly HTML.\n\nYou can save your design as a reusable Email Template, browse the template gallery for a starting point, or send the design straight into a campaign with \"Use in campaign\".",
                'related_feature' => 'email-templates',
            ],

            // ── Templates ─────────────────────────────────────────────
            [
                'slug' => 'reusable-templates',
                'title' => 'Working with Templates',
                'category' => 'templates',
                'sort_order' => 50,
                'excerpt' => 'Save SMS and email templates and reuse them in campaigns.',
                'content' => "Templates save you from rewriting the same message twice.\n\nSMS Templates store short message bodies; Email Templates store a subject and a designed HTML body. Both support merge tags such as {first_name}, {account_number}, and {balance}.\n\nFrom either templates page you can Preview a template in a modal and click \"Use in campaign\" to start a new campaign pre-filled with that template's content. Mark templates active or inactive to control whether they appear when building a campaign.",
                'related_feature' => 'templates',
            ],

            // ── Workflows ─────────────────────────────────────────────
            [
                'slug' => 'automating-with-workflows',
                'title' => 'Automating with Workflows',
                'category' => 'workflows',
                'sort_order' => 60,
                'excerpt' => 'Trigger messages automatically based on events like a new customer.',
                'content' => "Workflows let you send messages automatically without launching a campaign each time.\n\nA workflow has a trigger (for example, when a customer is created or a payment becomes overdue) and a sequence of steps — such as sending an SMS, waiting a delay, then sending a follow-up email.\n\nBuild a workflow from Workflows > Create, define the trigger and steps, and activate it. Active workflows run on their own whenever their trigger fires.",
                'related_feature' => 'workflows',
            ],

            // ── Billing & PayGro ──────────────────────────────────────
            [
                'slug' => 'paygro-integration',
                'title' => 'PayGro Integration & Customer Sync',
                'category' => 'billing',
                'sort_order' => 70,
                'excerpt' => 'Connect PayGro to sync customers and payment data into the platform.',
                'content' => "If you use PayGro, connect it under Settings > PayGro to keep your customer and payment data in sync.\n\nEnter your PayGro base URL, distributor ID, username, and password, then use Connect to verify the credentials. Once connected, run a sync over a date range to import customers and their payment status.\n\nSynced payment status and balances power audience filters (such as \"overdue\" customers) and merge tags like {balance} and {next_payment_date} in your messages.",
                'related_feature' => 'paygro',
            ],

            // ── Troubleshooting ───────────────────────────────────────
            [
                'slug' => 'messages-not-sending',
                'title' => 'My Messages Are Not Sending',
                'category' => 'troubleshooting',
                'sort_order' => 80,
                'excerpt' => 'Common reasons SMS or email fail to send, and how to fix them.',
                'content' => "If a campaign is not delivering, check the following.\n\nFor SMS: confirm your Africa's Talking credentials under Settings > SMS Gateway, and that customers have valid phone numbers in international format. Recipients without a phone number are skipped.\n\nFor email: confirm your SMTP settings under Settings > Email and use \"Send Test\" to verify. Recipients without a valid email address are skipped, and the From address may need to be on a verified domain.\n\nOpen the campaign's report and check the recipient list — failed recipients show an error message explaining why. The Activity log and queued jobs also help diagnose delivery issues.",
                'related_feature' => 'troubleshooting',
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::updateOrCreate(['slug' => $article['slug']], $article);
        }
    }
}
