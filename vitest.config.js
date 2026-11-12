import {defineConfig} from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'happy-dom',
        include: ['resources/tests/**/*.test.ts'],
    },
});
