<?php

class QLYX
{
	private PDO $pdo;
	private array $ignoredIps;
	private bool $anonymizeIp;
	private bool $autoCreateTable;
	private string $logFile;
	private array $config;
	private array $cache = [];

	public function __construct(PDO $pdo, array $ignoredIps = [], bool $anonymizeIp = false, bool $autoCreateTable = true, string $logFile = 'qlyx_log.txt', array $config = [])
	{
		$this->pdo = $pdo;
		$this->ignoredIps = $ignoredIps;
		$this->anonymizeIp = $anonymizeIp;
		$this->autoCreateTable = $autoCreateTable;
		$this->logFile = $logFile;
		$this->config = array_merge([
			'cache_duration' => 300, // 5 minutes
			'max_recent_visitors' => 100,
			'timezone' => 'UTC',
			'session_duration' => 1800, // 30 minutes
			'bot_detection_level' => 'normal', // normal, strict, or lenient
			'enable_geolocation' => true,
			'enable_organization_lookup' => true,
			'enable_session_tracking' => true,
			'enable_page_tracking' => true,
			'enable_referrer_tracking' => true,
			'enable_browser_tracking' => true,
			'enable_device_tracking' => true,
			'enable_os_tracking' => true,
			'enable_language_tracking' => true,
			'enable_timezone_tracking' => true,
			'enable_user_profile' => true,
			'enable_visitor_type' => true,
			'enable_visitor_country' => true,
			'enable_visitor_city' => true,
			'enable_visitor_region' => true,
			'enable_visitor_org' => true,
			'enable_visitor_browser' => true,
			'enable_visitor_device' => true,
			'enable_visitor_os' => true,
			'enable_visitor_language' => true,
			'enable_visitor_timezone' => true,
			'enable_visitor_referrer' => true,
			'enable_visitor_page' => true,
			'enable_visitor_session' => true,
			'enable_visitor_profile' => true
		], $config);

		if ($this->autoCreateTable) {
			$this->createTable();
		}
	}

	public function track(): void
	{
		$ip = $this->getClientIp();
		if (!$ip || in_array($ip, $this->ignoredIps)) {
			$this->log("Ignored IP: $ip");
			return;
		}

		$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
		$sessionId = $this->getSessionId();

		// Get visitor data based on enabled features
		$data = [
			'user_ip_address' => $this->anonymizeIp ? $this->anonymize($ip) : $ip,
			'session_id' => $sessionId,
			'created_at' => date('Y-m-d H:i:s')
		];

		if ($this->config['enable_geolocation']) {
			$geo = $this->getGeolocation($ip);
			if ($this->config['enable_visitor_country'])
				$data['user_country'] = $geo['country'] ?? 'Unknown';
			if ($this->config['enable_visitor_city'])
				$data['user_city'] = $geo['city'] ?? 'Unknown';
			if ($this->config['enable_visitor_region'])
				$data['user_region'] = $geo['region'] ?? 'Unknown';
			if ($this->config['enable_visitor_timezone'])
				$data['timezone'] = $geo['timezone'] ?? 'Unknown';
		}

		if ($this->config['enable_browser_tracking']) {
			$browser = $this->getBrowserInfo($userAgent);
			$data['browser_name'] = $browser['name'];
			$data['browser_version'] = $browser['version'];
		}

		if ($this->config['enable_device_tracking']) {
			$data['user_device_type'] = $this->getDeviceType($userAgent);
		}

		if ($this->config['enable_os_tracking']) {
			$data['user_os'] = $this->getUserOs($userAgent);
		}

		if ($this->config['enable_language_tracking']) {
			$data['browser_language'] = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
		}

		if ($this->config['enable_referrer_tracking']) {
			$data['referring_url'] = $_SERVER['HTTP_REFERER'] ?? 'Direct';
		}

		if ($this->config['enable_page_tracking']) {
			$data['page_url'] = $_SERVER['REQUEST_URI'] ?? 'Unknown';
		}

		if ($this->config['enable_organization_lookup']) {
			$data['user_org'] = $this->getUserOrganization($ip);
		}

		if ($this->config['enable_visitor_type']) {
			$data['visitor_type'] = $this->isBot($userAgent, $data['user_org'] ?? '') ? 'BOT' : 'HUMAN';
		}

		if ($this->config['enable_user_profile']) {
			$data['user_profile'] = $this->generateUserProfile($ip, $userAgent, $data['user_device_type'] ?? '', $data['user_os'] ?? '', $browser ?? ['name' => 'Unknown', 'version' => 'Unknown']);
		}

		$this->insertData($data);
		$this->updateSession($sessionId, $data);
	}

	private function getSessionId(): string
	{
		if (!$this->config['enable_session_tracking']) {
			return '';
		}

		if (isset($_COOKIE['qlyx_session'])) {
			return $_COOKIE['qlyx_session'];
		}

		$sessionId = bin2hex(random_bytes(16));
		if (!headers_sent()) {
			setcookie('qlyx_session', $sessionId, time() + $this->config['session_duration'], '/');
		}
		return $sessionId;
	}

	private function updateSession(string $sessionId, array $data): void
	{
		if (!$this->config['enable_session_tracking'] || empty($sessionId)) {
			return;
		}

		$stmt = $this->pdo->prepare("
			UPDATE qlyx_analytics 
			SET last_activity = NOW(),
				page_count = page_count + 1
			WHERE session_id = :session_id
			ORDER BY created_at DESC
			LIMIT 1
		");
		$stmt->execute(['session_id' => $sessionId]);
	}

	private function generateUserProfile($ip, $userAgent, $deviceType, $os, $browser): string
	{
		// Generate a hash based on user characteristics
		$user_profile = substr(hash('sha256', $ip . $userAgent . $deviceType . $os . $browser['name'] . $browser['version']), 0, 50);

		// Set this profile to a cookie if not already set
		if (!isset($_COOKIE['qlyx_user_profile']) && !headers_sent()) {
			setcookie('qlyx_user_profile', $user_profile, time() + 86400 * 30, "/"); // 30 days
		}

		return $user_profile;
	}

	private function createTable(): void
	{
		$query = "
			CREATE TABLE IF NOT EXISTS qlyx_analytics (
				id INT AUTO_INCREMENT PRIMARY KEY,
				user_ip_address VARCHAR(45),
				user_profile VARCHAR(50),
				user_org VARCHAR(100),
				user_browser_agent TEXT,
				user_device_type VARCHAR(50),
				user_os VARCHAR(100),
				user_city VARCHAR(100),
				user_region VARCHAR(100),
				user_country VARCHAR(100),
				browser_name VARCHAR(100),
				browser_version VARCHAR(100),
				browser_language VARCHAR(50),
				referring_url TEXT,
				page_url TEXT,
				timezone VARCHAR(100),
				visitor_type VARCHAR(20),
				session_id VARCHAR(32),
				page_count INT DEFAULT 1,
				last_activity TIMESTAMP,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_session (session_id),
				INDEX idx_created_at (created_at),
				INDEX idx_visitor_type (visitor_type),
				INDEX idx_country (user_country),
				INDEX idx_device (user_device_type),
				INDEX idx_browser (browser_name)
			)";
		$this->pdo->exec($query);
	}

	private function insertData(array $data): void
	{
		$sql = "
			INSERT INTO qlyx_analytics (
				user_ip_address, user_profile, user_org, user_browser_agent, 
				user_device_type, user_os, user_city, user_region, user_country, 
				browser_name, browser_version, browser_language, referring_url, 
				page_url, timezone, visitor_type, session_id, page_count
			) VALUES (
				:user_ip_address, :user_profile, :user_org, :user_browser_agent, 
				:user_device_type, :user_os, :user_city, :user_region, :user_country, 
				:browser_name, :browser_version, :browser_language, :referring_url, 
				:page_url, :timezone, :visitor_type, :session_id, :page_count
			)";

		// Ensure all required parameters are present
		$params = [
			'user_ip_address' => $data['user_ip_address'] ?? null,
			'user_profile' => $data['user_profile'] ?? null,
			'user_org' => $data['user_org'] ?? null,
			'user_browser_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
			'user_device_type' => $data['user_device_type'] ?? null,
			'user_os' => $data['user_os'] ?? null,
			'user_city' => $data['user_city'] ?? null,
			'user_region' => $data['user_region'] ?? null,
			'user_country' => $data['user_country'] ?? null,
			'browser_name' => $data['browser_name'] ?? null,
			'browser_version' => $data['browser_version'] ?? null,
			'browser_language' => $data['browser_language'] ?? null,
			'referring_url' => $data['referring_url'] ?? null,
			'page_url' => $data['page_url'] ?? null,
			'timezone' => $data['timezone'] ?? null,
			'visitor_type' => $data['visitor_type'] ?? null,
			'session_id' => $data['session_id'] ?? null,
			'page_count' => $data['page_count'] ?? 1
		];

		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($params);
	}

	private function getClientIp(): ?string
	{
		$ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
		return filter_var(explode(',', $ip)[0], FILTER_VALIDATE_IP);
	}

	private function isBot(string $agent, string $org = ''): bool
	{
		if (empty($agent)) {
			return true;
		}

		$agent = strtolower($agent);
		$org = strtolower($org);

		$botIdentifiers = [
			// Search engine bots
			'googlebot',
			'bingbot',
			'slurp',
			'yandexbot',
			'duckduckbot',
			'baiduspider',
			'sogou',
			'exabot',

			// Social media previews
			'facebookexternalhit',
			'facebot',
			'twitterbot',
			'linkedinbot',
			'slackbot',
			'discordbot',
			'telegrambot',

			// Crawlers/spiders
			'bot',
			'crawl',
			'crawler',
			'spider',
			'archive.org_bot',
			'ia_archiver',
			'redditbot',
			'showyoubot',
			'embedly',

			// Monitoring tools
			'uptime',
			'pingdom',
			'statuscake',
			'newrelicpinger',
			'site24x7',
			'checkly',

			// Headless/automation tools
			'headless',
			'phantomjs',
			'selenium',
			'puppeteer',
			'playwright',
			'chrome-lighthouse',

			// HTTP clients and libraries
			'python-requests',
			'python-urllib',
			'go-http-client',
			'java/',
			'okhttp',
			'curl',
			'wget',

			// Mobile preview apps
			'whatsapp',
			'flipboard',
			'tumblr',
			'nuzzel',
			'vkshare',
			'quora link preview',

			// Known suspicious user-agents
			'mozilla/5.0 (compatible;', // many basic bots use this format
		];

		$botOrgs = [
			'amazon',
			'google',
			'digitalocean',
			'linode',
			'microsoft',
			'facebook',
			'cloudflare',
			'hetzner',
			'ovh',
			'hostinger',
			'vultr',
			'contabo',
			'oracle',
			'gcore',
			'upcloud',
			'scaleway',
		];

		// Match by user agent
		foreach ($botIdentifiers as $botString) {
			if (strpos($agent, $botString) !== false) {
				return true;
			}
		}

		// Match by hosting org (if passed)
		foreach ($botOrgs as $orgName) {
			if ($org && strpos($org, $orgName) !== false) {
				return true;
			}
		}

		return false;
	}

	private function getGeolocation(string $ip): array
	{
		$url = "https://ipinfo.io/{$ip}/json";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode === 200 && $response) {
			$data = json_decode($response, true);
			if (json_last_error() === JSON_ERROR_NONE)
				return $data;
		}
		return [];
	}

	private function getBrowserInfo(string $agent): array
	{
		$browser = 'Unknown';
		$version = 'Unknown';

		if (preg_match('/Firefox\/([0-9.]+)/', $agent, $m)) {
			$browser = 'Firefox';
			$version = $m[1];
		} elseif (preg_match('/Chrome\/([0-9.]+)/', $agent, $m)) {
			$browser = 'Chrome';
			$version = $m[1];
		} elseif (preg_match('/Safari\/([0-9.]+)/', $agent, $m) && !preg_match('/Chrome/', $agent)) {
			$browser = 'Safari';
			$version = $m[1];
		} elseif (preg_match('/Trident\/([0-9.]+)/', $agent, $m)) {
			$browser = 'Internet Explorer';
			$version = $m[1];
		}

		return ['name' => $browser, 'version' => $version];
	}

	private function getDeviceType(string $agent): string
	{
		if (preg_match('/Mobile|Android/i', $agent))
			return 'Mobile';
		if (preg_match('/Tablet/i', $agent))
			return 'Tablet';
		return 'Desktop';
	}

	private function getUserOs(string $agent): string
	{
		if (preg_match('/windows/i', $agent))
			return 'Windows';
		if (preg_match('/macintosh|mac os x/i', $agent))
			return 'Mac OS';
		if (preg_match('/linux/i', $agent))
			return 'Linux';
		if (preg_match('/android/i', $agent))
			return 'Android';
		if (preg_match('/iphone/i', $agent))
			return 'iPhone';
		return 'Unknown OS';
	}

	private function getUserOrganization(string $ip): string
	{
		$url = "https://ipinfo.io/{$ip}/org";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 3);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'QLYXBot/1.0');
		$response = curl_exec($ch);
		curl_close($ch);
		if ($response && !empty($response)) {
			return trim($response);
		}
		return 'Unknown';
	}

	private function anonymize(string $ip): string
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			return preg_replace('/(:[a-fA-F0-9]{0,4}){4}$/', '::', $ip);
		}
		return preg_replace('/(\d+\.\d+\.\d+)\.\d+/', '$1.0', $ip);
	}

	private function log(string $msg): void
	{
		file_put_contents($this->logFile, "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
	}

	public function getDailyTrends(): array
	{
		$cacheKey = 'daily_trends';
		if (isset($this->cache[$cacheKey]) && (time() - $this->cache[$cacheKey]['time']) < $this->config['cache_duration']) {
			return $this->cache[$cacheKey]['data'];
		}

		$stmt = $this->pdo->prepare("
			SELECT 
				DATE(created_at) as date,
				COUNT(*) as visits,
				COUNT(DISTINCT session_id) as sessions,
				COUNT(DISTINCT user_ip_address) as unique_visitors,
				COUNT(DISTINCT CASE WHEN visitor_type = 'HUMAN' THEN user_ip_address END) as human_visitors,
				COUNT(DISTINCT CASE WHEN visitor_type = 'BOT' THEN user_ip_address END) as bot_visitors,
				AVG(page_count) as avg_pages_per_session,
				AVG(TIMESTAMPDIFF(MINUTE, created_at, last_activity)) as avg_session_duration
			FROM qlyx_analytics
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY DATE(created_at)
			ORDER BY DATE(created_at) ASC
		");
		$stmt->execute();
		$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$this->cache[$cacheKey] = [
			'time' => time(),
			'data' => $data
		];

		return $data;
	}

	public function getStats(string $range = '24h'): array
	{
		$cacheKey = "stats_{$range}";
		if (isset($this->cache[$cacheKey]) && (time() - $this->cache[$cacheKey]['time']) < $this->config['cache_duration']) {
			return $this->cache[$cacheKey]['data'];
		}

		$intervalMap = [
			'24h' => '1 DAY',
			'7d' => '7 DAY',
			'1m' => '1 MONTH',
			'1y' => '1 YEAR'
		];

		$interval = $intervalMap[$range] ?? $intervalMap['24h'];
		$data = $this->initializeStatsData();

		try {
			$where = "created_at >= DATE_SUB(NOW(), INTERVAL $interval)";

			// Get total visitors and visitor type counts
			$stmt = $this->pdo->prepare("
				SELECT 
					COUNT(*) as total,
					SUM(CASE WHEN visitor_type = 'HUMAN' THEN 1 ELSE 0 END) as human_count,
					SUM(CASE WHEN visitor_type = 'BOT' THEN 1 ELSE 0 END) as bot_count
				FROM qlyx_analytics 
				WHERE $where
			");
			$stmt->execute();
			$counts = $stmt->fetch(PDO::FETCH_ASSOC);

			$data['total'] = (int) $counts['total'];
			$data['by_visitor_type'] = [
				['visitor_type' => 'HUMAN', 'count' => (int) $counts['human_count']],
				['visitor_type' => 'BOT', 'count' => (int) $counts['bot_count']]
			];

			// Get session statistics
			if ($this->config['enable_session_tracking']) {
				$stmt = $this->pdo->prepare("
					SELECT 
						COUNT(DISTINCT session_id) as total_sessions,
						AVG(page_count) as avg_pages,
						AVG(TIMESTAMPDIFF(MINUTE, created_at, last_activity)) as avg_duration
					FROM qlyx_analytics 
					WHERE $where AND session_id IS NOT NULL
				");
				$stmt->execute();
				$sessionStats = $stmt->fetch(PDO::FETCH_ASSOC);
				$data['sessions'] = [
					'total' => (int) $sessionStats['total_sessions'],
					'average_pages' => round($sessionStats['avg_pages'], 1),
					'average_duration' => round($sessionStats['avg_duration'], 1)
				];
			}

			// Get device distribution
			$stmt = $this->pdo->prepare("
				SELECT user_device_type, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where AND user_device_type IS NOT NULL
				GROUP BY user_device_type
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_device'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get browser distribution
			$stmt = $this->pdo->prepare("
				SELECT browser_name, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where AND browser_name IS NOT NULL
				GROUP BY browser_name
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_browser'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get country distribution
			$stmt = $this->pdo->prepare("
				SELECT user_country, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where AND user_country IS NOT NULL
				GROUP BY user_country
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_country'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get city distribution
			$stmt = $this->pdo->prepare("
				SELECT user_city, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where AND user_city IS NOT NULL
				GROUP BY user_city
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_city'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get OS distribution
			$stmt = $this->pdo->prepare("
				SELECT user_os, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where
				GROUP BY user_os
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_os'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get language distribution
			$stmt = $this->pdo->prepare("
				SELECT browser_language, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where
				GROUP BY browser_language
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_language'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get timezone distribution
			$stmt = $this->pdo->prepare("
				SELECT timezone, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where
				GROUP BY timezone
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_timezone'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get organization distribution
			$stmt = $this->pdo->prepare("
				SELECT user_org, COUNT(*) as count 
				FROM qlyx_analytics 
				WHERE $where
				GROUP BY user_org
				ORDER BY count DESC
			");
			$stmt->execute();
			$data['by_org'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Get recent visitors with all details
			$stmt = $this->pdo->prepare("
				SELECT 
					user_ip_address,
					user_profile,
					user_org,
					user_device_type,
					browser_name,
					user_country,
					user_city,
					user_os,
					browser_language,
					timezone,
					visitor_type,
					created_at,
					page_count,
					TIMESTAMPDIFF(MINUTE, created_at, last_activity) as session_duration
				FROM qlyx_analytics 
				WHERE $where
				ORDER BY created_at DESC
				LIMIT :limit
			");
			$stmt->bindValue(':limit', (int) $this->config['max_recent_visitors'], PDO::PARAM_INT);
			$stmt->execute();
			$recentVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

			// Only set recent visitors if we actually have data
			if (!empty($recentVisitors)) {
				$data['recent'] = $recentVisitors;
			}

			$this->cache[$cacheKey] = [
				'time' => time(),
				'data' => $data
			];
		} catch (PDOException $e) {
			$this->log("Error in getStats: " . $e->getMessage());
		}
		$this->log("getStats data being returned: " . json_encode(['recent' => $data['recent'], 'by_os' => $data['by_os'], 'by_city' => $data['by_city']]));
		return $data;
	}

	private function initializeStatsData(): array
	{
		return [
			'total' => 0,
			'by_device' => [],
			'by_browser' => [],
			'by_country' => [],
			'by_city' => [],
			'by_visitor_type' => [],
			'by_os' => [],
			'by_language' => [],
			'by_timezone' => [],
			'by_org' => [],
			'by_session' => [],
			'recent' => [],
			'sessions' => [
				'total' => 0,
				'average_pages' => 0,
				'average_duration' => 0
			]
		];
	}

	public function clearCache(): void
	{
		$this->cache = [];
	}
}