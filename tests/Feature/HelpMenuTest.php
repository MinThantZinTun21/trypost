<?php

declare(strict_types=1);

test('english community group copy is community', function () {
    expect(__('sidebar.support.community'))->toBe('Community');
});
