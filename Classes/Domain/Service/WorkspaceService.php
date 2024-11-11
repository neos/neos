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

namespace Neos\Neos\Domain\Service;

use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateRootWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Command\CreateWorkspace;
use Neos\ContentRepository\Core\Feature\WorkspaceCreation\Exception\WorkspaceAlreadyExists;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Exception\AccessDeniedException;
use Neos\Neos\Domain\Model\User;
use Neos\Neos\Domain\Model\UserId;
use Neos\Neos\Domain\Model\WorkspaceClassification;
use Neos\Neos\Domain\Model\WorkspaceDescription;
use Neos\Neos\Domain\Model\WorkspaceMetadata;
use Neos\Neos\Domain\Model\WorkspaceRole;
use Neos\Neos\Domain\Model\WorkspaceRoleAssignment;
use Neos\Neos\Domain\Model\WorkspaceRoleAssignments;
use Neos\Neos\Domain\Model\WorkspaceRoleSubject;
use Neos\Neos\Domain\Model\WorkspaceTitle;
use Neos\Neos\Domain\Repository\WorkspaceMetadataAndRoleRepository;
use Neos\Neos\Security\Authorization\ContentRepositoryAuthorizationService;

/**
 * Central authority to interact with Content Repository Workspaces within Neos
 *
 * @api
 */
#[Flow\Scope('singleton')]
final readonly class WorkspaceService
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private WorkspaceMetadataAndRoleRepository $metadataAndRoleRepository,
        private UserService $userService,
        private ContentRepositoryAuthorizationService $authorizationService,
        private SecurityContext $securityContext,
    ) {
    }

    /**
     * Load metadata for the specified workspace
     *
     * Note: If no metadata exists for the specified workspace, metadata with title based on the name and classification
     * according to the content repository workspace is returned. Root workspaces are of classification ROOT whereas simple ones will yield UNKNOWN.
     * {@see WorkspaceClassification::UNKNOWN}
     */
    public function getWorkspaceMetadata(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName): WorkspaceMetadata
    {
        $workspace = $this->requireWorkspace($contentRepositoryId, $workspaceName);
        $metadata = $this->metadataAndRoleRepository->loadWorkspaceMetadata($contentRepositoryId, $workspaceName);
        return $metadata ?? new WorkspaceMetadata(
            WorkspaceTitle::fromString($workspaceName->value),
            WorkspaceDescription::fromString(''),
            $workspace->isRootWorkspace() ? WorkspaceClassification::ROOT : WorkspaceClassification::UNKNOWN,
            null,
        );
    }

    /**
     * Update/set title metadata for the specified workspace
     *
     * NOTE: The workspace privileges are not evaluated for this interaction, this should be done in the calling side if needed
     */
    public function setWorkspaceTitle(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceTitle $newWorkspaceTitle): void
    {
        $this->requireManagementWorkspacePermission($contentRepositoryId, $workspaceName);
        $workspace = $this->requireWorkspace($contentRepositoryId, $workspaceName);
        $this->metadataAndRoleRepository->updateWorkspaceMetadata($contentRepositoryId, $workspace, title: $newWorkspaceTitle->value, description: null);
    }

    /**
     * Update/set description metadata for the specified workspace
     *
     * NOTE: The workspace privileges are not evaluated for this interaction, this should be done in the calling side if needed
     */
    public function setWorkspaceDescription(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceDescription $newWorkspaceDescription): void
    {
        $this->requireManagementWorkspacePermission($contentRepositoryId, $workspaceName);
        $workspace = $this->requireWorkspace($contentRepositoryId, $workspaceName);
        $this->metadataAndRoleRepository->updateWorkspaceMetadata($contentRepositoryId, $workspace, title: null, description: $newWorkspaceDescription->value);
    }

    /**
     * Retrieve the currently active personal workspace for the specified $userId
     *
     * NOTE: Currently there can only ever be a single personal workspace per user. But this API already prepares support for multiple personal workspaces per user
     */
    public function getPersonalWorkspaceForUser(ContentRepositoryId $contentRepositoryId, UserId $userId): Workspace
    {
        $workspaceName = $this->metadataAndRoleRepository->findPrimaryWorkspaceNameForUser($contentRepositoryId, $userId);
        if ($workspaceName === null) {
            throw new \RuntimeException(sprintf('No workspace is assigned to the user with id "%s")', $userId->value), 1718293801);
        }
        return $this->requireWorkspace($contentRepositoryId, $workspaceName);
    }

    /**
     * Create a new root (aka base) workspace with the specified metadata
     *
     * @throws WorkspaceAlreadyExists
     */
    public function createRootWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceTitle $title, WorkspaceDescription $description): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentRepository->handle(
            CreateRootWorkspace::create(
                $workspaceName,
                ContentStreamId::create()
            )
        );
        $this->metadataAndRoleRepository->addWorkspaceMetadata($contentRepositoryId, $workspaceName, $title, $description, WorkspaceClassification::ROOT, null);
    }

    /**
     * Create the "live" root workspace with the default role assignment (users with the role "Neos.Neos:LivePublisher" are collaborators)
     */
    public function createLiveWorkspaceIfMissing(ContentRepositoryId $contentRepositoryId): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $workspaceName = WorkspaceName::forLive();
        $liveWorkspace = $contentRepository->findWorkspaceByName($workspaceName);
        if ($liveWorkspace !== null) {
            // live workspace already exists
            return;
        }
        $this->createRootWorkspace($contentRepositoryId, $workspaceName, WorkspaceTitle::fromString('Public live workspace'), WorkspaceDescription::empty());
        $this->metadataAndRoleRepository->assignWorkspaceRole($contentRepositoryId, $workspaceName, WorkspaceRoleAssignment::createForGroup('Neos.Neos:LivePublisher', WorkspaceRole::COLLABORATOR));
    }

    /**
     * Create a new, personal, workspace for the specified user
     */
    public function createPersonalWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceTitle $title, WorkspaceDescription $description, WorkspaceName $baseWorkspaceName, UserId $ownerId): void
    {
        $this->createWorkspace($contentRepositoryId, $workspaceName, $title, $description, $baseWorkspaceName, $ownerId, WorkspaceClassification::PERSONAL);
    }

    /**
     * Create a new, potentially shared, workspace
     */
    public function createSharedWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceTitle $title, WorkspaceDescription $description, WorkspaceName $baseWorkspaceName): void
    {
        $this->createWorkspace($contentRepositoryId, $workspaceName, $title, $description, $baseWorkspaceName, null, WorkspaceClassification::SHARED);
    }

    /**
     * Create a new, personal, workspace for the specified user if none exists yet
     * @internal experimental api, until actually used by the Neos.Ui
     */
    public function createPersonalWorkspaceForUserIfMissing(ContentRepositoryId $contentRepositoryId, User $user): void
    {
        $existingWorkspaceName = $this->metadataAndRoleRepository->findPrimaryWorkspaceNameForUser($contentRepositoryId, $user->getId());
        if ($existingWorkspaceName !== null) {
            $this->requireWorkspace($contentRepositoryId, $existingWorkspaceName);
            return;
        }
        $workspaceName = $this->getUniqueWorkspaceName($contentRepositoryId, $user->getLabel());
        $this->createPersonalWorkspace(
            $contentRepositoryId,
            $workspaceName,
            WorkspaceTitle::fromString($user->getLabel()),
            WorkspaceDescription::empty(),
            WorkspaceName::forLive(),
            $user->getId(),
        );
    }

    /**
     * Assign a workspace role to the given user/user group
     *
     * Without explicit workspace roles, only administrators can change the corresponding workspace.
     * With this method, the subject (i.e. a Neos user or group represented by a Flow role identifier) can be granted a {@see WorkspaceRole} for the specified workspace
     */
    public function assignWorkspaceRole(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceRoleAssignment $assignment): void
    {
        $this->requireManagementWorkspacePermission($contentRepositoryId, $workspaceName);
        $this->requireWorkspace($contentRepositoryId, $workspaceName);
        $this->metadataAndRoleRepository->assignWorkspaceRole($contentRepositoryId, $workspaceName, $assignment);
    }

    /**
     * Remove a workspace role assignment for the given subject
     *
     * @see self::assignWorkspaceRole()
     */
    public function unassignWorkspaceRole(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceRoleSubject $subject): void
    {
        $this->requireManagementWorkspacePermission($contentRepositoryId, $workspaceName);
        $this->requireWorkspace($contentRepositoryId, $workspaceName);
        $this->metadataAndRoleRepository->unassignWorkspaceRole($contentRepositoryId, $workspaceName, $subject);
    }

    /**
     * Get all role assignments for the specified workspace
     *
     * NOTE: This should never be used to evaluate permissions, instead {@see ContentRepositoryAuthorizationService::getWorkspacePermissions()} should be used!
     */
    public function getWorkspaceRoleAssignments(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName): WorkspaceRoleAssignments
    {
        return $this->metadataAndRoleRepository->getWorkspaceRoleAssignments($contentRepositoryId, $workspaceName);
    }

    /**
     * Builds a workspace name that is unique within the specified content repository.
     * If $candidate already refers to a workspace name that is not used yet, it will be used (with transliteration to enforce a valid format)
     * Otherwise a counter "-n" suffix is appended and increased until a unique name is found, or the maximum number of attempts has been reached (in which case an exception is thrown)
     */
    public function getUniqueWorkspaceName(ContentRepositoryId $contentRepositoryId, string $candidate): WorkspaceName
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $workspaceNameCandidate = WorkspaceName::transliterateFromString($candidate);
        $workspaceName = $workspaceNameCandidate;
        $attempt = 1;
        do {
            if ($contentRepository->findWorkspaceByName($workspaceName) === null) {
                return $workspaceName;
            }
            if ($attempt === 1) {
                $suffix = '';
            } else {
                $suffix = '-' . ($attempt - 1);
            }
            $workspaceName = WorkspaceName::fromString(
                substr($workspaceNameCandidate->value, 0, WorkspaceName::MAX_LENGTH - strlen($suffix)) . $suffix
            );
            $attempt++;
        } while ($attempt <= 10);
        throw new \RuntimeException(sprintf('Failed to find unique workspace name for "%s" after %d attempts.', $candidate, $attempt - 1), 1725975479);
    }

    // ------------------

    private function createWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, WorkspaceTitle $title, WorkspaceDescription $description, WorkspaceName $baseWorkspaceName, UserId|null $ownerId, WorkspaceClassification $classification): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $contentRepository->handle(
            CreateWorkspace::create(
                $workspaceName,
                $baseWorkspaceName,
                ContentStreamId::create()
            )
        );
        $this->metadataAndRoleRepository->addWorkspaceMetadata($contentRepositoryId, $workspaceName, $title, $description, $classification, $ownerId);
    }

    private function requireWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName): Workspace
    {
        $workspace = $this->contentRepositoryRegistry
            ->get($contentRepositoryId)
            ->findWorkspaceByName($workspaceName);
        if ($workspace === null) {
            throw new \RuntimeException(sprintf('Failed to find workspace with name "%s" for content repository "%s"', $workspaceName->value, $contentRepositoryId->value), 1718379722);
        }
        return $workspace;
    }

    private function requireManagementWorkspacePermission(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName): void
    {
        if ($this->securityContext->areAuthorizationChecksDisabled()) {
            return;
        }
        $workspacePermissions = $this->authorizationService->getWorkspacePermissions(
            $contentRepositoryId,
            $workspaceName,
            $this->securityContext->getRoles(),
            $this->userService->getCurrentUser()?->getId()
        );
        if (!$workspacePermissions->manage) {
            throw new AccessDeniedException(sprintf('The current user does not have manage permissions for workspace "%s" in content repository "%s"', $workspaceName->value, $contentRepositoryId->value), 1731343473);
        }
    }
}
