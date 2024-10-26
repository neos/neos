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
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Flow\Security\Authorization\Privilege\AbstractPrivilege;
use Neos\Flow\Security\Authorization\Privilege\PrivilegeSubjectInterface;
use Neos\Flow\Security\Exception\InvalidPrivilegeTypeException;

/**
 * TODO docs
 */
class ReadNodePrivilege extends AbstractSubtreeTagBasedPrivilege
{
}
