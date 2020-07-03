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

namespace Test\Security\SignedUrl;

use OC\Security\SignedUrl\Verifier;
use OCP\IConfig;
use Sabre\HTTP\Request;

class VerifierTest extends \Test\TestCase {

	/**
	 * @dataProvider providesUrls
	 */
	public function testSignedUrl($isSignedUrl, $isUrlValid, $method, $url) {
		$r = new Request($method, $url);
		$r->setAbsoluteUrl($url);
		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('1234567890');
		$v = new Verifier($r, $config, new \DateTime('2019-05-14T11:01:58.135Z'));
		if ($isSignedUrl) {
			$this->assertTrue($v->isSignedRequest());
			$this->assertEquals('alice', $v->getUserId());
			$this->assertEquals($isUrlValid, $v->signedRequestIsValid());
		} else {
			$this->assertFalse($v->isSignedRequest());
		}
	}

	public function providesUrls() {
		return [
			'valid url' => [true, true, 'get', 'http://cloud.example.net/?OC-Credential=alice&OC-Date=2019-05-14T11%3A01%3A58.135Z&OC-Expires=1200&OC-Verb=GET&OC-Signature=f9e53a1ee23caef10f72ec392c1b537317491b687bfdd224c782be197d9ca2b6'],
			'no signature' => [false, null, 'get', 'http://cloud.example.net/?OC-Credential=alice&OC-Date=2019-05-14T11%3A01%3A58.135Z&OC-Expires=1200&OC-Verb=GET'],
			'wrong method' => [true, false, 'post', 'http://cloud.example.net/?OC-Credential=alice&OC-Date=2019-05-14T11%3A01%3A58.135Z&OC-Expires=1200&OC-Verb=GET&OC-Signature=f9e53a1ee23caef10f72ec392c1b537317491b687bfdd224c782be197d9ca2b6'],
			'invalid signature' => [true, false, 'get', 'http://cloud.example.net/?OC-Credential=alice&OC-Date=2019-05-14T11%3A01%3A58.135Z&OC-Expires=1200&OC-Verb=GET&OC-Signature=f9e53a1ee23caef10f72ec392c1b537317491b687bfdd224c782be197d9ca2b'],
		];
	}
}
