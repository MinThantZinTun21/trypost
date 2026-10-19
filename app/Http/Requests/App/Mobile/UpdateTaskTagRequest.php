<?php

declare(strict_types=1);

namespace App\Http\Requests\App\Mobile;

/**
 * Renames a Task tag, unique within its Tag group like a new one.
 */
class UpdateTaskTagRequest extends StoreTaskTagRequest {}
