<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\User_SAML;

use OCP\IConfig;
use OCP\ISession;
use Sabre\DAV\CorePlugin;
use Sabre\DAV\FS\Directory;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Tree;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class DavPlugin extends ServerPlugin {
	private $session;
	private $config;
	private $auth;
	/** @var Server */
	private $server;

	public function __construct(ISession $session, IConfig $config, array $auth) {
		$this->session = $session;
		$this->config = $config;
		$this->auth = $auth;
	}


	public function initialize(Server $server) {
		// before auth
		$server->on('beforeMethod', [$this, 'beforeMethod'], 9);
		$this->server = $server;
	}

	public function beforeMethod(RequestInterface $request, ResponseInterface $response) {
		if (
			$this->config->getAppValue('user_saml', 'type') === 'environment-variable' &&
			!$this->session->exists('user_saml.samlUserData')
		) {
			$uidMapping = $this->config->getAppValue('user_saml', 'general-uid_mapping');
			if (isset($this->auth[$uidMapping])) {
				$this->session->set('user_saml.samlUserData', $this->auth);
			}
		}

		if ($request->getMethod() === 'OPTIONS' && $request->getPath() === '') {
			/** @var CorePlugin $corePlugin */
			$corePlugin = $this->server->getPlugin('core');
			// setup a fake tree for anonymous access
			$this->server->tree = new Tree(new Directory(''));
			$corePlugin->httpOptions($request, $response);
			$this->server->sapi->sendResponse($response);
			return false;
		}
	}
}
