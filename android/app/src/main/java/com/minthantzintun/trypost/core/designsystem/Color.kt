package com.minthantzintun.trypost.core.designsystem

import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

/*
 * Material 3 Expressive with the TryPost lime (ADR 0014). Dark is the primary
 * look: near-black surfaces that step up in tone, lime with dark ink on it.
 * Light keeps the lime as a tonal container and uses a deep lime for text.
 * Every text pair here is at least 4.5:1, checked for both themes.
 */
internal val DarkColors = darkColorScheme(
    primary = Color(0xFFA3E635),
    onPrimary = Color(0xFF1A2B00),
    primaryContainer = Color(0xFF2F4A06),
    onPrimaryContainer = Color(0xFFCBF48A),
    inversePrimary = Color(0xFF3F6300),
    secondary = Color(0xFFC0C9AE),
    onSecondary = Color(0xFF2A331D),
    secondaryContainer = Color(0xFF2C3324),
    onSecondaryContainer = Color(0xFFDCE5C9),
    tertiary = Color(0xFF8FD3CB),
    onTertiary = Color(0xFF003733),
    tertiaryContainer = Color(0xFF1B4D48),
    onTertiaryContainer = Color(0xFFB0F0E7),
    background = Color(0xFF0E100C),
    onBackground = Color(0xFFE4E7DC),
    surface = Color(0xFF0E100C),
    onSurface = Color(0xFFE4E7DC),
    surfaceVariant = Color(0xFF2B2F27),
    onSurfaceVariant = Color(0xFFB9BDAE),
    surfaceTint = Color(0xFFA3E635),
    inverseSurface = Color(0xFFE4E7DC),
    inverseOnSurface = Color(0xFF2B2F27),
    surfaceDim = Color(0xFF0E100C),
    surfaceBright = Color(0xFF34382F),
    surfaceContainerLowest = Color(0xFF090B07),
    surfaceContainerLow = Color(0xFF151712),
    surfaceContainer = Color(0xFF1A1D16),
    surfaceContainerHigh = Color(0xFF22251E),
    surfaceContainerHighest = Color(0xFF2B2F27),
    outline = Color(0xFF8A8F80),
    outlineVariant = Color(0xFF363A31),
    error = Color(0xFFFFB4AB),
    onError = Color(0xFF690005),
    errorContainer = Color(0xFF5C1410),
    onErrorContainer = Color(0xFFFFDAD6),
    scrim = Color(0xFF000000),
)

internal val LightColors = lightColorScheme(
    primary = Color(0xFF3F6300),
    onPrimary = Color(0xFFFFFFFF),
    primaryContainer = Color(0xFFB9F06A),
    onPrimaryContainer = Color(0xFF112000),
    inversePrimary = Color(0xFFA3E635),
    secondary = Color(0xFF586249),
    onSecondary = Color(0xFFFFFFFF),
    secondaryContainer = Color(0xFFDCE5C9),
    onSecondaryContainer = Color(0xFF151E0A),
    tertiary = Color(0xFF39665F),
    onTertiary = Color(0xFFFFFFFF),
    tertiaryContainer = Color(0xFFBCECE3),
    onTertiaryContainer = Color(0xFF00201C),
    background = Color(0xFFF8FAF2),
    onBackground = Color(0xFF191C15),
    surface = Color(0xFFF8FAF2),
    onSurface = Color(0xFF191C15),
    surfaceVariant = Color(0xFFDFE2D7),
    onSurfaceVariant = Color(0xFF45493E),
    surfaceTint = Color(0xFF3F6300),
    inverseSurface = Color(0xFF2E3129),
    inverseOnSurface = Color(0xFFF0F2E8),
    surfaceDim = Color(0xFFD8DBD0),
    surfaceBright = Color(0xFFF8FAF2),
    surfaceContainerLowest = Color(0xFFFFFFFF),
    surfaceContainerLow = Color(0xFFF1F3EA),
    surfaceContainer = Color(0xFFEBEEE3),
    surfaceContainerHigh = Color(0xFFE5E8DD),
    surfaceContainerHighest = Color(0xFFDFE2D7),
    outline = Color(0xFF757A6C),
    outlineVariant = Color(0xFFC6CABA),
    error = Color(0xFFBA1A1A),
    onError = Color(0xFFFFFFFF),
    errorContainer = Color(0xFFFFDAD6),
    onErrorContainer = Color(0xFF410002),
    scrim = Color(0xFF000000),
)

/** A status colour: [content] for its icon and word, on its own [container]. */
@Immutable
data class Tone(val content: Color, val container: Color)

/** The status tones Material 3 has no slot for. Each pair is at least 4.5:1. */
@Immutable
data class StatusColors(
    val scheduled: Tone,
    val publishing: Tone,
    val published: Tone,
    val failed: Tone,
    val neutral: Tone,
)

internal val DarkStatusColors = StatusColors(
    scheduled = Tone(Color(0xFF8DB8FF), Color(0xFF14284A)),
    publishing = Tone(Color(0xFFF5C04A), Color(0xFF3B2C00)),
    published = Tone(Color(0xFF6FDDA0), Color(0xFF0C3423)),
    failed = Tone(Color(0xFFFFB4AB), Color(0xFF5C1410)),
    neutral = Tone(Color(0xFFB9BDAE), Color(0xFF2B2F27)),
)

internal val LightStatusColors = StatusColors(
    scheduled = Tone(Color(0xFF1A57C9), Color(0xFFDCE6FF)),
    publishing = Tone(Color(0xFF7A5300), Color(0xFFFFE8B3)),
    published = Tone(Color(0xFF08734A), Color(0xFFCFF3DF)),
    failed = Tone(Color(0xFFBA1A1A), Color(0xFFFFDAD6)),
    neutral = Tone(Color(0xFF45493E), Color(0xFFE5E8DD)),
)

val LocalStatusColors = staticCompositionLocalOf { DarkStatusColors }

/**
 * The eight Tag group colours (`TagColor`), each a text tone on its own
 * container like the status tones, at least 4.5:1 in both themes.
 */
@Immutable
data class TagColors(
    val gray: Tone,
    val red: Tone,
    val orange: Tone,
    val yellow: Tone,
    val green: Tone,
    val teal: Tone,
    val blue: Tone,
    val brown: Tone,
)

internal val DarkTagColors = TagColors(
    gray = Tone(Color(0xFFC6CABA), Color(0xFF2B2F27)),
    red = Tone(Color(0xFFFFB4AB), Color(0xFF5C1410)),
    orange = Tone(Color(0xFFFFB68A), Color(0xFF4F2400)),
    yellow = Tone(Color(0xFFF0D264), Color(0xFF3A3000)),
    green = Tone(Color(0xFF6FDDA0), Color(0xFF0C3423)),
    teal = Tone(Color(0xFF6FD8CC), Color(0xFF003833)),
    blue = Tone(Color(0xFF8DB8FF), Color(0xFF14284A)),
    brown = Tone(Color(0xFFE0BFA8), Color(0xFF3D2A1E)),
)

internal val LightTagColors = TagColors(
    gray = Tone(Color(0xFF45493E), Color(0xFFE5E8DD)),
    red = Tone(Color(0xFFBA1A1A), Color(0xFFFFDAD6)),
    orange = Tone(Color(0xFF8F3F00), Color(0xFFFFDBC8)),
    yellow = Tone(Color(0xFF5F4E00), Color(0xFFFBE38A)),
    green = Tone(Color(0xFF08734A), Color(0xFFCFF3DF)),
    teal = Tone(Color(0xFF00655E), Color(0xFFC4F1EB)),
    blue = Tone(Color(0xFF1A57C9), Color(0xFFDCE6FF)),
    brown = Tone(Color(0xFF6F4A33), Color(0xFFF2DED2)),
)

val LocalTagColors = staticCompositionLocalOf { DarkTagColors }

/** The networks' own logo colours; TikTok's mark follows the text colour. */
internal val FacebookBlue = Color(0xFF0866FF)
internal val YouTubeRed = Color(0xFFFF0033)
