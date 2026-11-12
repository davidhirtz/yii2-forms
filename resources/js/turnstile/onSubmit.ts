import type {Challenge} from '../form.ts';
import {createWidget, type TurnstileOptions} from './turnstile.ts';

/**
 * Loads nothing until a valid submit, then runs Turnstile invisibly unless it needs the visitor.
 */
export default (options: TurnstileOptions = {}): Challenge => ($form) => createWidget($form, 'execute', options).challenge;
