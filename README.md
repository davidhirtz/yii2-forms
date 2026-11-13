# yii2-forms

Frontend forms for the [Yii 2](https://www.yiiframework.com/) platform
[yii2-skeleton](https://github.com/davidhirtz/yii2-skeleton): a controller answering JSON, mail forms, pluggable bot
protection and the TypeScript that submits them. Fields, mail views and markup stay in the project.

## Installation

```bash
composer require davidhirtz/yii2-forms
```

The bundle bootstraps itself (`Hirtz\Forms\Bootstrap`): the `botProtection` and `newsletter` components, the `forms`
message category and the bot protection's consent service in the required category.

## Forms

A form extends `Models\Form` (`submit()`, `getSuccessMessage()`; `terms` must be ticked unless `requiresTerms()`
answers `false`) or `Models\MailForm`, which sends `viewFile` with `subject` to `to` (default `params.formEmail`,
then `params.email`; a comma-separated list is fine) from `from` (`params.email`) with the visitor's `email`, when
valid, as reply-to. These four are no attributes, so a post never sets them, and no form field may take their names. The mail
view gets the form as `$form` and encodes what it prints.

The success message is HTML (`form.ts` shows it as such): encode what the visitor entered.

```php
class ContactForm extends MailForm
{
    public ?string $name = null;
    public ?string $email = null;

    public function init(): void
    {
        $this->subject = Yii::t('app', 'CONTACT_FORM_SUBJECT');
        $this->viewFile = '@app/mail/contact';
        parent::init();
    }

    public function rules(): array
    {
        return [...parent::rules(), [['name', 'email'], 'required'], [['email'], 'email']];
    }

    public function getSuccessMessage(): string
    {
        return Yii::t('app', 'CONTACT_FORM_SUCCESS_TEXT', ['name' => Html::encode($this->name)]);
    }
}
```

`Models\WebhookForm` posts its attributes as JSON, with `language`, `ip` and `url` (the page of the form), to
`webhookUrl` (default `params.formWebhookUrl`), an automation such as Zoho Flow, Make, Zapier or n8n. A subclass
renames or adds fields in `getWebhookData()`, or sends headers by overriding `getWebhook()`. Code of its own posts
through `Webhooks\Webhook` (`post()`, throwing `WebhookException`).

`Controllers\FormController` gets one action per form, POST only, behind the skeleton's `SameOriginFilter` instead of
a CSRF token, so the page holding the form can be cached:

```php
'controllerMap' => [
    'form' => [
        'class' => FormController::class,
        'forms' => ['contact' => ContactForm::class],
    ],
],
```

It answers `{status: 200, message}`, or `{status: 422, message, errors}` with status 422. A project controller can
use `Actions\SubmitAction` itself, with `SameOriginFilter` in its behaviors.

## Newsletter

`Models\NewsletterForm` subscribes its `email` through the `newsletter` component's provider. An address already on
the list answers like a new one, so the form never reveals who is subscribed; without a provider the form answers that
the newsletter is unavailable. A subclass adds fields and hands them to the provider through
`getSubscriptionAttributes()`.

```php
'forms' => ['contact' => ContactForm::class, 'newsletter' => NewsletterForm::class],
```

The provider comes from params, in this order:

- `Newsletter\Brevo`, from `params.brevoApiKey`, `params.brevoListId` (comma-separated for several) and
  `params.brevoTemplateId` (or one per language, `['de' => 8, 'en-US' => 9]`): Brevo's double opt-in. The confirmation
  link leads to `redirectUrl`, the home page unless the container says otherwise:
  `'container' => ['definitions' => [Brevo::class => ['redirectUrl' => ['/cms/site/view', 'slug' => 'subscribed']]]]`.
- `Newsletter\Mailchimp`, from `params.mailchimpApiKey` and `params.mailchimpListId` (or one per language,
  `params.mailchimpListId_de`, `params.mailchimpListId_en`): the member is `pending` until it confirms Mailchimp's double opt-in, unless the
  container sets `doubleOptIn` to `false`. The data center comes from the key's suffix or `params.mailchimpServer`. The
  form's attributes become merge fields (`FNAME`) of a new member; an existing one is left as it is.
- `Newsletter\Webhook`, from `params.newsletterWebhookUrl`: posts `email`, `language`, `ip`, `url` (the page of the
  form) and the attributes as JSON to an automation (Zoho Flow, Make, Zapier, n8n), which runs the opt-in.

A project replaces the provider (`false` turns the newsletter off); another service implements
`Newsletter\ProviderInterface`:

```php
'newsletter' => [
    'provider' => fn (): ProviderInterface => new Webhook(['url' => '…', 'headers' => ['Authorization' => '…']]),
],
```

## Bot protection

`botProtection` checks every submit after the form's own rules pass; every protection in its list must agree.
Without configuration the list is `Honeypot` and `RateLimit`, plus `Turnstile` when `params.cfTurnstileSiteKey` and
`params.cfTurnstileSecretKey` are set. The form renders the protections' markup with
`BotProtection::current()->render()`. A project replaces the list, or overrides `getDefaultProtections()`:

```php
'botProtection' => [
    'protections' => [new Honeypot(minSeconds: 5), new RateLimit(limit: 5)],
],
```

- `BotProtection\Honeypot`: a field that must stay empty (`website`, so no form may have a field of that name) and a
  signed render time a submit must not follow within `minSeconds`, nor after `maxSeconds` (two days) plus the
  duration of every page or fragment cache around the form (none when one never expires; `null` for no limit). A
  CDN or HTTP cache in front is not seen: raise `maxSeconds` by its lifetime. No third party.
- `BotProtection\RateLimit`: `limit` submits per IP address (an IPv6 /64) within `duration` seconds, across all forms,
  counted in the `cache` component.
- `BotProtection\Turnstile`: Cloudflare Turnstile, failing closed when Cloudflare cannot be reached. Registers its
  consent service.

Another mechanism implements `BotProtection\ProtectionInterface`.

## TypeScript

`resources/js` is imported from the package in the project's build (a Vite alias to
`vendor/davidhirtz/yii2-forms/resources/js`, say):

```ts
import form from '@forms/form.ts';
import turnstile from '@forms/turnstile/onSubmit.ts';

form(document, {challenge: turnstile()});
```

`form.ts` submits every `form[data-action]` and writes the answer into its `[data-message]` element (the success
message as HTML, errors as text), sets `aria-invalid` on the fields named in the errors and `data-state` (`busy`,
`success`, `error`) on the form; `onSuccess` and `onError` replace the output. A failed request shows
`data-error-message`. A challenge solves before the post and resets after the answer:

- `turnstile/onSubmit.ts`: loads nothing until a valid submit, then runs Turnstile invisibly (it shows itself only
  when it needs the visitor).
- `turnstile/onFocus.ts`: loads and renders Turnstile on the form's first `focusin`.

Both find the widget's container as `[data-turnstile]` inside the form and take Turnstile's render options
(`turnstile({language: 'de', theme: 'light'})`), except the ones they set themselves.
