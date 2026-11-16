<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Listener;

use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\BeforeUserAddedEvent;
use OCP\Group\ISubAdmin;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserSession;

/**
 * @template-implements IEventListener<Event|BeforeUserAddedEvent>
 */
class BeforeUserAddedListener implements IEventListener {
	public function __construct(
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private ISubAdmin $subAdmin,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof BeforeUserAddedEvent) {
			return;
		}
		$author = $this->userSession->getUser();
		if (!$author instanceof IUser) {
			return;
		}
		if ($this->groupManager->isAdmin($author->getUID()) || $this->groupManager->isDelegatedAdmin($author->getUID())) {
			return;
		}
		if ($this->canBeManagedBy($event->getUser(), $author)) {
			return;
		}
		throw new OCSException('This user cannot be added to the group', Http::STATUS_FORBIDDEN);
	}

	private function canBeManagedBy(IUser $target, IUser $author): bool {
		$targetGroupIds = $this->groupManager->getUserGroupIds($target);
		if ($targetGroupIds === []) {
			return $target->getLastLogin() === 0 && !$this->subAdmin->isSubAdmin($target);
		}
		$managedGroupIds = array_map(
			static fn (IGroup $group): string => $group->getGID(),
			$this->subAdmin->getSubAdminsGroups($author),
		);
		return array_diff($targetGroupIds, $managedGroupIds) === [];
	}
}
