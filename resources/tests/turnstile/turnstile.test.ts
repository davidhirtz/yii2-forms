import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import onFocus from '../../js/turnstile/onFocus.ts';
import onSubmit from '../../js/turnstile/onSubmit.ts';

describe('turnstile', () => {
    let $form: HTMLFormElement;
    let options: Record<string, unknown> | undefined;
    let api: {render: ReturnType<typeof vi.fn>; execute: ReturnType<typeof vi.fn>; reset: ReturnType<typeof vi.fn>};

    const issueToken = (token: string) => (options!.callback as (token: string) => void)(token);

    beforeEach(() => {
        document.body.innerHTML = '<form><input name="email"><div data-turnstile data-sitekey="site-key"></div></form>';
        $form = document.querySelector('form')!;
        options = undefined;

        api = {
            render: vi.fn((_container: HTMLElement, renderOptions: Record<string, unknown>) => {
                options = renderOptions;
                return 'widget';
            }),
            execute: vi.fn(),
            reset: vi.fn(),
        };

        vi.stubGlobal('turnstile', api);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('renders nothing before the submit, then executes invisibly', async () => {
        const challenge = onSubmit()($form);
        $form.querySelector('input')!.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));

        expect(api.render).not.toHaveBeenCalled();

        const fields = challenge.solve();
        await vi.waitFor(() => expect(api.execute).toHaveBeenCalledWith('widget'));

        expect(options).toMatchObject({sitekey: 'site-key', execution: 'execute', appearance: 'interaction-only', 'response-field': false});

        issueToken('token-1');
        await expect(fields).resolves.toEqual({'cf-turnstile-response': 'token-1'});

        challenge.reset();
        expect(api.reset).toHaveBeenCalledWith('widget');
    });

    it('renders on the first focus, so the token is ready by submit', async () => {
        const challenge = onFocus()($form);
        $form.querySelector('input')!.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));

        await vi.waitFor(() => expect(api.render).toHaveBeenCalledOnce());
        expect(options).toMatchObject({execution: 'render'});

        issueToken('token-2');

        await expect(challenge.solve()).resolves.toEqual({'cf-turnstile-response': 'token-2'});
        expect(api.execute).not.toHaveBeenCalled();
    });

    it('passes the project\'s render options without letting them replace its own', async () => {
        const challenge = onSubmit({language: 'de', theme: 'light', execution: 'render', sitekey: 'other'})($form);
        void challenge.solve();

        await vi.waitFor(() => expect(api.render).toHaveBeenCalled());
        expect(options).toMatchObject({language: 'de', theme: 'light', execution: 'execute', sitekey: 'site-key'});
    });

    it('rejects when Turnstile reports an error', async () => {
        const challenge = onSubmit()($form);
        const fields = challenge.solve();

        await vi.waitFor(() => expect(api.render).toHaveBeenCalled());
        (options!['error-callback'] as () => void)();

        await expect(fields).rejects.toThrow('Turnstile could not verify the visitor.');
    });

    it('solves to nothing for a form without a container', async () => {
        $form.querySelector('[data-turnstile]')!.remove();

        await expect(onSubmit()($form).solve()).resolves.toEqual({});
        expect(api.render).not.toHaveBeenCalled();
    });
});
