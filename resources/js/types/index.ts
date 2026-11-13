import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    auth: Auth;
    /** Shared site contact block (footer, contact page). */
    contact: {
        address: string;
        email: string;
        phone: string;
        socials: Record<string, string>;
        logo: string;
    };
    /** Published value-chain clusters for the header dropdown. */
    navClusters?: { label: string; href: string }[];
    /** Rotating quote on the auth split layout (optional). */
    quote?: { message: string; author: string };
    /** Page flash status messages. */
    flash?: { contact?: string | null; newsletter?: string | null };
    [key: string]: unknown; // This allows for additional properties...
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
