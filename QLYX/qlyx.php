<?php

class QLYX
{
	private PDO $pdo;
	private array $ignoredIps;
	private bool $anonymizeIp;
	private bool $autoCreateTable;
	private string $logFile;

	public function __construct(PDO $pdo, array $ignoredIps = [], bool $anonymizeIp = false, bool $autoCreateTable = true, string $logFile = 'qlyx_log.txt')
	{
		$this->pdo = $pdo;
		$this->ignoredIps = $ignoredIps;
		$this->anonymizeIp = $anonymizeIp;
		$this->autoCreateTable = $autoCreateTable;
		$this->logFile = $logFile;

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
		if ($this->isBot($userAgent)) {
			$this->log("Bot detected: $userAgent");
			return;
		}

		$geo = $this->getGeolocation($ip);
		$browser = $this->getBrowserInfo($userAgent);
		$deviceType = $this->getDeviceType($userAgent);
		$os = $this->getUserOs($userAgent);
		$user_profile = $this->generateUserProfile($ip, $userAgent, $deviceType, $os, $browser);
		$user_org = $this->getUserOrganization($ip);

		$ipToStore = $ip;

		$data = [
			'user_ip_address'     => $ipToStore,
			'user_profile'        => $user_profile,
			'user_org'            => $user_org,
			'user_browser_agent'  => $userAgent,
			'user_device_type'    => $deviceType,
			'user_os'             => $os,
			'user_city'           => $geo['city'] ?? 'Unknown',
			'user_region'         => $geo['region'] ?? 'Unknown',
			'user_country'        => $geo['country'] ?? 'Unknown',
			'browser_name'        => $browser['name'],
			'browser_version'     => $browser['version'],
			'browser_language'    => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown',
			'referring_url'       => $_SERVER['HTTP_REFERER'] ?? 'Direct',
			'page_url'            => $_SERVER['REQUEST_URI'] ?? 'Unknown',
			'timezone'            => $geo['timezone'] ?? 'Unknown',
			'visitor_type'        => 'HUMAN'
		];

		$this->insertData($data);
	}

	private function generateUserProfile($ip, $userAgent, $deviceType, $os, $browser): string
	{
		// generate a hash based on $ip, $userAgent, $deviceType, $os, $browser
		$user_profile = substr(hash('sha256', $ip . $userAgent . $deviceType . $os . $browser['name'] . $browser['version']), 0, 50);
		// Set this profile to a cookie or session if needed
		if (!isset($_COOKIE['qlyx_user_profile'])) {
			setcookie('qlyx_user_profile', $user_profile, time() + (86400 * 30), "/"); // 30 days
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
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
			)";
		$this->pdo->exec($query);
	}

	private function insertData(array $data): void
	{
		$sql = "
			INSERT INTO qlyx_analytics (
				user_ip_address, user_profile, user_org, user_browser_agent, user_device_type, user_os, user_city, 
				user_region, user_country, browser_name, browser_version, browser_language, 
				referring_url, page_url, timezone, visitor_type
			) VALUES (
				:user_ip_address, :user_profile, :user_org, :user_browser_agent, :user_device_type, :user_os, :user_city, 
				:user_region, :user_country, :browser_name, :browser_version, :browser_language, 
				:referring_url, :page_url, :timezone, :visitor_type
			)";
		$stmt = $this->pdo->prepare($sql);
		$stmt->execute($data);
	}

	private function getClientIp(): ?string
	{
		$ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
		return filter_var(explode(',', $ip)[0], FILTER_VALIDATE_IP);
	}

	private function isBot(string $agent): bool
	{
		if (empty($agent)) return true;

		return (bool) preg_match('/
			bot|crawl|slurp|spider|facebookexternalhit|facebot|pingdom|ia_archiver|
			twitterbot|linkedinbot|embedly|quora\ link\ preview|showyoubot|outbrain|
			pinterest|bitlybot|nuzzel|vkShare|W3C_Validator|redditbot|Applebot|
			WhatsApp|flipboard|tumblr|TelegramBot|Slackbot|discordbot|
			Googlebot|Bingbot|Yahoo! Slurp|DuckDuckBot|Baiduspider|YandexBot|
			Sogou|Exabot
		/ix', $agent);
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
			if (json_last_error() === JSON_ERROR_NONE) return $data;
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
		if (preg_match('/Mobile|Android/i', $agent)) return 'Mobile';
		if (preg_match('/Tablet/i', $agent)) return 'Tablet';
		return 'Desktop';
	}

	private function getUserOs(string $agent): string
	{
		if (preg_match('/windows/i', $agent)) return 'Windows';
		if (preg_match('/macintosh|mac os x/i', $agent)) return 'Mac OS';
		if (preg_match('/linux/i', $agent)) return 'Linux';
		if (preg_match('/android/i', $agent)) return 'Android';
		if (preg_match('/iphone/i', $agent)) return 'iPhone';
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
		$stmt = $this->pdo->prepare("
			SELECT DATE(created_at) as date, COUNT(*) as visits
			FROM qlyx_analytics
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
			GROUP BY DATE(created_at)
			ORDER BY DATE(created_at) ASC
		");
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	public function getStats(string $range = '24h'): array
	{
		$intervalMap = [
			'24h' => 'INTERVAL 1 DAY',
			'7d'  => 'INTERVAL 7 DAY',
			'1m'  => 'INTERVAL 1 MONTH',
			'1y'  => 'INTERVAL 1 YEAR'
		];

		$interval = $intervalMap[$range] ?? $intervalMap['24h'];

		$data = [];

		$data['total'] = $this->pdo->query("
			SELECT COUNT(*) FROM qlyx_analytics WHERE created_at >= NOW() - $interval
		")->fetchColumn();

		$data['by_device'] = $this->pdo->query("
			SELECT user_device_type, COUNT(*) as count 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			GROUP BY user_device_type
		")->fetchAll(PDO::FETCH_ASSOC);

		$data['by_browser'] = $this->pdo->query("
			SELECT browser_name, COUNT(*) as count 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			GROUP BY browser_name
		")->fetchAll(PDO::FETCH_ASSOC);

		$data['by_country'] = $this->pdo->query("
			SELECT user_country, COUNT(*) as count 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			GROUP BY user_country
			ORDER BY count DESC 
			LIMIT 5
		")->fetchAll(PDO::FETCH_ASSOC);

		$data['by_city'] = $this->pdo->query("
			SELECT user_city, COUNT(*) as count 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			GROUP BY user_city
			ORDER BY count DESC 
			LIMIT 5
		")->fetchAll(PDO::FETCH_ASSOC);

		$data['by_visitor_type'] = $this->pdo->query("
			SELECT visitor_type, COUNT(*) as count 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			GROUP BY visitor_type
		")->fetchAll(PDO::FETCH_ASSOC);

		$data['recent'] = $this->pdo->query("
			SELECT 
				COALESCE(user_ip_address, 'N/A') as user_ip_address, 
				COALESCE(user_profile, 'N/A') as user_profile,
				COALESCE(user_org, 'N/A') as user_org,
				COALESCE(user_device_type, 'N/A') as user_device_type, 
				COALESCE(browser_name, 'N/A') as browser_name, 
				COALESCE(user_country, 'N/A') as user_country, 
				COALESCE(user_city, 'N/A') as user_city, 
				COALESCE(user_region, 'N/A') as user_region,
				COALESCE(user_os, 'N/A') as user_os,
				COALESCE(browser_language, 'N/A') as browser_language,
				COALESCE(referring_url, 'N/A') as referring_url,
				COALESCE(page_url, 'N/A') as page_url,
				COALESCE(timezone, 'N/A') as timezone,
				COALESCE(visitor_type, 'N/A') as visitor_type,
				created_at 
			FROM qlyx_analytics 
			WHERE created_at >= NOW() - $interval
			ORDER BY created_at DESC 
			LIMIT 10
		")->fetchAll(PDO::FETCH_ASSOC);

		return $data;
	}
}
