---
paths:
  - 'resources/js/**'
---

# Js

## Use the shadcn-vue ui components, not hand-rolled markup
Pages and components render buttons, badges, cards, alerts, form fields, empty states, spinners, separators, tables, selects, textareas and progress bars through resources/js/components/ui (restyle with `class`). Exceptions: custom widgets the components can't express (the composer caption textarea with its overflow mirror) and the platform previews. tests/Unit/ShadcnComponentsTest.php fails on a raw table/select/textarea/hr/progress or non-file input outside components/ui; list a deliberate exception there with its reason.
