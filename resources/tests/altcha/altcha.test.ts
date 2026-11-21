import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {createChallenge, verifySolution} from 'altcha-lib';
import {deriveKey} from 'altcha-lib/algorithms/web/pbkdf2';
import onFocus from '../../js/altcha/onFocus.ts';
import onSubmit from '../../js/altcha/onSubmit.ts';

const SECRET = 'hmac-key';

describe('altcha', () => {
    let $form: HTMLFormElement;
    let fetchMock: ReturnType<typeof vi.fn>;
    let expiresAt: number;

    const verify = async (fields: Record<string, string>) => {
        const {challenge, solution} = JSON.parse(atob(fields.altcha!));
        return (await verifySolution({challenge, solution, deriveKey, hmacSignatureSecret: SECRET})).verified;
    };

    beforeEach(() => {
        document.body.innerHTML = '<form><input name="email"><div data-altcha data-url="/form/altcha"></div></form>';
        $form = document.querySelector('form')!;
        expiresAt = Math.floor(Date.now() / 1000) + 600;

        fetchMock = vi.fn(async () => Response.json(await createChallenge({
            algorithm: 'PBKDF2/SHA-256',
            cost: 1,
            counter: 5,
            deriveKey,
            expiresAt,
            hmacSignatureSecret: SECRET,
        })));

        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('fetches nothing before the submit, then posts a solution', async () => {
        const challenge = onSubmit()($form);
        $form.querySelector('input')!.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));

        expect(fetchMock).not.toHaveBeenCalled();

        const fields = await challenge.solve();
        expect(fetchMock).toHaveBeenCalledWith('/form/altcha', expect.objectContaining({method: 'post'}));
        expect(await verify(fields)).toBe(true);

        challenge.reset();
        expect(fetchMock).toHaveBeenCalledOnce();

        await challenge.solve();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('solves on the first focus and again after every answer', async () => {
        const challenge = onFocus()($form);
        $form.querySelector('input')!.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));
        $form.querySelector('input')!.dispatchEvent(new FocusEvent('focusin', {bubbles: true}));

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(await verify(await challenge.solve())).toBe(true);
        expect(fetchMock).toHaveBeenCalledOnce();

        challenge.reset();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('solves again when the solution is about to expire', async () => {
        expiresAt = Math.floor(Date.now() / 1000) + 10;
        const challenge = onFocus()($form);
        $form.dispatchEvent(new FocusEvent('focusin'));

        await challenge.solve();
        await challenge.solve();
        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('rejects a failed challenge request and retries on the next submit', async () => {
        fetchMock.mockResolvedValueOnce(new Response('', {status: 404}));
        const challenge = onSubmit()($form);

        await expect(challenge.solve()).rejects.toThrow('404');
        expect(await verify(await challenge.solve())).toBe(true);
    });

    it('adds nothing without a container', async () => {
        document.body.innerHTML = '<form></form>';
        const challenge = onFocus()(document.querySelector('form')!);

        await expect(challenge.solve()).resolves.toEqual({});
        challenge.reset();
        expect(fetchMock).not.toHaveBeenCalled();
    });
});
