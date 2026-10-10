import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fill="currentColor"
                fillRule="evenodd"
                d="M7 5h10c5 0 8 2.5 8 6.5 0 2.1-1 3.6-2.9 4.5 2.5.8 3.9 2.6 3.9 5 0 4-3.4 6-8.5 6H7V5zm5 4v5h4.5c2.4 0 3.5-.9 3.5-2.5S18.9 9 16.5 9H12zm0 9v5h5c2.6 0 4-.8 4-2.5S19.6 18 17 18h-5z"
            />
        </svg>
    );
}
