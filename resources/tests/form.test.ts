import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import form, {type FormChallenge} from '../js/form.ts';

describe('form', () => {
    let $form: HTMLFormElement;
    let fetchMock: ReturnType<typeof vi.fn>;

    const respond = (data: object) => fetchMock.mockResolvedValueOnce({json: () => Promise.resolve(data)});

    const submit = async () => {
        $form.dispatchEvent(new Event('submit', {cancelable: true}));
        await vi.waitFor(() => expect($form.inert).toBe(false));
    };

    beforeEach(() => {
        document.body.innerHTML = `
            <form data-action="/form/contact">
                <div data-message hidden></div>
                <input name="email" value="åsa@example.com">
                <input type="checkbox" name="terms" value="1">
            </form>`;

        $form = document.querySelector('form')!;
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('posts the form and shows the success message as HTML', async () => {
        form(document);
        respond({status: 200, message: '<strong>Danke 👋</strong>'});

        await submit();

        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/form/contact');
        expect((init.body as FormData).get('email')).toBe('åsa@example.com');
        expect($form.dataset.state).toBe('success');
        expect($form.querySelector('[data-message]')!.innerHTML).toBe('<strong>Danke 👋</strong>');
    });

    it('marks invalid fields and lists the errors as text', async () => {
        form(document);
        respond({status: 422, message: 'Check', errors: {terms: ['<b>Accept</b>'], '': ['Spam check failed.']}});

        await submit();

        const $message = $form.querySelector<HTMLElement>('[data-message]')!;
        expect($form.dataset.state).toBe('error');
        expect($message.hidden).toBe(false);
        expect($message.textContent).toBe('<b>Accept</b>Spam check failed.');
        expect($form.querySelector('[name="terms"]')!.getAttribute('aria-invalid')).toBe('true');
        expect($form.querySelector('[name="email"]')!.hasAttribute('aria-invalid')).toBe(false);
    });

    it('adds the challenge fields and resets the challenge after the answer', async () => {
        const challenge: FormChallenge = {
            solve: vi.fn(() => Promise.resolve({'cf-turnstile-response': 'token'})),
            reset: vi.fn(),
        };

        form(document, {challenge: () => challenge});
        respond({status: 200, message: 'Sent'});

        await submit();

        expect((fetchMock.mock.calls[0][1].body as FormData).get('cf-turnstile-response')).toBe('token');
        expect(challenge.reset).toHaveBeenCalledOnce();
    });

    it('does not post when the challenge fails', async () => {
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        $form.dataset.errorMessage = 'Please try again.';

        form(document, {
            challenge: () => ({solve: () => Promise.reject(new Error('No token')), reset: vi.fn()}),
        });

        await submit();

        expect(fetchMock).not.toHaveBeenCalled();
        expect($form.dataset.state).toBe('error');
        expect($form.querySelector('[data-message]')!.textContent).toBe('Please try again.');
    });
});
