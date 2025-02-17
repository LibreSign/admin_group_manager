<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Middleware;

use OC\Security\Ip\Address;
use OC\Security\Ip\Range;
use OCA\AdminGroupManager\Controller\AEnvironmentAwareOCSController;
use OCA\AdminGroupManager\Controller\Attribute\RestrictIp;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\OCS\OCSException;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class InjectionMiddleware extends Middleware {

	public function __construct(
		private IRequest $request,
		private IConfig $config,
		private LoggerInterface $logger,
	) {
		$this->request = $request;
	}

	/**
	 * @param Controller $controller
	 * @param string $methodName
	 * @throws \Exception
	 */
	public function beforeController(Controller $controller, string $methodName) {
		if ($controller instanceof AEnvironmentAwareOCSController) {
			$apiVersion = $this->request->getParam('apiVersion');
			/** @var AEnvironmentAwareOCSController $controller */
			$controller->setAPIVersion((int)substr($apiVersion, 1));
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		if (!empty($reflectionMethod->getAttributes(RestrictIp::class))) {
			$this->restrictIp();
		}
	}

	private function restrictIp(): void {
		$ip = new Address(
			$this->request->getRemoteAddress()
		);
		$ranges = $this->config->getSystemValue('admin_group_manager_allowed_range');
		if (!is_array($ranges) || empty($ranges)) {
			return;
		}
		foreach ($ranges as $range) {
			if ((new Range($range))->contains($ip)) {
				return;
			}
		}
		$this->logger->error('Unauthorized access to API', ['IP' => $ip]);
		throw new OCSException('', Http::STATUS_UNAUTHORIZED);
	}
}
