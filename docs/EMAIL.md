# Sending real email

LankaGuide360 sends these emails:

- trip received (to the traveller) and new trip request (to agents and `ADMIN_EMAIL`)
- every status change, with the trip plan PDF when a trip is approved
- messages between traveller and agent
- booking accepted (to the agent)
- the e-receipt, with the PDF attached
- contact form messages

Out of the box `MAIL_MAILER=log`, so emails are only written to `storage/logs/laravel.log`.
To really send them, pick one free SMTP service, put its details in `.env`, and test.

## Option A — Brevo (recommended, free 300 emails a day)

1. Create a free account at <https://www.brevo.com>.
2. **Senders, domains & dedicated IPs → Senders**: add and verify the address you send from
   (for example your Gmail address, or `bookings@yourdomain`).
3. **SMTP & API → SMTP**: copy the *SMTP login* and create an *SMTP key*.
4. In `.env`:

   ```env
   MAIL_MAILER=smtp
   MAIL_SCHEME=null
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=your-brevo-smtp-login@smtp-brevo.com
   MAIL_PASSWORD=your-brevo-smtp-key
   MAIL_FROM_ADDRESS="the-verified-sender@example.com"
   MAIL_FROM_NAME="LankaGuide360"
   ADMIN_EMAIL=you@example.com
   ```

## Option B — a Gmail account (free, about 500 emails a day)

1. Turn on 2-Step Verification for the Google account.
2. Create an **App Password** (Google Account → Security → App passwords). It is 16 letters.
3. In `.env`:

   ```env
   MAIL_MAILER=smtp
   MAIL_SCHEME=null
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=youraccount@gmail.com
   MAIL_PASSWORD=the16letterapppassword
   MAIL_FROM_ADDRESS="youraccount@gmail.com"
   MAIL_FROM_NAME="LankaGuide360"
   ```

Never commit `.env` — the password must stay on your computer and the server only.

## Test it

```sh
php artisan config:clear
php artisan lg:mail-test you@example.com
```

or **Admin → Settings → Email → Send test email**. Both send right away and explain common
errors (wrong password, blocked port, unverified sender).

## Make sure queued emails go out

Trip emails are queued so pages stay fast. Something must process the queue:

- **On your PC:** keep `php artisan queue:work` running.
- **On a server with a permanent process:** run `php artisan queue:work --tries=3` as a service.
- **On free/shared hosting (alwaysdata, InfinityFree…):** add a cron job that runs every minute:

  ```sh
  php /path/to/site/artisan schedule:run
  ```

  The scheduler (`routes/console.php`) then sends whatever is queued and stops.
