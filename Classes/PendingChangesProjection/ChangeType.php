<?php

declare(strict_types=1);

namespace Neos\Neos\PendingChangesProjection;

/**
 * Type of changes
 *
 * @api
 */
enum ChangeType : string
{
    case CREATED = 'created';
    case CHANGED = 'changed';
    case MOVED = 'moved';
    case DELETED = 'deleted';
}
