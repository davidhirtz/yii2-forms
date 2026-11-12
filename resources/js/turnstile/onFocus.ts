import type {Challenge} from '../form.ts';
import {createWidget} from './turnstile.ts';

/**
 * Loads and renders Turnstile when the visitor first focuses the form, so the token is usually ready by submit.
 */
export default (): Challenge => ($form) => {
    const {load, challenge} = createWidget($form, 'render');

    $form.addEventListener('focusin', () => void load(), {once: true});

    return challenge;
};
