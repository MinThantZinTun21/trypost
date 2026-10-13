<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Raw elements that resources/js/components/ui already wraps, keyed by the
 * shadcn-vue component to use instead.
 *
 * @var array<string, string>
 */
const SHADCN_WRAPPED_ELEMENTS = [
    'table' => 'Table',
    'select' => 'NativeSelect or Select',
    'textarea' => 'Textarea',
    'hr' => 'Separator',
    'progress' => 'Progress',
];

/**
 * Custom widgets allowed to keep a raw element, with the reason.
 *
 * @var array<string, list<string>>
 */
const SHADCN_RAW_ELEMENT_EXCEPTIONS = [
    // The caption textarea sits on a mirror div that highlights text past the
    // platform limit; both need identical font, padding and leading.
    'components/posts/editor/PostEditorComposer.vue' => ['textarea'],
];

/**
 * The raw elements in a Vue source that a shadcn-vue component should render.
 * A hidden `<input type="file">` is allowed, because no ui component wraps it.
 *
 * @return list<string>
 */
function rawElementsReplacedByShadcn(string $source): array
{
    $found = [];

    foreach (array_keys(SHADCN_WRAPPED_ELEMENTS) as $element) {
        if (preg_match("/<{$element}(?=[\\s>\\/])/", $source) === 1) {
            $found[] = $element;
        }
    }

    preg_match_all('/<input(?=[\s>\/])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', $source, $inputs);

    foreach ($inputs[0] as $input) {
        if (preg_match('/\stype="file"/', $input) !== 1) {
            $found[] = 'input';

            break;
        }
    }

    return $found;
}

test('the guard flags raw elements and allows hidden file inputs', function () {
    expect(rawElementsReplacedByShadcn('<table class="w-full"><tr></tr></table>'))->toBe(['table'])
        ->and(rawElementsReplacedByShadcn("<select\n    v-model=\"x\"\n>"))->toBe(['select'])
        ->and(rawElementsReplacedByShadcn('<hr />'))->toBe(['hr'])
        ->and(rawElementsReplacedByShadcn('<input v-model="name" />'))->toBe(['input'])
        ->and(rawElementsReplacedByShadcn("<input\n    ref=\"fileInput\"\n    :class=\"a > b\"\n    type=\"file\"\n/>"))->toBe([])
        ->and(rawElementsReplacedByShadcn('<Table><TableRow /></Table><Textarea /><Input />'))->toBe([])
        ->and(rawElementsReplacedByShadcn('<header></header><hr-like />'))->toBe([]);
});

test('pages and components use the shadcn-vue ui components, not raw form and table elements', function () {
    $root = resource_path('js');
    $violations = [];

    foreach (File::allFiles($root) as $file) {
        $path = Str::after($file->getPathname(), "{$root}/");

        if ($file->getExtension() !== 'vue' || Str::startsWith($path, 'components/ui/')) {
            continue;
        }

        $allowed = SHADCN_RAW_ELEMENT_EXCEPTIONS[$path] ?? [];
        $raw = array_diff(rawElementsReplacedByShadcn($file->getContents()), $allowed);

        if ($raw !== []) {
            $violations[] = "{$path}: ".implode(', ', $raw);
        }
    }

    expect($violations)->toBe([], 'Use the components in resources/js/components/ui instead.');
});
