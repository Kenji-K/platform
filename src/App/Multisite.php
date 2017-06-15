<?php
/**
 * Ushahidi Multisite
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

namespace Ushahidi\App;

use League\Url\Url;

class Multisite
{
	protected $db;
	protected $domain;
	protected $subdomain;

	public function __construct(\Ohanzee\Database $db)
	{
		$this->db = $db;
	}

	protected function parseHost($host)
	{
		if (!$this->domain && !$this->subdomain) {
			// Load the default domain
			$domain = Kohana::$config->load('multisite.domain');

			// If no host passed in, check the for HOST in environment
			if (!$host) {
				$host = getenv('HOST');
			}
			// If we still don't have a host
			if (! $host) {
				// .. parse the current URL
				$url = Url::createFromServer($_SERVER);
				// .. and grab the host
				$host = $url->getHost()->toUnicode();
			}

			// If $domain is set and we're at a subdomain of $domain..
			if ($domain AND substr($host, strlen($domain) * -1) == $domain) {
				// .. grab just the subdomain
				$subdomain = substr($host, 0, (strlen($domain) * -1) -1);
			} else {
				// .. otherwise grab the whole domain
				$domain = $host;
				$subdomain = '';
			}

			$this->domain = $domain;
			$this->subdomain = $subdomain;
		}
	}

	public function getDbConfig($host = NULL) {
		$this->parseHost($host);

		// .. and find the current deployment credentials
		$result = \Ohanzee\DB::select()->from('deployments')
			->where('subdomain', '=', $this->subdomain)
			->where('domain', '=', $this->domain)
			->limit(1)
			->offset(0)
			// @todo filter only active deployments?
			->execute($this->db);

		$deployment = $result->current();

		// No deployment? throw a 404
		if (! count($deployment)) {
			abort(404, "Deployment not found");
		}

		// Set new database config
		$config = Repository\OhanzeeRepository::getDefaultConfig();
		Kohana::$config->load('database')->default;
		$config['connection'] = [
			'hostname'   => $deployment['db_host'],
			'database'   => $deployment['db_name'],
			'username'   => $deployment['db_username'],
			'password'   => $deployment['db_password'],
			'persistent' => $config['connection']['persistent'],
		];

		// Check we can connect to the DB
		try {
			\Ohanzee\DB::select(\Ohanzee\DB::expr('1'))->from('users')
				->execute(\Ohanzee\Database::instance('deployment', $config));
		} catch (Exception $e) {
			// If we can't connect, throw 503 Service Unavailable
			abort(503, "Deployment not ready");
		}

		return $config;
	}

	public function getCdnPrefix($host = NULL)
	{
		$this->parseHost($host);

		return $this->subdomain . ($this->domain ? '.' . $this->domain : '');
	}

	public function getSite($host = NULL)
	{
		$this->parseHost($host);
		return $this->subdomain . ($this->domain ? '.' . $this->domain : '');
	}
}
