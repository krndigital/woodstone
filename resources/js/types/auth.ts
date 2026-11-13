export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    phone: string | null;
    avatar?: string;
    role: string;
    role_label: string;
    permissions: string[];
    has_pin: boolean;
    can_use_pin: boolean;
    two_factor_enabled?: boolean;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type SharedTerminal = {
    id: number;
    name: string;
} | null;

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
