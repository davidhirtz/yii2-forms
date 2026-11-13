## Unreleased

- `BotProtection::getDefaultProtections()` and `Newsletter::getDefaultProvider()` replace the closures in `Bootstrap`
- Added `BotProtection\RateLimit`, in the default protections
- Added `Models\WebhookForm` and the generic `Webhooks\Webhook`, which `Newsletter\Webhook` extends
- Added `Honeypot::$maxSeconds`, extended by the page and fragment caches around the form
- `MailForm::$to`, `$from`, `$subject` and `$viewFile` are no longer attributes
- Mailchimp adds members instead of updating existing ones

## 3.1.0 (November 13, 2026)

- Mailchimp newsletter provider, wired from params; a list per language

## 3.0.0 (November 12, 2026)

- Initial release: mail forms, a newsletter form with Brevo and webhook providers, a JSON form controller, honeypot and Turnstile bot protection, and the TypeScript to submit them
