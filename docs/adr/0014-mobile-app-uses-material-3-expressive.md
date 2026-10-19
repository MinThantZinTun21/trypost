# The Mobile app uses Material 3 Expressive, not the web's Sera look

The Mobile app first copied the web's shadcn **Sera** look onto Compose: square-ish corners, hairline borders, uppercase tracked labels on every button and tab, "FB/TT/YT" text tags for Social accounts. On a phone that read as a port, not an app. The app now has its own style, **Material 3 Expressive with the TryPost lime**, set once in the Compose design system (`core/designsystem`: colours, type, shapes, spacing, icon sizes and the shared components) so screens read tokens, never raw colours.

- Dark (near-black surfaces that step up in tone) is the primary look, light is fully supported, and the app follows the system unless the Owner picks Dark or Light in Settings. The choice lives in its own DataStore, so Sign out does not reset it.
- The lime brand replaces wallpaper (dynamic) colour, so status colours stay predictable. Every text pair is at least 4.5:1 in both themes.
- Labels are sentence case in the system Roboto; corners are rounded (8 to 28 dp).
- A status is a pill with an icon, a word and a tone, never colour alone. Social accounts show their network's logo.
- Icons are **Tabler Icons** (MIT, the web's icon family) converted to vector drawables, with the three platform logos. No icon library dependency.

## Considered Options

- **Keep Sera on the phone**: rejected. Uppercase labels and tiny text tags are slower to read and tap, and Material components fight the square, underlined look.
- **Dynamic (wallpaper) colour**: rejected. It would recolour the lime brand and could clash with the status tones.
- **Material Symbols icons**: rejected. The Android SDK's copy is missing icons the app needs, and the full set would mean a download or a dependency; Tabler is already in the repo and matches the web.

## Consequences

- The web keeps Sera; the two looks share the lime brand, the status meanings and the icon family, not their components.
- A new screen builds from `core/designsystem` (`FormField`, `StatusPill`, `PlatformLogo`, `StateMessage`, `Skeleton`, `ConfirmDialog`, …) rather than its own styling.
