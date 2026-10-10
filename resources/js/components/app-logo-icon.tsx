import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" aria-hidden="true">
            <path
                fill="currentColor"
                d="M5 5h9v9H5zm13 0h9v9h-9zM5 18h9v9H5zm13 0h9v9h-9z"
            />
        </svg>
    );
}
