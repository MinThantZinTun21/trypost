export const YOUTUBE_TITLE_MAX_CHARACTERS = 100;

export const youtubeTitleCharacters = (title: string): number =>
    [...title].length;
