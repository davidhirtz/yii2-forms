import type {Challenge} from '../form.ts';
import {createSolver} from './altcha.ts';

/**
 * Fetches and solves a challenge on a valid submit.
 */
export default (): Challenge => ($form) => createSolver($form, false).challenge;
