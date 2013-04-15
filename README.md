
Getting Started
-----------------

The standard Facebook PHP SDK with Kontagent fully integrated. Use this SDK as you would normally (see Facebook documentation) and analytics will automatically be reported to your Kontagent dashboard.

	require_once './facebook.php';
	require_once './kontagent_facebook.php';

	$KontagentFacebook = new KontagentFacebook(
		array(
			'appId'	=> '<FACEBOOK_APP_ID>',
			'secret' => '<FACEBOOK_APP_SECRET>'
		),
		array(
			'apiKey' => '<KT_API_KEY>',
			'useTestServer' => false
		)
	);