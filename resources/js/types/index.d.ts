import { InertiaLinkProps } from '@inertiajs/vue3';
import type { Component } from 'vue';

import type { ContentTypeMediaRule } from '@/lib/contentTypeMediaRules';
import type { WelcomeSummary } from '@/types/welcome';

export type {
    WelcomeNetwork,
    WelcomeStep,
    WelcomeSummary,
} from '@/types/welcome';

export type WorkspaceRole = 'owner' | 'admin' | 'member' | 'viewer';

export interface Workspace {
    id: string;
    name: string;
    logo_url: string | null;
    role?: WorkspaceRole | null;
    [key: string]: unknown;
}

export interface AuthAccount {
    id: string;
    name: string;
    created_at: string | null;
}

export interface Auth {
    user: User;
    role: WorkspaceRole | null;
    currentWorkspace: Workspace | null;
    workspaces: Workspace[];
    account: AuthAccount | null;
}

export interface FlashData {
    banner?: string;
    bannerStyle?: 'success' | 'danger' | 'info' | 'warning';
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    plainToken?: string;
    [key: string]: unknown;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: Component;
    isActive?: boolean;
    activePattern?: string;
    exact?: boolean;
    excludeActive?: string[];
    badge?: string;
}

export interface LegalLinks {
    terms: string;
    privacy: string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: FlashData;
    sidebarOpen: boolean;
    legal: LegalLinks;
    contentTypeMediaRules?: Record<string, ContentTypeMediaRule>;
    welcome?: WelcomeSummary;
    [key: string]: unknown;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & SharedData;

export interface User {
    id: string;
    name: string;
    first_name: string;
    email: string;
    has_photo: boolean;
    photo_url: string | null;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export type BreadcrumbItem = {
    title: string;
    href?: string;
};

export interface PinterestBoard {
    id: string;
    name: string;
}

/** Per-account payload from ListPinterestBoards (Inertia). */
export interface PinterestBoardsPayload {
    boards: PinterestBoard[];
    truncated: boolean;
}
