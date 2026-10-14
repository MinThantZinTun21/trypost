---
paths:
  - 'resources/js/**'
---

# Js

## Use the shadcn-vue ui components, not hand-rolled markup
Pages and components render buttons, badges, cards, alerts, form fields, empty states, spinners, separators, tables, selects, textareas and progress bars through resources/js/components/ui (restyle with `class`). Exceptions: custom widgets the components can't express (the composer caption textarea with its overflow mirror) and the platform previews. tests/Unit/ShadcnComponentsTest.php fails on a raw table/select/textarea/hr/progress or non-file input outside components/ui; list a deliberate exception there with its reason.

## Sera design: tokens and ui components own the look
The look is the shadcn Sera preset, ported by hand into resources/css/app.css and components/ui. Radius 0, hairline borders, shadow-sm on surfaces (overlays keep the preset's shadow-md), uppercase tracked text-xs labels, Roboto + Roboto Slab (font-display). Lime text on white is text-primary-text (text-primary is unreadable); bg-primary pairs with text-primary-foreground. rounded-full only for avatars (with their +N chip and empty slot), dots and spinners; previews keep their own styling. A page with a sticky mobile action bar passes mobile-action-bar to AppLayout so the help button lifts above it (MobileOverflowTest checks this).
