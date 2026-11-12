const loaded = new Map<string, Promise<void>>();

/**
 * Loads a script once per page, however many forms ask for it.
 */
export default (src: string): Promise<void> => {
    let promise = loaded.get(src);

    if (!promise) {
        promise = new Promise<void>((resolve, reject) => {
            const $script = document.createElement('script');
            $script.async = true;
            $script.src = src;
            $script.onload = () => resolve();
            $script.onerror = () => {
                loaded.delete(src);
                reject(new Error(`Failed to load ${src}`));
            };

            document.head.append($script);
        });

        loaded.set(src, promise);
    }

    return promise;
};
