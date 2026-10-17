package com.minthantzintun.trypost.core.designsystem

import androidx.compose.material3.darkColorScheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Immutable
import androidx.compose.runtime.staticCompositionLocalOf
import androidx.compose.ui.graphics.Color

/*
 * The web app's shadcn Sera tokens (resources/css/app.css), converted from
 * OKLCH to sRGB. Lime primary always carries its dark lime foreground.
 */
private val Lime400 = Color(0xFF9AE600)
private val Lime700 = Color(0xFF497D00)
private val Lime900 = Color(0xFF35530E)
private val Neutral50 = Color(0xFFFAFAFA)
private val Neutral100 = Color(0xFFF5F5F5)
private val Neutral200 = Color(0xFFE5E5E5)
private val Neutral400 = Color(0xFFA1A1A1)
private val Neutral500 = Color(0xFF737373)
private val Neutral800 = Color(0xFF262626)
private val Neutral850 = Color(0xFF2E2E2E)
private val Neutral900 = Color(0xFF171717)
private val Neutral950 = Color(0xFF0A0A0A)
private val Red400 = Color(0xFFFF6467)
private val Red600 = Color(0xFFE7000B)

internal val LightColors = lightColorScheme(
    primary = Lime400,
    onPrimary = Lime900,
    primaryContainer = Lime400,
    onPrimaryContainer = Lime900,
    secondary = Neutral100,
    onSecondary = Neutral900,
    secondaryContainer = Neutral100,
    onSecondaryContainer = Neutral900,
    background = Color.White,
    onBackground = Neutral950,
    surface = Color.White,
    onSurface = Neutral950,
    surfaceVariant = Neutral100,
    onSurfaceVariant = Neutral500,
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color.White,
    surfaceContainer = Color.White,
    surfaceContainerHigh = Neutral100,
    surfaceContainerHighest = Neutral100,
    outline = Neutral200,
    outlineVariant = Neutral200,
    error = Red600,
    onError = Color.White,
)

internal val DarkColors = darkColorScheme(
    primary = Lime400,
    onPrimary = Lime900,
    primaryContainer = Lime400,
    onPrimaryContainer = Lime900,
    secondary = Neutral800,
    onSecondary = Neutral50,
    secondaryContainer = Neutral800,
    onSecondaryContainer = Neutral50,
    background = Neutral950,
    onBackground = Neutral50,
    surface = Neutral950,
    onSurface = Neutral50,
    surfaceVariant = Neutral800,
    onSurfaceVariant = Neutral400,
    surfaceContainerLowest = Neutral950,
    surfaceContainerLow = Neutral900,
    surfaceContainer = Neutral900,
    surfaceContainerHigh = Neutral800,
    surfaceContainerHighest = Neutral800,
    outline = Neutral850,
    outlineVariant = Neutral850,
    error = Red400,
    onError = Neutral950,
)

/** The Sera colours Material 3 has no slot for: status tones and lime text. */
@Immutable
data class StatusColors(
    val primaryText: Color,
    val success: Color,
    val warning: Color,
    val info: Color,
    val neutral: Color,
    val danger: Color,
)

internal val LightStatusColors = StatusColors(
    primaryText = Lime700,
    success = Color(0xFF009966),
    warning = Color(0xFFE17100),
    info = Color(0xFF155DFC),
    neutral = Neutral500,
    danger = Red600,
)

internal val DarkStatusColors = StatusColors(
    primaryText = Lime400,
    success = Color(0xFF00D492),
    warning = Color(0xFFFFB900),
    info = Color(0xFF51A2FF),
    neutral = Neutral400,
    danger = Red400,
)

val LocalStatusColors = staticCompositionLocalOf { LightStatusColors }
