<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020-2024 LibreCode coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\AdminGroupManager\Middleware;

use OCA\AdminGroupManager\Controller\AEnvironmentAwareController;
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
		if ($controller instanceof AEnvironmentAwareController) {
			$apiVersion = $this->request->getParam('apiVersion');
			/** @var AEnvironmentAwareController $controller */
			$controller->setAPIVersion((int)substr($apiVersion, 1));
		}

		$reflectionMethod = new \ReflectionMethod($controller, $methodName);

		if (!empty($reflectionMethod->getAttributes(RestrictIp::class))) {
			$this->restrictIp();
		}
	}

	private function restrictIp(): void {
		$ip = $this->request->getRemoteAddress();
		$allowed = $this->config->getSystemValue('admin_group_manager_allowed_ip');
		if ($allowed !== $ip) {
			$this->logger->error('Unauthorized access to API', ['IP' => $ip]);
			throw new OCSException('', Http::STATUS_UNAUTHORIZED);
		}
	}
}
