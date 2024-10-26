<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Neos\Security\Authorization\Privilege;

use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTags;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;

/**
 * A subject for the {@see ReadNodePrivilege}
 */
final readonly class SubtreeTagPrivilegeSubject implements PrivilegeSubjectInterface
{
    public function __construct(
        public SubtreeTags $subTreeTags,
        public ContentRepositoryId|null $contentRepositoryId = null,
    ) {
    }

    public function __toString(): string
    {
        $label = 'tag' . ($this->subTreeTags->count() > 1 ? 's' : '') . ' "' . implode('", "', $this->subTreeTags->toStringArray()) . '"';
        if ($this->contentRepositoryId !== null) {
            $label .= ' in Content Repository "' . $this->contentRepositoryId->value . '"';
        }
        return $label;
    }
}
