export interface CalendarSocialAccount {
    id: string;
    platform: string;
    display_name: string;
    username: string | null;
    display_label: string;
}

export interface CalendarPostPlatform {
    id: string;
    platform: string;
    status: string;
    social_account: CalendarSocialAccount | null;
}

export interface CalendarAccountOption {
    id: string;
    platform: string;
    display_label: string;
}
