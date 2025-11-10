# 💳 Paystack for JetFormBuilder — v1.3.0

[![WordPress Tested](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://www.php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)
[![Paystack](https://img.shields.io/badge/Paystack-API-success.svg)](https://paystack.com/docs/)
[![Author](https://img.shields.io/badge/Author-Tobi%20John-lightgrey.svg)](https://tobijohn.com)

**Paystack for JetFormBuilder** connects your JetFormBuilder forms with **Paystack** for secure, verified, and fully automated payment processing.  
It supports both **Test** and **Live** modes, **callback verification**, **Paystack webhooks**, and an optional **reconciliation cron job** to ensure no transaction is missed.

---

## ⚙️ Key Features

✅ **Server-side Paystack integration** – initialize payments securely using your secret key (never exposed to the browser).  
✅ **Webhook verification** – verifies all successful payments directly from Paystack.  
✅ **Callback fallback** – ensures your customer still sees a success message even if the webhook is delayed.  
✅ **Reconciliation cron** – re-verifies pending transactions automatically every 3 hours.  
✅ **Customizable email notifications** – send HTML emails to customers after successful payments.  
✅ **Optional JetEngine CCT updates** – update your database table when payments are confirmed.  
✅ **Detailed logging system** – view up to 200 recent events from Paystack (webhooks, verifications, cron runs).  
✅ **Supports both Test and Live modes.**

---

## 🧩 Requirements

- WordPress 6.0+
- JetFormBuilder
- JetEngine (optional, if using the CCT update feature)
- Paystack account (Test or Live)

---

## 🧰 Quick Installation

### 🪄 Option 1 — From GitHub
```bash
git clone https://github.com/tobijohn/paystack-jfb.git wp-content/plugins/paystack-jfb

Then activate it from Plugins → Installed Plugins.

🪄 Option 2 — Manual Upload

Download paystack-jfb-v1.3.0-webhook-cron.zip

Go to Plugins → Add New → Upload Plugin

Upload the ZIP and click Activate

🔧 Setup Instructions
1️⃣ Configure Plugin Settings

Go to Settings → Paystack for JFB and set up:

Your Paystack Secret Keys (Test & Live)

Your Mode (Test or Live)

Select a Callback Page (with [paystack_callback] shortcode)

(Optional) Enable:

✅ Webhook Endpoint

✅ Reconciliation Cron

✅ Database Updates

✅ Email Notifications

✅ Log Events

2️⃣ Add Paystack Webhook URL

In your Paystack account, go to:

Developers → Webhooks → 
https://yourdomain.com/wp-json/paystack-jfb/v1/webhook

(Ensure your site is publicly accessible — Paystack will POST here.)
3️⃣ Create a Payment Page
Create a new WordPress page and add this shortcode:
[paystack_init]

This page automatically redirects users to Paystack for payment.
4️⃣ Set JetFormBuilder Redirect
After form submission, redirect users to:
https://yourdomain.com/pay/?email={email}&amount={amount}&inserted_cct_ticket_order={inserted_id}&customer_first_name={first_name}

These form field values will dynamically populate the URL.

💡 How It Works


User submits JetFormBuilder form → redirected to Paystack via [paystack_init].


Paystack processes payment → sends a charge.success webhook event to your site.


Plugin verifies signature → updates the database (optional) and sends the confirmation email.


User is redirected to [paystack_callback] page for confirmation.


Every 3 hours, a cron job runs to reconcile any pending or missed transactions.



🔍 Logging
All webhook, callback, and reconciliation events are logged in the database table:
wp_paystack_jfb_logs

You can view the last 200 events under Settings → Paystack for JFB.

🔐 Security


Webhook requests verified via Paystack’s HMAC-SHA512 signature.


Secret keys are masked in settings and never exposed in plain text.


Only administrators can access plugin settings.


All Paystack requests are made securely via HTTPS.


Webhook and callback endpoints are nonce-free but signature protected.



🧩 Developer Notes
ComponentPath / HookDescriptionWebhook Endpoint/wp-json/paystack-jfb/v1/webhookPrimary source of truth for payment confirmationLogs Tablewp_paystack_jfb_logsStores webhook and cron eventsCron Hookpaystack_jfb_reconcileRe-verifies pending payments automaticallyDefault Cron IntervalEvery 3 HoursConfigurable in plugin settingsShortcodes[paystack_init], [paystack_callback]Frontend payment and callback handling
All code follows WordPress Security and Coding Standards.

🧑‍💻 Author
Developed by: Tobi John
📧 Email: connect@tobijohn.com
🌐 Website: https://tobijohn.com

🏷️ License
Distributed under the GPLv2 or later license.

🕓 Changelog
v1.3.0 — November 2025


Added Webhook verification as the primary proof of payment


Added Reconciliation Cron (every 3 hours)


Added Event Logs table with admin viewer


Enhanced callback UX and email notifications


General security & stability improvements



💡 Paystack for JetFormBuilder helps you collect payments the right way — verified, reliable, and automated.

