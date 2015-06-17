<?php
/**
 * @author Arthur Schiwon <blizzz@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <schiessle@owncloud.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Robin McCorkell <rmccorkell@karoshi.org.uk>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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

namespace OC\Cache;

use OC\Files\Filesystem;
use OC\Files\View;
use OCP\Security\ISecureRandom;

class File {
	protected $storage;

	/**
	 * Returns the cache storage for the logged in user
	 *
	 * @return \OC\Files\View cache storage
	 * @throws \OC\ForbiddenException
	 * @throws \OC\User\NoUserException
	 */
	protected function getStorage() {
		if (isset($this->storage)) {
			return $this->storage;
		}
		if (\OC_User::isLoggedIn()) {
			$rootView = new View();
			$user = \OC::$server->getUserSession()->getUser();
			Filesystem::initMountPoints($user->getUID());
			if (!$rootView->file_exists('/' . $user->getUID() . '/cache')) {
				$rootView->mkdir('/' . $user->getUID() . '/cache');
			}
			$this->storage = new View('/' . $user->getUID() . '/cache');
			return $this->storage;
		} else {
			\OC_Log::write('core', 'Can\'t get cache storage, user not logged in', \OC_Log::ERROR);
			throw new \OC\ForbiddenException('Can\t get cache storage, user not logged in');
		}
	}

	/**
	 * @param string $key
	 * @return mixed|null
	 * @throws \OC\ForbiddenException
	 */
	public function get($key) {
		$result = null;
		if ($this->hasKey($key)) {
			$storage = $this->getStorage();
			$result = $storage->file_get_contents($key);
		}
		return $result;
	}

	/**
	 * Returns the size of the stored/cached data
	 *
	 * @param string $key
	 * @return int
	 */
	public function size($key) {
		$result = 0;
		if ($this->hasKey($key)) {
			$storage = $this->getStorage();
			$result = $storage->filesize($key);
		}
		return $result;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $ttl
	 * @return bool|mixed
	 * @throws \OC\ForbiddenException
	 */
	public function set($key, $value, $ttl = 0) {
		$storage = $this->getStorage();
		$result = false;
		// unique id to avoid chunk collision, just in case
		$uniqueId = \OC::$server->getSecureRandom()->getLowStrengthGenerator()->generate(
			16,
			ISecureRandom::CHAR_DIGITS . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER
		);

		// use part file to prevent hasKey() to find the key
		// while it is being written
		$keyPart = $key . '.' . $uniqueId . '.part';
		if ($storage and $storage->file_put_contents($keyPart, $value)) {
			if ($ttl === 0) {
				$ttl = 86400; // 60*60*24
			}
			$result = $storage->touch($keyPart, time() + $ttl);
			$result &= $storage->rename($keyPart, $key);
		}
		return $result;
	}

	/**
	 * @param string $key
	 * @return bool
	 * @throws \OC\ForbiddenException
	 */
	public function hasKey($key) {
		$storage = $this->getStorage();
		if ($storage && $storage->is_file($key) && $storage->isReadable($key)) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $key
	 * @return bool|mixed
	 * @throws \OC\ForbiddenException
	 */
	public function remove($key) {
		$storage = $this->getStorage();
		if (!$storage) {
			return false;
		}
		return $storage->unlink($key);
	}

	/**
	 * @param string $prefix
	 * @return bool
	 * @throws \OC\ForbiddenException
	 */
	public function clear($prefix = '') {
		$storage = $this->getStorage();
		if ($storage and $storage->is_dir('/')) {
			$dh = $storage->opendir('/');
			if (is_resource($dh)) {
				while (($file = readdir($dh)) !== false) {
					if ($file != '.' and $file != '..' and ($prefix === '' || strpos($file, $prefix) === 0)) {
						$storage->unlink('/' . $file);
					}
				}
			}
		}
		return true;
	}

	/**
	 * Runs GC
	 * @throws \OC\ForbiddenException
	 */
	public function gc() {
		$storage = $this->getStorage();
		if ($storage and $storage->is_dir('/')) {
			$now = time();
			$dh = $storage->opendir('/');
			if (!is_resource($dh)) {
				return null;
			}
			while (($file = readdir($dh)) !== false) {
				if ($file != '.' and $file != '..') {
					$mtime = $storage->filemtime('/' . $file);
					if ($mtime < $now) {
						$storage->unlink('/' . $file);
					}
				}
			}
		}
	}
}
