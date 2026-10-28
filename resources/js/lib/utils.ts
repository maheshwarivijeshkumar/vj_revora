import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

const twMerge = extendTailwindMerge({
    extend: {
        classGroups: {
            'font-size': [
                {
                    text: [
                        'caption',
                        'small',
                        'body-lg',
                        'h4',
                        'h3',
                        'h2',
                        'h1',
                        'display',
                        'hero',
                    ],
                },
            ],
        },
    },
});

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}
