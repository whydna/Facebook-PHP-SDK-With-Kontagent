<?php

// Kontagent's wrapper around Facebook's 3.0 SDK. Overrides methods to 
// automatically send the appropriate tracking messages to Kontagent.
class KontagentFacebook extends Facebook
{
	// Reference to Kontagent's API wrapper object
	public $ktApi = null;
	
	// Reference to Kontagent's Landing Tracker pbkec
	private $ktLandingTracker = null;
	
	public function __construct($config, $ktConfig)
	{
		parent::__construct(array(
			'appId' => $config['appId'], 
			'secret' => $config['secret'])
		);
		
		// instantiate the Kontagent Api object
		$this->ktApi = new KontagentApi($ktConfig['apiKey'], array(
			'useTestServer' => ($ktConfig['useTestServer']) ? $ktConfig['useTestServer'] : false,
			'validateParams' => false
		));
		
		$this->ktLandingTracker = new KontagentLandingTracker($this);
		$this->ktLandingTracker->trackLanding();
	}

	public function getKontagentApi()
	{
		return $this->ktApi;
	}
	
	// Overrides the parent method, returns the current URL adding the ability 
	// to strip KT tracking variables.
	protected function getCurrentUrl($stripKtVars = false) 
	{
		$currentUrl = parent::getCurrentUrl();
		return ($stripKtVars) ? KontagentUtils::stripKtVarsFromUrl($currentUrl) : $currentUrl;
	}
	
	// Returns the Feed Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation).
	public function getLoginUrl($params = array()) 
	{
		$params['redirect_uri'] = KontagentUtils::appendVarsToUrl(
			(isset($params['redirect_uri'])) ? $params['redirect_uri'] : $this->getCurrentUrl(true),
			array(
				'kt_track_apa' => 1,
				'kt_u' =>  (isset($_GET['kt_u'])) ? $_GET['kt_u'] : null,
				'kt_su' => (isset($_GET['kt_su'])) ? $_GET['kt_su'] : null
			)
		);
		
		return parent::getLoginUrl($params);
	}
	
	// Returns the Logout url. This method takes in the parameters defined by Facebook
	// (see FB documentation).
	public function getLogoutUrl($params = array()) 
	{			
		return parent::getLogoutUrl(array_merge(
			array('next' => $this->getCurrentUrl(true)),
			$params
		));
	}
	
	// Returns the Feed Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation) as well as 'subtype1', 'subtype2', 'subtype3' values.
	public function getFeedDialogUrl($params = array())
	{
		$uniqueTrackingTag = $this->ktApi->genUniqueTrackingTag();
		
		// define the URL to redirect the sender
		$params['redirect_uri'] = KontagentUtils::appendVarsToUrl(
			(isset($params['redirect_uri'])) ? $params['redirect_uri'] : $this->getCurrentUrl(true),
			array(
				'kt_track_pst' => 1,
				'kt_u' =>  $uniqueTrackingTag,
				'kt_st1' => (isset($params['subtype1'])) ? $params['subtype1'] : null,
				'kt_st2' => (isset($params['subtype2'])) ? $params['subtype2'] : null,
				'kt_st3' => (isset($params['subtype3'])) ? $params['subtype3'] : null
			)
		);
		
		// We replace the $params['link'] with Kontagent's redirect landing page
		// which will track the PSR and then redirect them to the real landing url.
		if (isset($params['link'])) {
			$params['link'] = KontagentUtils::appendVarsToUrl(
				$params['link'],
				array(
					'kt_track_psr' => 1,
					'kt_u' =>  $uniqueTrackingTag,
					'kt_st1' => (isset($params['subtype1'])) ? $params['subtype1'] : null,
					'kt_st2' => (isset($params['subtype2'])) ? $params['subtype2'] : null,
					'kt_st3' => (isset($params['subtype3'])) ? $params['subtype3'] : null
				)
			);
		}

		if (isset($params['actions'])) {
			for($i=0; $i<sizeof($params['actions']); $i++) {
				if (isset($params['actions'][$i]['link'])) {
					$params['actions'][$i]['link'] = KontagentUtils::appendVarsToUrl(
						$params['actions'][$i]['link'],
						array(
							'kt_track_psr' => 1,
							'kt_u' =>  $uniqueTrackingTag,
							'kt_st1' => (isset($params['subtype1'])) ? $params['subtype1'] : null,
							'kt_st2' => (isset($params['subtype2'])) ? $params['subtype2'] : null,
							'kt_st3' => (isset($params['subtype3'])) ? $params['subtype3'] : null
						)
					);
				}
			}
		}

		return $this->getUrl( 
			'www',
			'dialog/feed',
			array_merge(
				array('app_id' => $this->getAppId()),
				$params
			)
		);
	}
	
	// Returns the Friends Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation).
	public function getFriendsDialogUrl($params = array())
	{
		return $this->getUrl( 
			'www',
			'dialog/friends',
			array_merge(
				array(
					'app_id' => $this->getAppId(),
					'redirect_uri' => $this->getCurrentUrl(true)
				),
				$params
			)
		);
	}
	
	// Returns the OAuth Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation).
	public function getOAuthDialogUrl($params = array())
	{
		$params['redirect_uri'] = KontagentUtils::appendVarsToUrl(
			(isset($params['redirect_uri'])) ? $params['redirect_uri'] : $this->getCurrentUrl(true),
			array(
				'kt_track_apa' => 1,
				'kt_u' =>  (isset($_GET['kt_u'])) ? $_GET['kt_u'] : null,
				'kt_su' =>  (isset($_GET['kt_su'])) ? $_GET['kt_su'] : null
			)
		);
	
		return $this->getUrl( 
			'www',
			'dialog/oauth',
			array_merge(
				array('client_id' => $this->getAppId()),
				$params
			)
		);
	}
	
	// Returns the Pay Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation).echo '<script
	public function getPayDialogUrl($params = array())
	{
		return $this->getUrl( 
			'www',
			'dialog/pay',
			array_merge(
				array(
					'app_id' => $this->getAppId(),
					'redirect_uri' => $this->getCurrentUrl(true)
				),
				$params
			)
		);
	}
	
	// Returns the Requests Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation) as well as 'subtype1', 'subtype2', 'subtype3' values.
	public function getRequestsDialogUrl($params = array())
	{
		$uniqueTrackingTag = $this->ktApi->genUniqueTrackingTag();
		
		$params['redirect_uri'] = KontagentUtils::appendVarsToUrl(
			 (isset($params['redirect_uri'])) ? $params['redirect_uri'] : $this->getCurrentUrl(true),
			array(
				'kt_track_ins' => 1,
				'kt_u' => $uniqueTrackingTag,
				'kt_st1' => (isset($params['subtype1'])) ? $params['subtype1'] : null,
				'kt_st2' => (isset($params['subtype2'])) ? $params['subtype2'] : null,
				'kt_st3' => (isset($params['subtype3'])) ? $params['subtype3'] : null
			)
		);
		
		// append Kontagent tracking parameters to the data field
		$params['data'] = KontagentUtils::appendKtVarsToDataField(
			isset($params['data']) ? $params['data'] : '',
			array(
				'kt_u' => $uniqueTrackingTag,
				'kt_st1' => (isset($params['subtype1'])) ? $params['subtype1'] : null,
				'kt_st2' => (isset($params['subtype2'])) ? $params['subtype2'] : null,
				'kt_st3' => (isset($params['subtype3'])) ? $params['subtype3'] : null
			)
		);
	
		return $this->getUrl( 
			'www',
			'dialog/apprequests',
			array_merge(
				array('app_id' => $this->getAppId()),
				$params
			)
		);
	}
	
	// Returns the Send Dialog url. This method takes in the parameters defined by Facebook
	// (see FB documentation).
	public function getSendDialogUrl($params = array())
	{		
		return $this->getUrl( 
			'www',
			'dialog/send',
			array_merge(
				array('app_id' => $this->getAppId()),
				$params
			)
		);
	}
}

////////////////////////////////////////////////////////////////////////////////

class KontagentUtils
{
	// KT tracking variable names that are used to pass Kontagent values around.
	private static $ktVars = array(
		'kt_track_apa',
		'kt_track_pst',
		'kt_track_psr',
		'kt_track_ins',
		'kt_track_inr',
		'kt_track_mes',
		'kt_track_mer',
		'kt_track_ucc',
		'kt_u',
		'kt_su',
		'kt_st1',
		'kt_st2',
		'kt_st3',
		'kt_r',
		'kt_type'
	);
	
	// Appends KT tracking parameters to the data field of the Requests Dialog
	// (see FB documentation for details).
	public static function appendKtVarsToDataField($dataString, $vars = array()) 
	{
		// Data will be stored in the following format:
		// data = "<original_data>|<kontagent_data>"
	
		$dataString .= '|';
		
		foreach($vars as $key => $val) {
			if (isset($val)) {
				$dataString .= $key . '=' . $val . '&';
			}
		}
		
		// remove trailing ampersand
		return self::removeTrailingAmpersand($dataString);
	}
			
	// Strips the Kontagent data and returns a string containing only the original data.
	public static function stripKtVarsFromDataField($dataString)
	{
		list($otherDataString, $ktDataString) = explode('|', $dataString);
		
		return $otherDataString;
	}
	
	// Strips the original data and returns a string containing only the Kontagent data.
	public static function extractKtVarsFromDataField($dataString)
	{
		list($otherDataString, $ktDataString) = explode('|', $dataString);
		
		parse_str($ktDataString, $ktDataVars);
		
		return $ktDataVars;
	}
	
	// Appends variables to a given URL. $vars should be an associative array
	// in the form: var_name => var_value
	public static function appendVarsToUrl($url, $vars = array()) 
	{
		if (strstr($url, '?') === false) {
			$url .= '?';
		} else {
			$url .= '&';
		}
	
		foreach($vars as $key => $val) {
			if (isset($val)) {
				$url .= $key . '=' . $val . '&';
			}

		}
		
		// remove trailing ampersand
		return self::removeTrailingAmpersand($url);
	}
	
	// Cleans a given URL of KT tracking parameters.
	public static function stripKtVarsFromUrl($url) 
	{
		$parts = parse_url($url);
		
		if (empty($parts['query'])) {
			return $url;
		}
		
		$vars = explode('&', $parts['query']);
		$retainedVars = array();
				
		foreach ($vars as $var) {
			list ($key, $val) = explode('=', $var);
			
			if (!in_array($key, self::$ktVars)) {
				$retainedVars[] = $var;
			}
		}

		$query = '';

		if (!empty($retainedVars)) {
			$query = '?' . implode($retainedVars, '&');
		}
		
		$port = (isset($parts['port'])) ? ':' . $parts['port'] : '';
		
		return $parts['scheme'] . '://' . $parts['host'] . $port . $parts['path'] . $query;
	}
	
	// Takes in a URL and returns an associative array containing the KT tracking parameters.
	public static function extractKtVarsFromUrl($url)
	{
		$ktUrlVars = array();
	
		$parts = parse_url($url);
		
		if (empty($parts['query'])) {
			return $ktUrlVars;
		}
		
		$vars = explode('&', $parts['query']);
		
		foreach ($vars as $var) {
			list ($key, $val) = explode('=', $var);
			
			if (in_array($key, self::$ktVars)) {
				$ktUrlVars[$key] = $val;
			}
		}
		
		return $ktUrlVars;
	}

	public static function removeTrailingAmpersand($string)
	{
		if (substr($string, -1) == '&') {
			return substr($string, 0, -1);
		} else {
			return $string;
		}
	}
	
	public static function removeTrailingComma($string)
	{
		if (substr($string, -1) == ',') {
			return substr($string, 0, -1);
		} else {
			return $string;
		}
	}
    
        public static function setKtInstalledSession()
	{
		$_SESSION['kt_installed'] = true;
	}
	
	public static function unsetKtInstalledSession()
	{
		unset($_SESSION['kt_installed']);
	}
	
	public static function isKtInstalledSessionSet()
	{
		if (isset($_SESSION['kt_installed']) && $_SESSION['kt_installed'] == true) {
			return true;
		} else {
			return false;
		}
	}
	
	public static function redirect($url) 
	{
		header("location:" . $url);
		exit();
	}
}

class KontagentLandingTracker
{
	private $ktFacebook = null;
	
	public function __construct(KontagentFacebook $ktFacebook)
	{
		$this->ktFacebook = $ktFacebook;
	}
	
	public function trackLanding()
	{		
		// Notice the INR, PSR, and UCC methods return the consumed
		// unique tracking tags.
		$uniqueTrackingTag = null;
		$shortUniqueTrackingTag = null;
		
		if ($this->shouldTrackPgr()) {
			$this->trackPgr();
		}
		
		if ($this->shouldTrackIns()) {
			$this->trackIns();
		} else if ($this->shouldTrackInr()) {
			$uniqueTrackingTag = $this->trackInr();
		} else if ($this->shouldTrackPst()) {
			$this->trackPst();
		} else if ($this->shouldTrackPsr()) {
			$uniqueTrackingTag = $this->trackPsr();
		} else if ($this->shouldTrackUcc()) {
			$shortUniqueTrackingTag = $this->trackUcc();
		}
		
		// Store the consumed tracking tags in the $_GET 
		// variable because this is where the $this->trackApa() and
		// KontagentFacebook->getLoginUrl() method looks for it.
		if ($uniqueTrackingTag) {
			$_GET['kt_u'] = $uniqueTrackingTag;
		} else if ($shortUniqueTrackingTag) {
			$_GET['kt_su'] = $shortUniqueTrackingTag;
		}

		if ($this->shouldTrackApa()) {
			$this->trackApa();
			$this->trackCpu();
			$this->trackSpruceInstall();
		}
	}

	private function trackPgr()
	{
		$this->ktFacebook->getKontagentApi()->trackPageRequest($this->ktFacebook->getUser());
	}
	
	private function trackApa()
	{
		$this->ktFacebook->getKontagentApi()->trackApplicationAdded(
			$this->ktFacebook->getUser(), 
			array(
				'uniqueTrackingTag' => isset($_GET['kt_u']) ? $_GET['kt_u'] : null,
				'shortUniqueTrackingTag' => isset($_GET['kt_su']) ? $_GET['kt_su'] : null,
			)
		);
				
		KontagentUtils::setKtInstalledSession();
	}
	
	private function trackSpruceInstall()
	{		
		// Spruce Media Ad Tracking  
		if (isset($_GET['spruce_adid'])) {
			$spruceUrl = 'http://bp-pixel.socialcash.com/100480/pixel.ssps';
			$spruceUrl .= '?spruce_adid=' . $_GET["spruce_adid"];
			$spruceUrl .= '&spruce_sid=' . $this->ktFacebook->getKontagentApi()->genShortUniqueTrackingTag();

			$ktFacebook->getKontagentApi()->sendHttpRequest($spruceUrl);
		}
	}
	
	private function trackCpu()
	{
		// track the user information
		$gender = null;
		$birthYear = null;
		$friendCount = null;
		
		// attempt to retrieve user data from FB api
		try {
			$userInfo = $this->ktFacebook->api('/me');
			$userFriendsInfo = $this->ktFacebook->api('/me/friends');
			
			$gender = substr($userInfo['gender'], 0, 1);
			
			if (isset($userInfo['birthday'])) {
				$birthdayPieces = explode('/', $userInfo['birthday']);
				
				if (sizeof($birthdayPieces) == 3) {
					$birthYear = $birthdayPieces[2];
				}
			}
			
			$friendCount = sizeof($userFriendsInfo['data']);
		} catch (FacebookApiException $e) { }
		
		$this->ktFacebook->getKontagentApi()->trackUserInformation($this->ktFacebook->getUser(), array(
			'gender' => (isset($gender)) ? $gender : null,
			'birthYear' => (isset($birthYear)) ? $birthYear : null,
			'friendCount' => (isset($friendCount)) ? $friendCount : null
		));
	}
	
	private function trackIns()
	{
		$recipientUserIds = '';

		if (isset($_GET['request_ids']) && is_array($_GET['request_ids'])) {
			// Non-efficient Requests, we need to make an extra call to get the uids
			$requests = $this->ktFacebook->api('/', array('ids'=>$requestIds));

			foreach($requests as $request) {
				$recipientUserIds .= $request['to']['id'] . ',';
			}
	
			$recipientUserIds = KontagentUtils::removeTrailingComma($recipientUserIds);
		} else if (isset($_GET['request']) && isset($_GET['to']) && is_array($_GET['to'])) {
			// Request 2.0 Efficient mode, we have direct access to recipient uids
			$recipientUserIds = implode(',', $_GET['to']);
		}
	
		$this->ktFacebook->getKontagentApi()->trackInviteSent(
			$this->ktFacebook->getUser(),
			$recipientUserIds,
			$_GET['kt_u'], 
			array(
				'subtype1' => (isset($_GET['kt_st1'])) ? $_GET['kt_st1'] : null,
				'subtype2' => (isset($_GET['kt_st2'])) ? $_GET['kt_st2'] : null,
				'subtype3' => (isset($_GET['kt_st3'])) ? $_GET['kt_st3'] : null
			)
		);
	}
	
	private function trackInr()
	{
		try {
			// User may be responding to more than 1 request. We take the latest one.
			$requestIds = explode(',', $_GET['request_ids']);
			$requestId = $requestIds[sizeof($requestIds)-1];
			$request = $this->ktFacebook->api('/' . $requestId);
		
			// extract parameters that was stored in the data field
			// (kt_u, kt_st1, kt_st2, kt_st3)
			$ktDataVars = KontagentUtils::extractKtVarsFromDataField($request['data']);
		
			if (isset($request['to']['id'])) {
			    $recipientUserId = $request['to']['id'];
			} elseif ($this->ktFacebook->getUser()) { 
			    $recipientUserId = $this->ktFacebook->getUser();
			} else {
			    $recipientUserId = null;
			}

			$this->ktFacebook->getKontagentApi()->trackInviteResponse($ktDataVars['kt_u'], array(
				'recipientUserId' => $recipientUserId,
				'subtype1' => (isset($ktDataVars['kt_st1'])) ? $ktDataVars['kt_st1'] : null,
				'subtype2' => (isset($ktDataVars['kt_st2'])) ? $ktDataVars['kt_st2'] : null,
				'subtype3' => (isset($ktDataVars['kt_st3'])) ? $ktDataVars['kt_st3'] : null
			));
			
			return $ktDataVars['kt_u'];
		} catch (FacebookApiException $e) { }
	}
	
	private function trackPst()
	{
		$this->ktFacebook->getKontagentApi()->trackStreamPost($this->ktFacebook->getUser(), $_GET['kt_u'], 'stream', array(
			'subtype1' => (isset($_GET['kt_st1'])) ? $_GET['kt_st1'] : null,
			'subtype2' => (isset($_GET['kt_st2'])) ? $_GET['kt_st2'] : null,
			'subtype3' => (isset($_GET['kt_st3'])) ? $_GET['kt_st3'] : null
		));
	}
	
	private function trackPsr()
	{
		$this->ktFacebook->getKontagentApi()->trackStreamPostResponse($_GET['kt_u'], 'stream', array(
			'recipientUserId' => ($this->ktFacebook->getUser()) ? $this->ktFacebook->getUser() : null,
			'subtype1' => (isset($_GET['kt_st1'])) ? $_GET['kt_st1'] : null,
			'subtype2' => (isset($_GET['kt_st2'])) ? $_GET['kt_st2'] : null,
			'subtype3' => (isset($_GET['kt_st3'])) ? $_GET['kt_st3'] : null
		));
		
		return $_GET['kt_u'];
	}
	
	private function trackUcc()
	{
		$shortUniqueTrackingTag = $this->ktFacebook->getKontagentApi()->genShortUniqueTrackingTag();

		$this->ktFacebook->getKontagentApi()->trackThirdPartyCommClick($_GET['kt_type'], array(
			'shortUniqueTrackingTag' => $shortUniqueTrackingTag,
			'userId' => ($this->ktFacebook->getUser()) ? $this->ktFacebook->getUser() : null,
			'subtype1' => isset($_GET['kt_st1']) ? $_GET['kt_st1'] : null,
			'subtype2' => isset($_GET['kt_st2']) ? $_GET['kt_st2'] : null,
			'subtype3' => isset($_GET['kt_st3']) ? $_GET['kt_st3'] : null
		));
		
		return $shortUniqueTrackingTag;
	}
	
	private function shouldTrackPgr()
	{
		if ($this->ktFacebook->getUser()) {
			return true;
		}
		 
		return false;
	}
	
	private function shouldTrackApa()
	{
		if ($this->ktFacebook->getUser() && isset($_GET['kt_track_apa'])) {
			return true;
		}
		
		// If the user authenticated via auth referrals (as opposed to an
		// explicit login request from the app), the $_GET['kt_track_apa']
		// will not be present. This is why we have this check.
		if ($this->ktFacebook->getUser() && !KontagentUtils::isKtInstalledSessionSet()) { 
			return true;
		}
		
		return false;
	}
	
	private function shouldTrackIns()
	{
		if ($this->ktFacebook->getUser() && isset($_GET['kt_track_ins']) && isset($_GET['kt_u'])) {
			if (isset($_GET['request_ids']) && is_array($_GET['request_ids'])) {
				// Non-efficient Requests
				return true;
			} else if (isset($_GET['request']) && isset($_GET['to']) && is_array($_GET['to'])) {
				// Efficient Requests enabled
				return true;
			}
		}
		
		return false;
	}
	
	private function shouldTrackInr()
	{
		// due to new facebook change, we can only track INR after the
		// user has authenticated
		if ($this->ktFacebook->getUser() && isset($_GET['request_ids']) && !is_array($_GET['request_ids']))
			//&& sizeof($this->extractKtVarsFromUrl($this->getCurrentUrl(false))) == 0) 
		{
			return true;
		}
		
		return false;
	}
	
	private function shouldTrackPst()
	{
		if ($this->ktFacebook->getUser() && isset($_GET['kt_track_pst']) && isset($_GET['post_id']) && isset($_GET['kt_u'])) {
			return true;
		}
		
		return false;
	}
	
	private function shouldTrackPsr()
	{
		if (isset($_GET['kt_track_psr']) && isset($_GET['kt_u'])) {
			return true;
		}
		
		return false;
	}
	
	private function shouldTrackUcc()
	{
		if(isset($_GET['kt_track_ucc']) && isset($_GET['kt_type'])) {
			return true;
		}
		
		return false;
	}
}

////////////////////////////////////////////////////////////////////////////////

class KontagentApi {
	private $sdkVersion = "p00";

	private $baseApiUrl = "http://api.geo.kontagent.net/api/v1/";
	private $baseTestServerUrl = "http://test-server.kontagent.com/api/v1/";
	
	private $apiKey = null;
	private $validateParams = null;

	private $useTestServer = null;
	
	private $useCurl = null;

	/*
	* Kontagent class constructor
	*
	* @param string $apiKey The app's Kontagent API key
	* @param array $optionalParams An associative array containing paramName => value
	* @param bool $optionalParams['useTestServer'] Whether to send messages to the Kontagent Test Server
	* @param bool $optionalParams['validateParams'] Whether to validate the parameters passed into the tracking methods
	*/
	public function __construct($apiKey, $optionalParams = array()) {
		$this->apiKey = $apiKey;
		$this->useTestServer = ($optionalParams['useTestServer']) ? $optionalParams['useTestServer'] : false;
		$this->validateParams = ($optionalParams['validateParams']) ? $optionalParams['validateParams'] : false;
		
		// determine whether curl is installed on the server
		$this->useCurl = (function_exists('curl_init')) ? true : false;
	}

	/*
	* Sends an HTTP request given a URL
	*
	* @param string $url The message type to send ('apa', 'ins', etc.)
	*/
	public function sendHttpRequest($url) {
		// use curl if available, otherwise use file_get_contents() to send the request
		if ($this->useCurl) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
		} else {
			file_get_contents($url);
		}
	}

	/*
	* Sends the API message.
	*
	* @param string $messageType The message type to send ('apa', 'ins', etc.)
	* @param array $params An associative array containing paramName => value (ex: 's'=>123456789)
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function sendMessage($messageType, $params, &$validationErrorMsg = null) {
		// append the version
		$params['sdk'] = $this->sdkVersion;

		if ($this->validateParams) {
			// validate the message parameters
			$validationErrorMsg = null;
			
			foreach($params as $paramName => $paramValue) {
				if (!KtValidator::validateParameter($messageType, $paramName, $paramValue, $validationErrorMsg)) {
					return false;
				}
			}
			if (!KtValidator::validateSubtypes($params, $validationErrorMsg)) {
				return false;
			}
		}
	
		// generate URL of the API request
		$url = null;
		
		if ($this->useTestServer) {
			$url = $this->baseTestServerUrl . $this->apiKey . "/" . $messageType . "/?" . http_build_query($params, '', '&');
		} else {
			$url = $this->baseApiUrl . $this->apiKey . "/" . $messageType . "/?" . http_build_query($params, '', '&');
		}
		
		$this->sendHttpRequest($url);

		return true;
	}
	
	/*
	* Generates a unique tracking tag.
	*
	* @return string The unique tracking tag
	*/
	public function genUniqueTrackingTag() {
		return substr(md5(uniqid(rand(), true)), -16);
	}
	
	/*
	* Generates a short unique tracking tag.
	*
	* @return string The short unique tracking tag
	*/
	public function genShortUniqueTrackingTag() {
		return substr(md5(uniqid(rand(), true)), -8);
	}
	
	/*
	* Sends an Invite Sent message to Kontagent.
	*
	* @param int $userId The UID of the sending user
	* @param string $recipientUserIds A comma-separated list of the recipient UIDs
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	InviteSent->InviteResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackInviteSent($userId, $recipientUserIds, $uniqueTrackingTag, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'r' => $recipientUserIds,
			'u' => $uniqueTrackingTag
		);
		
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
			
		return $this->sendMessage("ins", $params, $validationErrorMsg);
	}
	
	/*
	* Sends an Invite Response message to Kontagent.
	*
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	InviteSent->InviteResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['recipientUserId'] The UID of the responding user
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackInviteResponse($uniqueTrackingTag, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			'i' => 0,
			'u' => $uniqueTrackingTag
		);
		
		if (isset($optionalParams['recipientUserId'])) { $params['r'] = $optionalParams['recipientUserId']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("inr", $params, $validationErrorMsg);
	}
	
	/*
	* Sends an Notification Email Sent message to Kontagent.
	*
	* @param int $userId The UID of the sending user
	* @param string $recipientUserIds A comma-separated list of the recipient UIDs
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	NotificationEmailSent->NotificationEmailResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackNotificationEmailSent($userId, $recipientUserIds, $uniqueTrackingTag, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'r' => $recipientUserIds,
			'u' => $uniqueTrackingTag
		);
		
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("nes", $params, $validationErrorMsg);
	}

	/*
	* Sends an Notification Email Response message to Kontagent.
	*
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	NotificationEmailSent->NotificationEmailResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['recipientUserId'] The UID of the responding user
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackNotificationEmailResponse($uniqueTrackingTag, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			'i' => 0,
			'u' => $uniqueTrackingTag
		);
		
		if (isset($optionalParams['recipientUserId'])) { $params['r'] = $optionalParams['recipientUserId']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }	
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("nei", $params, $validationErrorMsg);
	}

	/*
	* Sends an Stream Post message to Kontagent.
	*
	* @param int $userId The UID of the sending user
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	NotificationEmailSent->NotificationEmailResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param string $type The Facebook channel type
	*	(feedpub, stream, feedstory, multifeedstory, dashboard_activity, or dashboard_globalnews).
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackStreamPost($userId, $uniqueTrackingTag, $type, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'u' => $uniqueTrackingTag,
			'tu' => $type
		);
		
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
		
		return $this->sendMessage("pst", $params, $validationErrorMsg);
	}

	/*
	* Sends an Stream Post Response message to Kontagent.
	*
	* @param string $uniqueTrackingTag 32-digit hex string used to match 
	*	NotificationEmailSent->NotificationEmailResponse->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param string $type The Facebook channel type
	*	(feedpub, stream, feedstory, multifeedstory, dashboard_activity, or dashboard_globalnews).
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['recipientUserId'] The UID of the responding user
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackStreamPostResponse($uniqueTrackingTag, $type, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			'i' => 0,
			'u' => $uniqueTrackingTag,
			'tu' => $type
		);
		
		if (isset($optionalParams['recipientUserId'])) { $params['r'] = $optionalParams['recipientUserId']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("psr", $params, $validationErrorMsg);
	}

	/*
	* Sends an Custom Event message to Kontagent.
	*
	* @param int $userId The UID of the user
	* @param string $eventName The name of the event
	* @param array $optionalParams An associative array containing paramName => value
	* @param int $optionalParams['value'] A value associated with the event
	* @param int $optionalParams['level'] A level associated with the event (must be positive)
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackEvent($userId, $eventName, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'n' => $eventName
		);
		
		if (isset($optionalParams['value'])) { $params['v'] = $optionalParams['value']; }
		if (isset($optionalParams['level'])) { $params['l'] = $optionalParams['level']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("evt", $params, $validationErrorMsg);
	}

	/*
	* Sends an Application Added message to Kontagent.
	*
	* @param int $userId The UID of the installing user
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['uniqueTrackingTag'] 16-digit hex string used to match 
	*	Invite/StreamPost/NotificationSent/NotificationEmailSent->ApplicationAdded messages. 
	*	See the genUniqueTrackingTag() helper method.
	* @param string $optionalParams['shortUniqueTrackingTag'] 8-digit hex string used to match 
	*	ThirdPartyCommClicks->ApplicationAdded messages. 
	*	See the genShortUniqueTrackingTag() helper method.
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackApplicationAdded($userId, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array('s' => $userId);
		
		if (isset($optionalParams['uniqueTrackingTag'])) { $params['u'] = $optionalParams['uniqueTrackingTag']; }
		if (isset($optionalParams['shortUniqueTrackingTag'])) { $params['su'] = $optionalParams['shortUniqueTrackingTag']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("apa", $params, $validationErrorMsg);
	}

	/*
	* Sends an Application Removed message to Kontagent.
	*
	* @param int $userId The UID of the removing user
	* @param array $optionalParams An associative array containing paramName => value
    	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackApplicationRemoved($userId, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array('s' => $userId);

		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("apr", $params, $validationErrorMsg);
	}
	
	/*
	* Sends an Third Party Communication Click message to Kontagent.
	*
	* @param string $type The third party comm click type (ad, partner).
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['shortUniqueTrackingTag'] 8-digit hex string used to match 
	*	ThirdPartyCommClicks->ApplicationAdded messages. 
	* @param string $optionalParams['userId'] The UID of the user
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackThirdPartyCommClick($type, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			'i' => 0,
			'tu' => $type
		);
		
		if (isset($optionalParams['shortUniqueTrackingTag'])) { $params['su'] = $optionalParams['shortUniqueTrackingTag']; }
		if (isset($optionalParams['userId'])) { $params['s'] = $optionalParams['userId']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }	
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("ucc", $params, $validationErrorMsg);
	}

	/*
	* Sends an Page Request message to Kontagent.
	*
	* @param int $userId The UID of the user
	* @param array $optionalParams An associative array containing paramName => value
	* @param int $optionalParams['ipAddress'] The current users IP address
	* @param string $optionalParams['pageAddress'] The current page address (ex: index.html)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackPageRequest($userId, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'ts' => time() 
		);
		
		if (isset($optionalParams['ipAddress'])) { $params['ip'] = $optionalParams['ipAddress']; }
		if (isset($optionalParams['pageAddress'])) { $params['u'] = $optionalParams['pageAddress']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("pgr", $params, $validationErrorMsg);
	}

	/*
	* Sends an User Information message to Kontagent.
	*
	* @param int $userId The UID of the user
	* @param array $optionalParams An associative array containing paramName => value
	* @param int $optionalParams['birthYear'] The birth year of the user
	* @param string $optionalParams['gender'] The gender of the user (m,f,u)
	* @param string $optionalParams['country'] The 2-character country code of the user
	* @param int $optionalParams['friendCount'] The friend count of the user
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackUserInformation($userId, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array('s' => $userId);
		
		if (isset($optionalParams['birthYear'])) { $params['b'] = $optionalParams['birthYear']; }
		if (isset($optionalParams['gender'])) { $params['g'] = $optionalParams['gender']; }
		if (isset($optionalParams['country'])) { $params['lc'] = strtoupper($optionalParams['country']); }
		if (isset($optionalParams['friendCount'])) { $params['f'] = $optionalParams['friendCount']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }

		return $this->sendMessage("cpu", $params, $validationErrorMsg);
	}

	/*
	* Sends an Goal Count message to Kontagent.
	*
	* @param int $userId The UID of the user
	* @param array $optionalParams An associative array containing paramName => value
	* @param int $optionalParams['goalCount1'] The amount to increment goal count 1 by
	* @param int $optionalParams['goalCount2'] The amount to increment goal count 2 by
	* @param int $optionalParams['goalCount3'] The amount to increment goal count 3 by
	* @param int $optionalParams['goalCount4'] The amount to increment goal count 4 by
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackGoalCount($userId, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array('s' => $userId);
		
		if (isset($optionalParams['goalCount1'])) { $params['gc1'] = $optionalParams['goalCount1']; }
		if (isset($optionalParams['goalCount2'])) { $params['gc2'] = $optionalParams['goalCount2']; }
		if (isset($optionalParams['goalCount3'])) { $params['gc3'] = $optionalParams['goalCount3']; }
		if (isset($optionalParams['goalCount4'])) { $params['gc4'] = $optionalParams['goalCount4']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("gci", $params, $validationErrorMsg);
	}

	/*
	* Sends an Revenue message to Kontagent.
	*
	* @param int $userId The UID of the user
	* @param int $value The amount of revenue in cents
	* @param array $optionalParams An associative array containing paramName => value
	* @param string $optionalParams['type'] The transaction type (direct, indirect, advertisement, credits, other)
	* @param string $optionalParams['subtype1'] Subtype1 value (max 32 chars)
	* @param string $optionalParams['subtype2'] Subtype2 value (max 32 chars)
	* @param string $optionalParams['subtype3'] Subtype3 value (max 32 chars)
	* @param string $optionalParams['data'] Additional JSON-formatted data to associate with the message
	* @param string $validationErrorMsg The error message on validation failure
	* 
	* @return bool Returns false on validation failure, true otherwise
	*/
	public function trackRevenue($userId, $value, $optionalParams = array(), &$validationErrorMsg = null) {
		$params = array(
			's' => $userId,
			'v' => $value
		);
		
		if (isset($optionalParams['type'])) { $params['tu'] = $optionalParams['type']; }
		if (isset($optionalParams['subtype1'])) { $params['st1'] = $optionalParams['subtype1']; }
		if (isset($optionalParams['subtype2'])) { $params['st2'] = $optionalParams['subtype2']; }
		if (isset($optionalParams['subtype3'])) { $params['st3'] = $optionalParams['subtype3']; }
		if (isset($optionalParams['data'])) { $params['data'] = base64_encode($optionalParams['data']); }
	
		return $this->sendMessage("mtu", $params, $validationErrorMsg);
	}
}

////////////////////////////////////////////////////////////////////////////////

/*
* Helper class to validate the paramters for the Kontagent API messages
*/
class KtValidator
{

	/*
	* Validates a parameter of a given message type.
	*
	* @param string $messageType The message type that the param belongs to (ex: ins, apa, etc.)
	* @param string $paramName The name of the parameter (ex: s, su, u, etc.)
	* @param mixed $paramValue The value of the parameter
	* @param string $validationErrorMsg If the parameter value is invalid, this will be populated with the error message
	*
	* @returns bool Returns true on success and false on failure.
	*
	*/
	public static function validateParameter($messageType, $paramName, $paramValue, &$validationErrorMsg = null) {
		// generate name of the dynamic method
		$methodName = 'validate' . ucfirst($paramName);
		if (!self::$methodName($messageType, $paramValue, $validationErrorMsg)) {
			return false;
		} else {
			return true;
		}
	}
 
	/*
	* Validates that subtypes parameters are incrementing (ex: if st2 is used, st1 is not optional)
	*
	* @param array $params All the parameters for the given message
	* @param string $validationErrorMsg If the parameter value is invalid, this will be populated with the error message
	*
	* @returns bool Returns true on success and false on failure.
	*
	*/
	public static function validateSubtypes($params, &$validationErrorMsg = null) {
		if (isset($params['st3']) && !isset($params['st2'])) {
			$validationErrorMsg = 'Invalid subtypes. st2 is not optional if st3 is used.';
			return false;
		} else if (isset($params['st2']) && !isset($params['st1'])) {
			$validationErrorMsg = 'Invalid subtypes. st1 is not optional if st2 is used.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateB($messageType, $paramValue, &$validationErrorMsg = null) {
		// birthyear param (cpu message)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1900, 'max_range' => 2012))) === false) {
			$validationErrorMsg = 'Invalid birth year.';
			return false;
		} else {
			return true;
		}
	}

	private static function validateData($messageType, $paramValue, &$validationErrorMsg = null) {
		return true;
	}
	
	
	private static function validateF($messageType, $paramValue, &$validationErrorMsg = null) {
		// friend count param (cpu message)
		if(filter_var($paramValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0))) === false) {
			$validationErrorMsg = 'Invalid friend count.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateG($messageType, $paramValue, &$validationErrorMsg = null) {
		// gender param (cpu message)
		if (preg_match('/^[mfu]$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid gender.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateGc1($messageType, $paramValue, &$validationErrorMsg = null) {
		// goal count param (gc1, gc2, gc3, gc4 messages)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => -16384, 'max_range' => 16384))) === false) {
			$validationErrorMsg = 'Invalid goal count value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateGc2($messageType, $paramValue, &$validationErrorMsg = null) {
		return self::validateGc1($messageType, $paramValue, $validationErrorMsg);
	}
	
	private static function validateGc3($messageType, $paramValue, &$validationErrorMsg = null) {
		return self::validateGc1($messageType, $paramValue, $validationErrorMsg);
	}
	
	private static function validateGc4($messageType, $paramValue, &$validationErrorMsg = null) {
		return self::validateGc1($messageType, $paramValue, $validationErrorMsg);
	}
	
	private static function validateI($messageType, $paramValue, &$validationErrorMsg = null) {
		// isAppInstalled param (inr, psr, ner, nei messages)
		if (preg_match('/^[01]$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid isAppInstalled value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateIp($messageType, $paramValue, &$validationErrorMsg = null) {
		// ip param (pgr messages)
		if (filter_var($paramValue, FILTER_VALIDATE_IP) === false) {
			$validationErrorMsg = 'Invalid ip address value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateL($messageType, $paramValue, &$validationErrorMsg = null) {
		// level param (evt messages)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 255))) === false) {
			$validationErrorMsg = 'Invalid level value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateLc($messageType, $paramValue, &$validationErrorMsg = null) {
		// country param (cpu messages)
		if (preg_match('/^[A-Z]{2}$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid country value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateLp($messageType, $paramValue, &$validationErrorMsg = null) {
		// postal/zip code param (cpu messages)
		// this parameter isn't being used so we just return true for now
		return true;
	}
	
	private static function validateLs($messageType, $paramValue, &$validationErrorMsg = null) {
		// state param (cpu messages)
		// this parameter isn't being used so we just return true for now
		return true;
	}
	
	private static function validateN($messageType, $paramValue, &$validationErrorMsg = null) {
		// event name param (evt messages)
		if (preg_match('/^[A-Za-z0-9-_]{1,32}$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid event name value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateR($messageType, $paramValue, &$validationErrorMsg = null) {
		// Sending messages include multiple recipients (comma separated) and
		// response messages can only contain 1 recipient UID
		$uids = explode(",", $paramValue);
		foreach ($uids as $uid) {
			if(self::validateS($messageType, $uid, $validationErrorMsg) === false) {
				$validationErrorMsg = 'Invalid recipient user ids.';
				return false;
			}
		}
		return true;
	}
	
	private static function validateS($messageType, $paramValue, &$validationErrorMsg = null) {
		// userId param)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array('options' => array('min_range' => 1))) === false) {
			$validationErrorMsg = 'Invalid user id.';
			return false;
		} else {
			return true;
		}
	}

	private static function validateSdk($messageType, $paramValue, &$validationErrorMsg = null) {
		return true;
	}
	
	private static function validateSt1($messageType, $paramValue, &$validationErrorMsg = null) {
		// subtype1 param
		if (preg_match('/^[A-Za-z0-9-_]{1,32}$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid subtype value.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateSt2($messageType, $paramValue, &$validationErrorMsg = null) {
		return self::validateSt1($messageType, $paramValue, $validationErrorMsg);
	}
	
	private static function validateSt3($messageType, $paramValue, &$validationErrorMsg = null) {
		return self::validateSt1($messageType, $paramValue, $validationErrorMsg);
	}

	private static function validateSu($messageType, $paramValue, &$validationErrorMsg = null) {
		// short tracking tag param
		if (preg_match('/^[A-Fa-f0-9]{8}$/', $paramValue) == 0) {
			$validationErrorMsg = 'Invalid short unique tracking tag.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateTs($messageType, $paramValue, &$validationErrorMsg = null) {
		// timestamp param (pgr message)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array("options" => array("min_range" => 0))) === false) {
			$validationErrorMsg = 'Invalid timestamp.';
			return false;
		} else {
			return true;
		}
	}
	
	private static function validateTu($messageType, $paramValue, &$validationErrorMsg = null) {
		// type parameter (mtu, pst/psr, ucc messages)
		// acceptable values for this parameter depends on the message type
		if ($messageType == 'mtu') {
			if (preg_match('/^(direct|indirect|advertisement|credits|other)$/', $paramValue) == 0) {
				$validationErrorMsg = 'Invalid monetization type.';
				return false;
			}
		} elseif ($messageType == 'pst' || $messageType == 'psr') {
			if (preg_match('/^(feedpub|stream|feedstory|multifeedstory|dashboard_activity|dashboard_globalnews)$/', $paramValue) == 0) {
				$validationErrorMsg = 'Invalid stream post/response type.';
				return false;
			}
		} elseif ($messageType == 'ucc') {
			if (preg_match('/^(ad|partner)$/', $paramValue) == 0) {
				$validationErrorMsg = 'Invalid third party communication click type.';
				return false;
			}
		}
		
		return true;
	}
	
	private static function validateU($messageType, $paramValue, &$validationErrorMsg = null) {
		// unique tracking tag parameter for all messages EXCEPT pgr.
		// for pgr messages, this is the "page address" param
		if ($messageType != 'pgr') {
			if (preg_match('/^[A-Fa-f0-9]{16}$/', $paramValue) == 0) {
				$validationErrorMsg = 'Invalid unique tracking tag.';
				return false;
			}
		}
		
		return true;
	}
	
	private static function validateV($messageType, $paramValue, &$validationErrorMsg = null) {
		// value param (mtu, evt messages)
		if (filter_var($paramValue, FILTER_VALIDATE_INT, array("options" => array("min_range" => -1000000, "max_range" => 1000000))) === false) {
			$validationErrorMsg = 'Invalid value.';
			return false;
		} else {
			return true;
		}
	}
}

?>