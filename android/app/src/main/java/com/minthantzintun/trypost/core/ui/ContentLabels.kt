package com.minthantzintun.trypost.core.ui

import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource
import com.minthantzintun.trypost.R
import com.minthantzintun.trypost.core.model.PlatformText

@Composable
fun PlatformText.label(): String = stringResource(
    when (this) {
        PlatformText.Title -> R.string.text_title
        PlatformText.Description -> R.string.text_description
        PlatformText.Caption -> R.string.text_caption
        PlatformText.Tags -> R.string.text_tags
    },
)

/** A Content type's name, or `null` for one the app does not know. */
@Composable
fun contentTypeLabel(contentType: String?): String? = when (contentType) {
    "facebook_post" -> stringResource(R.string.content_type_facebook_post)
    "facebook_reel" -> stringResource(R.string.content_type_facebook_reel)
    "facebook_story" -> stringResource(R.string.content_type_facebook_story)
    "tiktok_video" -> stringResource(R.string.content_type_tiktok_video)
    "tiktok_photo" -> stringResource(R.string.content_type_tiktok_photo)
    "youtube_short" -> stringResource(R.string.content_type_youtube_short)
    else -> null
}
