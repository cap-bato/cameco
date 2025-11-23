import { InertiaLinkProps } from '@inertiajs/react';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
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
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

// ============================================================================
// ATS MODULE TYPES
// ============================================================================

// Export all ATS types for use across the application
export * from './ats-pages';

// ============================================================================
// APPRAISAL MODULE TYPES
// ============================================================================

// Export all Appraisal types for use across the application
export * from './appraisal-pages';

// ============================================================================
// PAYROLL MODULE TYPES
// ============================================================================

// Export all Payroll types for use across the application
export * from './payroll-pages';

// ============================================================================
// WORKFORCE MANAGEMENT MODULE TYPES
// ============================================================================

// Export all Workforce Management types for use across the application
export * from './workforce-pages';

// ============================================================================
// TIMEKEEPING MODULE TYPES
// ============================================================================

// Export all Timekeeping types for use across the application
export * from './timekeeping-pages';
