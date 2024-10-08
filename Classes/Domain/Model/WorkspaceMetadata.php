<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Model;

use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * Neos-specific metadata of a workspace
 *
 * @api
 */
#[Flow\Proxy(false)]
final readonly class WorkspaceMetadata
{
    public function __construct(
        public WorkspaceName $workspaceName,
        public WorkspaceTitle $title,
        public WorkspaceDescription $description,
        public WorkspaceClassification $classification,
        public UserId|null $ownerUserId,
    ) {
    }

    /**
     * Note: To be used with named arguments!
     */
    public function with(
        WorkspaceTitle $title = null,
        WorkspaceDescription $description = null,
    ): self {
        return new self(
            $this->workspaceName,
            $title ?? $this->title,
            $description ?? $this->description,
            $this->classification,
            $this->ownerUserId,
        );
    }
}
