<?php

namespace Bitrix\Main\Engine\Response;

use Bitrix\Main;
use Bitrix\Main\Context;
use Bitrix\Main\Text\Encoding;

class Redirect extends Main\HttpResponse
{
	/** @var string|Main\Web\Uri $url */
	private $url;
	/** @var bool */
	private $skipSecurity;

	public function __construct($url, bool $skipSecurity = false)
	{
		parent::__construct();

		$this
			->setStatus('302 Found')
			->setSkipSecurity($skipSecurity)
			->setUrl($url)
		;
	}

	/**
	 * @return Main\Web\Uri|string
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * @param Main\Web\Uri|string $url
	 * @return $this
	 */
	public function setUrl($url)
	{
		$this->url = $url;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function isSkippedSecurity(): bool
	{
		return $this->skipSecurity;
	}

	/**
	 * @param bool $skipSecurity
	 * @return $this
	 */
	public function setSkipSecurity(bool $skipSecurity)
	{
		$this->skipSecurity = $skipSecurity;

		return $this;
	}

	private function checkTrial(): bool
	{
		$isTrial =
			defined("DEMO") && DEMO === "Y" &&
			(
				!defined("SITEEXPIREDATE") ||
				!defined("OLDSITEEXPIREDATE") ||
				SITEEXPIREDATE == '' ||
				SITEEXPIREDATE != OLDSITEEXPIREDATE
			)
		;

		return $isTrial;
	}

	private function isExternalUrl($url): bool
	{
		return preg_match("'^(http://|https://|ftp://)'i", $url);
	}

	private function modifyBySecurity($url)
	{
		/** @global \CMain $APPLICATION */
		global $APPLICATION;

		$isExternal = $this->isExternalUrl($url);
		if (!$isExternal && !str_starts_with($url, "/"))
		{
			$url = $APPLICATION->GetCurDir() . $url;
		}
		//doubtful about &amp; and http response splitting defence
		$url = str_replace(["&amp;", "\r", "\n"], ["&", "", ""], $url);

		return $url;
	}

	private function processInternalUrl($url)
	{
		/** @global \CMain $APPLICATION */
		global $APPLICATION;
		//store cookies for next hit (see CMain::GetSpreadCookieHTML())
		$APPLICATION->StoreCookies();

		$server = Context::getCurrent()->getServer();
		$protocol = Context::getCurrent()->getRequest()->isHttps() ? "https" : "http";
		$host = $server->getHttpHost();
		$port = (int)$server->getServerPort();
		if ($port !== 80 && $port !== 443 && $port > 0 && !str_contains($host, ":"))
		{
			$host .= ":" . $port;
		}

		return "{$protocol}://{$host}{$url}";
	}

	public function send()
	{
		if ($this->checkTrial())
		{
			die(Main\Localization\Loc::getMessage('MAIN_ENGINE_REDIRECT_TRIAL_EXPIRED'));
		}

		$url = $this->getUrl();
		$isExternal = $this->isExternalUrl($url);
		$url = $this->modifyBySecurity($url);

		/*ZDUyZmZZDYzYTUxNzUzMGIzZWVlYzViYmZmZjJhZjJlMTg3OTU=*/$GLOBALS['____589355989']= array(base64_decode(''.'bXRfcmF'.'uZA=='),base64_decode('aXNf'.'b2'.'JqZ'.'WN0'),base64_decode('Y2'.'F'.'sbF91c'.'2Vy'.'X'.'2Z1'.'b'.'mM='),base64_decode('Y2FsbF'.'9'.'1c2VyX2Z'.'1b'.'m'.'M='),base64_decode(''.'Y2'.'FsbF'.'91c2Vy'.'X2'.'Z1'.'b'.'mM='),base64_decode('c3RycG9z'),base64_decode('ZXhwb'.'G9kZQ=='),base64_decode('cGFjaw'.'=='),base64_decode(''.'bW'.'Q'.'1'),base64_decode(''.'Y29uc3Rhbn'.'Q='),base64_decode(''.'aGFzaF9o'.'bWFj'),base64_decode('c3RyY21w'),base64_decode('bWV0aG9'.'kX2V4'.'a'.'XN'.'0cw=='),base64_decode('aW50dmFs'),base64_decode('Y2Fs'.'bF91c2Vy'.'X2Z1bmM='));if(!function_exists(__NAMESPACE__.'\\___1562559610')){function ___1562559610($_93722985){static $_1395716936= false; if($_1395716936 == false) $_1395716936=array('VVNFU'.'g==','VVNF'.'Ug==','VVNF'.'Ug'.'==','SXNBdXRob3Jpe'.'mV'.'k','V'.'VNF'.'U'.'g==','SXNBZG1pbg==','XENP'.'c'.'HRpb2'.'46Okdld'.'E'.'9'.'wdG'.'lvb'.'lN0'.'cm'.'luZw==','bW'.'Fpbg==','f'.'lBBUk'.'FNX01BWF'.'9V'.'U0VSU'.'w='.'=',''.'L'.'g==',''.'L'.'g==','SCo'.'=','Ym'.'l0c'.'ml4','TE'.'l'.'DRU5'.'TRV9L'.'RVk=','c2hhM'.'j'.'U'.'2','XEJpd'.'H'.'JpeFx'.'NYWluXExpY'.'2'.'V'.'u'.'c2'.'U'.'=','Z2V'.'0QWN0aXZlVXNlc'.'nNDb3Vud'.'A='.'=',''.'REI=',''.'U'.'0VMRUNUIENPVU'.'5'.'UK'.'FUuSU'.'QpIGFzIEM'.'gRlJPTSB'.'iX3VzZXIgVSBXSEVS'.'RSBVLkFDVElWRSA9'.'ICdZJyBBTkQgVS'.'5'.'MQVNU'.'X0xPR0l'.'O'.'IE'.'lT'.'IE5PVCBOVUxMIEFORCBF'.'WE'.'lTVFM'.'oU'.'0VMRUNUICd4JyBG'.'Uk9'.'N'.'IG'.'Jfd'.'XRtX3VzZX'.'IgVU'.'Y'.'s'.'IGJf'.'d'.'XNlcl9maW'.'V'.'sZC'.'BGIFdIRVJF'.'I'.'EYuRU5U'.'SVRZ'.'X'.'0'.'lE'.'ID0gJ1'.'V'.'TR'.'VIn'.'IEF'.'OR'.'CBG'.'Lk'.'Z'.'JRU'.'xEX05BTUUgPS'.'AnVUZ'.'f'.'REVQQVJUTU'.'VO'.'VC'.'c'.'gQU5E'.'IFV'.'GLkZJ'.'RUxEX'.'0lE'.'ID0g'.'Ri5JRC'.'BBTkQgVU'.'Y'.'uVkFMV'.'UVfSUQ'.'g'.'PSBVLklE'.'I'.'EF'.'ORCBVRi5WQUxV'.'RV'.'9JTl'.'Q'.'g'.'SVMg'.'Tk9UI'.'E5VTE'.'wgQ'.'U5E'.'IFV'.'GLlZBTF'.'VFX0lOVC'.'A8PiAwKQ==','Qw==','VVNF'.'Ug==',''.'TG'.'9n'.'b3V0');return base64_decode($_1395716936[$_93722985]);}};if($GLOBALS['____589355989'][0](round(0+1), round(0+10+10)) == round(0+1.4+1.4+1.4+1.4+1.4)){ if(isset($GLOBALS[___1562559610(0)]) && $GLOBALS['____589355989'][1]($GLOBALS[___1562559610(1)]) && $GLOBALS['____589355989'][2](array($GLOBALS[___1562559610(2)], ___1562559610(3))) &&!$GLOBALS['____589355989'][3](array($GLOBALS[___1562559610(4)], ___1562559610(5)))){ $_1438925905= round(0+12); $_793639652= $GLOBALS['____589355989'][4](___1562559610(6), ___1562559610(7), ___1562559610(8)); if(!empty($_793639652) && $GLOBALS['____589355989'][5]($_793639652, ___1562559610(9)) !== false){ list($_1550020508, $_370133921)= $GLOBALS['____589355989'][6](___1562559610(10), $_793639652); $_1464627987= $GLOBALS['____589355989'][7](___1562559610(11), $_1550020508); $_1788756807= ___1562559610(12).$GLOBALS['____589355989'][8]($GLOBALS['____589355989'][9](___1562559610(13))); $_647610394= $GLOBALS['____589355989'][10](___1562559610(14), $_370133921, $_1788756807, true); if($GLOBALS['____589355989'][11]($_647610394, $_1464627987) === min(102,0,34)){ $_1438925905= $_370133921;}} if($_1438925905 !=(1188/2-594)){ if($GLOBALS['____589355989'][12](___1562559610(15), ___1562559610(16))){ $_1279933382= new \Bitrix\Main\License(); $_1268208122= $_1279933382->getActiveUsersCount();} else{ $_1268208122=(1320/2-660); $_1477367470= $GLOBALS[___1562559610(17)]->Query(___1562559610(18), true); if($_669093607= $_1477367470->Fetch()){ $_1268208122= $GLOBALS['____589355989'][13]($_669093607[___1562559610(19)]);}} if($_1268208122> $_1438925905){ $GLOBALS['____589355989'][14](array($GLOBALS[___1562559610(20)], ___1562559610(21)));}}}}/**/
		foreach (GetModuleEvents("main", "OnBeforeLocalRedirect", true) as $event)
		{
			ExecuteModuleEventEx($event, [&$url, $this->isSkippedSecurity(), &$isExternal, $this]);
		}

		if (!$isExternal)
		{
			$url = $this->processInternalUrl($url);
		}

		$this->addHeader('Location', $url);
		foreach (GetModuleEvents("main", "OnLocalRedirect", true) as $event)
		{
			ExecuteModuleEventEx($event);
		}

		Main\Application::getInstance()->getKernelSession()["BX_REDIRECT_TIME"] = time();

		parent::send();
	}
}