import type {Challenge} from '../form.ts';
import {createWidget, type TurnstileOptions} from './turnstile.ts';

/**
 * Loads and renders Turnstile when the visitor first focuses the form, so the token is usually ready by submit.
 */
export default (options: TurnstileOptions = {}): Challenge => ($form) => {
    const {load, challenge} = createWidget($form, 'render', options);

    $form.addEventListener('focusin', () => void load(), {once: true});

    return challenge;
};
