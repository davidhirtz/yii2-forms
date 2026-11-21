import {solveChallenge, type Challenge} from 'altcha-lib';
import {deriveKey} from 'altcha-lib/algorithms/web/pbkdf2';
import type {Fields, FormChallenge} from '../form.ts';

const RESPONSE_PARAM = 'altcha';

/**
 * A solution this close to its challenge's expiry is solved again, so it cannot run out on the way to the server.
 */
const EXPIRY_MARGIN = 30;

/**
 * The solver for a form's `[data-altcha]` container, which names the challenge URL in `data-url`. WebCrypto derives
 * the keys off the main thread, so no worker is needed.
 */
export const createSolver = ($form: HTMLFormElement, isEager: boolean) => {
    const $container = $form.querySelector<HTMLElement>('[data-altcha]');

    let pending: Promise<Fields> | undefined;
    let expiresAt: number | undefined;

    const fetchChallenge = (): Promise<Challenge> => fetch($container!.dataset.url!, {
        method: 'post',
        credentials: 'same-origin',
        headers: {Accept: 'application/json'},
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`Altcha challenge failed with status ${response.status}.`);
        }

        return response.json() as Promise<Challenge>;
    });

    const start = (): Promise<Fields> => {
        expiresAt = undefined;

        const promise = fetchChallenge()
            .then((challenge) => {
                expiresAt = challenge.parameters.expiresAt;
                return Promise.all([challenge, solveChallenge({challenge, deriveKey})]);
            })
            .then(([challenge, solution]) => {
                if (!solution) {
                    throw new Error('Altcha could not solve the challenge.');
                }

                return {[RESPONSE_PARAM]: btoa(JSON.stringify({challenge, solution}))};
            });

        promise.catch(() => {
            if (pending === promise) {
                pending = undefined;
            }
        });

        return pending = promise;
    };

    const isExpiring = () => expiresAt !== undefined && expiresAt - EXPIRY_MARGIN < Date.now() / 1000;

    const challenge: FormChallenge = {
        solve: () => {
            if (!$container) {
                return Promise.resolve({});
            }

            return !pending || isExpiring() ? start() : pending;
        },

        reset: () => {
            pending = undefined;

            if (isEager && $container) {
                start().catch(() => undefined);
            }
        },
    };

    const load = () => {
        if ($container && !pending) {
            start().catch(() => undefined);
        }
    };

    return {load, challenge};
};
