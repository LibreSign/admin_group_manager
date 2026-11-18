<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Listener;

use OCA\AdminGroupManager\AppInfo\Application;
use OCA\AdminGroupManager\Controller\AdminGroupController;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IConfig;
use OCP\User\Events\UserChangedEvent;

/**
 * @template-implements IEventListener<Event|UserChangedEvent>
 */
class UserEnabledListener implements IEventListener {
	public function __construct(
		private IConfig $config,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof UserChangedEvent) {
			return;
		}
		if ($event->getFeature() !== 'enabled' || $event->getValue() !== true) {
			return;
		}
		$this->config->deleteUserValue($event->getUser()->getUID(), Application::APP_ID, AdminGroupController::DISABLED_BY_BILLING);
	}
}
