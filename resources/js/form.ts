/**
 * Submits a `form[data-action]` with `fetch()` and shows the answer of the bundle's `SubmitAction`.
 */
export type Fields = Record<string, string>;

export interface FormChallenge {
    /** Resolves to the fields the server checks, rejects to cancel the submit. */
    solve(): Promise<Fields>;

    /** A token is single-use: called after every answer. */
    reset(): void;
}

export type Challenge = ($form: HTMLFormElement) => FormChallenge;

export interface Answer {
    status: number;
    message: string;
    errors?: Record<string, string[]>;
}

export interface Options {
    challenge?: Challenge;
    onSuccess?: ($form: HTMLFormElement, answer: Answer) => void;
    onError?: ($form: HTMLFormElement, answer: Answer) => void;
}

const getMessage = ($form: HTMLFormElement): HTMLElement | null => $form.querySelector<HTMLElement>('[data-message]');

/**
 * The message the server authored goes in as HTML; field errors as text.
 */
const showSuccess = ($form: HTMLFormElement, answer: Answer) => {
    const $message = getMessage($form);

    if ($message) {
        $message.innerHTML = answer.message;
        $message.hidden = false;
    }
};

const showError = ($form: HTMLFormElement, answer: Answer) => {
    const $message = getMessage($form);

    if ($message) {
        const errors = Object.values(answer.errors ?? {}).flat();

        $message.replaceChildren(...(errors.length ? errors : [answer.message]).map((error) => {
            const $p = document.createElement('p');
            $p.textContent = error;
            return $p;
        }));

        $message.hidden = false;
        $message.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }
};

export default ($container: ParentNode, options: Options = {}) => {
    $container.querySelectorAll<HTMLFormElement>('form[data-action]').forEach(($form) => {
        const challenge = options.challenge?.($form);
        const onSuccess = options.onSuccess ?? showSuccess;
        const onError = options.onError ?? showError;

        const clear = () => {
            $form.querySelectorAll('[aria-invalid]').forEach(($input) => $input.removeAttribute('aria-invalid'));
            delete $form.dataset.state;

            const $message = getMessage($form);

            if ($message) {
                $message.hidden = true;
            }
        };

        const answer = (data: Answer) => {
            $form.dataset.state = data.status === 200 ? 'success' : 'error';

            if (data.status === 200) {
                onSuccess($form, data);
                return;
            }

            Object.keys(data.errors ?? {}).forEach((name) => {
                if (name) {
                    $form.querySelector(`[name="${CSS.escape(name)}"]`)?.setAttribute('aria-invalid', 'true');
                }
            });

            onError($form, data);
        };

        $form.addEventListener('submit', (event) => {
            event.preventDefault();

            if ($form.inert) {
                return;
            }

            clear();
            $form.dataset.state = 'busy';
            $form.inert = true;

            (challenge ? challenge.solve() : Promise.resolve({} as Fields))
                .then((fields) => {
                    const body = new FormData($form);
                    Object.entries(fields).forEach(([name, value]) => body.set(name, value));

                    return fetch($form.dataset.action!, {method: 'post', credentials: 'same-origin', body});
                })
                .then((response) => response.json() as Promise<Answer>)
                .then(answer)
                .catch((error: unknown) => {
                    console.error(error);
                    answer({status: 0, message: $form.dataset.errorMessage ?? String(error)});
                })
                .finally(() => {
                    challenge?.reset();
                    $form.inert = false;
                });
        });
    });
};
