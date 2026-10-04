export const ContentType = {
    FacebookPost: 'facebook_post',
    FacebookReel: 'facebook_reel',
    FacebookStory: 'facebook_story',
    TikTokVideo: 'tiktok_video',
    TikTokPhoto: 'tiktok_photo',
    YouTubeShort: 'youtube_short',
} as const;

export type ContentTypeValue = (typeof ContentType)[keyof typeof ContentType];
