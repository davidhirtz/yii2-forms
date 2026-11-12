import type {Challenge} from '../form.ts';
import {createWidget} from './turnstile.ts';

/**
 * Loads nothing until a valid submit, then runs Turnstile invisibly unless it needs the visitor.
 */
export default (): Challenge => ($form) => createWidget($form, 'execute').challenge;
