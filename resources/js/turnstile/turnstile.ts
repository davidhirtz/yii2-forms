import loadScript from '../loadScript.ts';
import type {Fields, FormChallenge} from '../form.ts';

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
const RESPONSE_PARAM = 'cf-turnstile-response';

/**
 * The part of Cloudflare's API used here, so a project needs no `@types/cloudflare-turnstile`.
 */
interface TurnstileApi {
    render(container: HTMLElement, options: Record<string, unknown>): string | null | undefined;
    execute(widgetId: string): void;
    reset(widgetId: string): void;
}

const getTurnstile = (): TurnstileApi => (window as unknown as {turnstile: TurnstileApi}).turnstile;

/**
 * Render options a project may set, such as `language`, `theme` or `size`.
 */
export type TurnstileOptions = Record<string, unknown>;

/**
 * The widget a form's `[data-turnstile]` container holds, rendered on the first `load()`.
 */
export const createWidget = ($form: HTMLFormElement, execution: 'render' | 'execute', options: TurnstileOptions = {}) => {
    const $container = $form.querySelector<HTMLElement>('[data-turnstile]');

    let widgetId: string | null | undefined;
    let token: string | undefined;
    let waiting: {resolve: (fields: Fields) => void; reject: (error: Error) => void} | undefined;

    const settle = (value: string | Error) => {
        if (typeof value === 'string') {
            token = value;
            waiting?.resolve({[RESPONSE_PARAM]: value});
        } else {
            waiting?.reject(value);
        }

        waiting = undefined;
    };

    const load = (): Promise<void> => {
        if (!$container || widgetId !== undefined) {
            return Promise.resolve();
        }

        return ('turnstile' in window ? Promise.resolve() : loadScript(SCRIPT_URL)).then(() => {
            widgetId ??= getTurnstile().render($container, {
                appearance: execution === 'execute' ? 'interaction-only' : 'always',
                ...options,
                sitekey: $container.dataset.sitekey!,
                execution,
                'response-field': false,
                callback: (value: string) => settle(value),
                'error-callback': () => settle(new Error('Turnstile could not verify the visitor.')),
                'expired-callback': () => {
                    token = undefined;
                },
            });
        });
    };

    const challenge: FormChallenge = {
        solve: () => {
            if (!$container) {
                return Promise.resolve({});
            }

            if (token) {
                return Promise.resolve({[RESPONSE_PARAM]: token});
            }

            return load().then(() => new Promise<Fields>((resolve, reject) => {
                waiting = {resolve, reject};

                if (execution === 'execute' && widgetId) {
                    getTurnstile().execute(widgetId);
                }
            }));
        },

        reset: () => {
            token = undefined;

            if (widgetId) {
                getTurnstile().reset(widgetId);
            }
        },
    };

    return {load, challenge};
};
