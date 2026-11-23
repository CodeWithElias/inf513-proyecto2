import { BASE } from '../lib/api';

/** Logo de TVU / Canal 11 UAGRM (public/img/logo-tvu.png). */
export default function Logo({ className = 'size-12' }) {
    return (
        <img
            src={`${BASE}/img/logo-tvu.png`}
            alt="TVU Canal 11 UAGRM"
            className={`${className} rounded-tema bg-white object-cover shrink-0`}
        />
    );
}
