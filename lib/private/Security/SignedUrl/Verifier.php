<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2020, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Security\SignedUrl;

use OCP\IConfig;
use Sabre\HTTP\RequestInterface;

class Verifier {

	/**
	 * @var RequestInterface
	 */
	private $request;
	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var \DateTime|null
	 */
	private $now;

	public function __construct(RequestInterface $request, IConfig $config, \DateTime $now = null) {
		$this->request = $request;
		$this->config = $config;
		$this->now = $now ?? new \DateTime();
	}

	public function isSignedRequest(): bool {
		$params = $this->getQueryParameters();
		return isset($params['OC-Signature']);
	}

	public function signedRequestIsValid(): bool {
		$params = $this->getQueryParameters();
		$urlSignature = $params['OC-Signature'];
		$urlCredential = $params['OC-Credential'];
		$urlDate = $params['OC-Date'];
		$urlExpires = $params['OC-Expires'];
		$urlVerb = $params['OC-Verb'];

		unset($params['OC-Signature']);

		$qp = \http_build_query($params);
		$url =  \Sabre\Uri\parse($this->getAbsoluteUrl());
		$url['query'] = $qp;
		$url = \Sabre\Uri\build($url);

		$signingKey = $this->config->getUserValue($urlCredential, 'core', 'signing-key');

		$hash = \hash_pbkdf2("sha512", $url, $signingKey, 10000, 64, false);
		if ($hash !== $urlSignature) {
			return false;
		}
		if (\strtoupper($this->getMethod()) !== \strtoupper($urlVerb)) {
			return false;
		}
		$date = new \DateTime($urlDate);
		$date->add(new \DateInterval("PT${urlExpires}S"));
		return !($date < $this->now);
	}

	private function getQueryParameters(): array {
		return $this->request->getQueryParameters();
	}

	public function getUserId(): string {
		$params = $this->getQueryParameters();
		return $params['OC-Credential'];
	}

	private function getAbsoluteUrl(): string {
		return $this->request->getAbsoluteUrl();
	}

	private function getMethod(): string {
		return $this->request->getMethod();
	}
}
