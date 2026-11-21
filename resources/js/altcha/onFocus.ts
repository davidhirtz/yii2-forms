import type {Challenge} from '../form.ts';
import {createSolver} from './altcha.ts';

/**
 * Starts solving on the form's first `focusin`, and again after every answer, so a solution is usually ready by submit.
 */
export default (): Challenge => ($form) => {
    const {load, challenge} = createSolver($form, true);

    $form.addEventListener('focusin', load, {once: true});

    return challenge;
};
