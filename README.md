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
answers `false`) or `Models\MailForm`, which sends itself to `to` (default `params.formEmail`, then `params.email`; a
comma-separated list is fine) from `from` (`params.email`) with the visitor's `email` as reply-to. The mail view gets
the form as `$form`.

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
        return Yii::t('app', 'CONTACT_FORM_SUCCESS_TEXT');
    }
}
```

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

The provider comes from params:

- `Newsletter\Brevo`, from `params.brevoApiKey`, `params.brevoListId` (comma-separated for several) and
  `params.brevoTemplateId` (or one per language, `['de' => 8, 'en-US' => 9]`): Brevo's double opt-in. The confirmation
  link leads to `redirectUrl`, the home page unless the container says otherwise:
  `'container' => ['definitions' => [Brevo::class => ['redirectUrl' => ['/cms/site/view', 'slug' => 'subscribed']]]]`.
- `Newsletter\Webhook`, from `params.newsletterWebhookUrl`: posts `email`, `language`, `ip`, `url` (the page of the
  form) and the attributes as JSON to an automation (Zoho Flow, Make, Zapier, n8n), which runs the opt-in.

A project replaces the provider with a closure; another service implements `Newsletter\ProviderInterface`:

```php
'newsletter' => [
    'provider' => fn (): ProviderInterface => new Webhook(['url' => '…', 'headers' => ['Authorization' => '…']]),
],
```

## Bot protection

`botProtection` checks every submit after the form's own rules pass; every protection in its list must agree.
Without configuration the list is `Honeypot`, plus `Turnstile` when `params.cfTurnstileSiteKey` and
`params.cfTurnstileSecretKey` are set. The form renders the protections' markup with
`BotProtection::current()->render()`. A project replaces the list with a closure (an array would be appended to):

```php
'botProtection' => [
    'protections' => fn (): array => [new Honeypot(minSeconds: 5)],
],
```

- `BotProtection\Honeypot`: a field that must stay empty and a signed render time a submit must not follow within
  `minSeconds`. No third party.
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
