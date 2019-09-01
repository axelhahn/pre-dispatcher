<?php
/*
 *
 *     P R E   D I S P A T C H E R    C L A S S
 * 
 * 
 *     This is free software and Open Source 
 *     GNU General Public License (GNU GPL) version 3
 * 
 *     Author: Axel Hahn
 * 
 *     The preDispatcher is a cache in front of a slow website delivery/ cms.
 *     Initially it was created for my website with Concrete5. But it could
 *     be used for other products too.
 * 
 */

require_once 'cache.class.php';
class preDispatcher{

	// --------------------------------------------------------------------------------
	// CONFIG
	// --------------------------------------------------------------------------------

	/**
	 * @var array config values
	 */
	protected $aCfgCache=array();

	/**
	 * @var array  messages for debugging
	 */
	protected $aMsg=array(); 

    /**
     * fileextension for storing cachefiles (without ".")
     * @var string
     */
    protected $_sCacheExt = 'cache';

	protected $_sRequest=false; 
	protected $_bDebug=false;

	protected $_oCache=false; 

	// --------------------------------------------------------------------------------
	// CONSTRUCTOR
	// --------------------------------------------------------------------------------

	/**
	 * init
	 */
	public function __construct() {
		// load config
		$this->aCfgCache=@include('pre_dispatcher_config.php');
		include('cache.class_config.php');
		$this->_oCache=new AhCache('preDispatcher');

		// echo '<pre>'.print_r($_SERVER,1).'</pre>';

		// check debug
		if(isset($_SERVER['REQUEST_URI'])){
			if(isset($this->aCfgCache['debug']['enable']) && $this->aCfgCache['debug']['enable']){
				if(
					isset($this->aCfgCache['debug']['ip'])
					&& is_array($this->aCfgCache['debug']['ip'])
					&& count($this->aCfgCache['debug']['ip'])
				){
					$sIp=$_SERVER['REMOTE_ADDR'];
					// die($sIp);
					foreach($this->aCfgCache['debug']['ip'] as $sPattern){
						// $this->addInfo('... test debug ip '.$sPattern.' vs pattern '.$sPattern);
						if(preg_match('#'.$sPattern.'#', $sIp)){
							// $this->addInfo('...... matched');
							$this->_bDebug=true;
						}
					}
				} else {
					$this->_bDebug=true;
				}
			}

			// set minimal vars
			$this->_sRequest=$_SERVER['REQUEST_URI'];
			$this->_oCache=new AhCache('preDispatcher', $this->_sRequest);

			$this->sDeleteKey='__delete_'.md5($_SERVER['HTTP_HOST']);
			$this->aCfgCache['refreshcache']['get'][]=$this->sDeleteKey;
		}

		return true;
	}


	// --------------------------------------------------------------------------------
	//
	// cleanup
	//
	// --------------------------------------------------------------------------------

    /**
     * Cleanup cache directory; delete all cachefiles older than n seconds
     * Other filetypes in the directory won't be touched.
     * Empty directories will be deleted.
     * 
     * To delete all cachefles of all modules you can use
     * $o->cleanup(0); 
     * 
     * @param int $iSec max age of cachefile; older cachefiles will be deleted
     * @return     true
     */
    public function cleanup($iSec = false) {
		return $this->_oCache->cleanup($iSec);
    }
	
	// --------------------------------------------------------------------------------
	//
	// log/ debug messages
	//
	// --------------------------------------------------------------------------------

	/**
	 * add log message for debugging
	 *
	 * @param string $sHeaderMessage
	 * @return bool (true)
	 */
	public function addInfo($sHeaderMessage){
		$this->aMsg[]=array(
			'time'=>microtime(true),
			'message'=>$sHeaderMessage,
		);

		return true;
	}

	/**
	 * show log messages as http response headers (debug flag must be true)
	 *
	 * @return bool (true)
	 */
	public function renderHeaders($sMode='unknown'){
		if(!$this->_bDebug){
			return false;
		}
		$sReturn='';
		$iCounter=0;
		$iStartTime=0;
		$iTimer=0;
		$iLastTime=0;

		$aColors=array(
			'unknown'=>'rgba(128,128,128,0.7)',
			'fromcache'=>'rgba(88,128,88,0.7)',
			'stored'=>'rgba(88,88,128,0.7)',
			'uncachable'=>'rgba(128,88,88,0.7)',
		);

		foreach ($this->aMsg as $aMessageItem){
			$iCounter++;
			if($iCounter===1){
				$iStartTime=$aMessageItem['time'];
			}
			$iTimer=($iLastTime ? $aMessageItem['time'] - $iLastTime : 0);
			$iLastTime=$aMessageItem['time'];
			if(!isset($this->aCfgCache['debug']['header']) || $this->aCfgCache['debug']['header']){
				header('X-CACHE-DEBUG-'.($iCounter<10?'0':'').$iCounter.': '.$aMessageItem['message']);
			}

			if(!isset($this->aCfgCache['debug']['html']) || $this->aCfgCache['debug']['html']){
				$sReturn.=($iCounter<10?'0':'').$iCounter.': '
					// .number_format($iTimer,3).' - '
					.$aMessageItem['message'].'<br>'
				;
			}
		}
		$sBg='rgba(128,128,128,0.7)';
		if($sReturn){
			$sReturn='<div style="position: absolute; top: 1em; right: 1em; border: 2px solid rgba(0,0,0,0.2); background:'
				. $aColors[$sMode]
				. '; color:#fee; padding: 0.5em; z-index: 100000;">'
				. '<h3 style="margin:0; ">'.__CLASS__.':</h3>'
				. $sReturn
				. 'Total: <strong style="font-size: 130%;">'.(number_format(($iLastTime-$iStartTime)*1000, 2)).'ms</strong><br>'
				. ($sMode==='fromcache' ? '<a href="'.$this->getRefreshUrl().'" target="_blank">Refresh</a>' : '')
				. '</div>'
			;
		}
		return $sReturn;
	}

	// --------------------------------------------------------------------------------
	//
	// caching
	//
	// --------------------------------------------------------------------------------
	/**
	 * helper function to detect config elements; 
	 * it returns true if no blocking element was found
	 *
	 * @param string $sKey      key in config; one of nocache|deletecache
	 * @param string $sContent  optional: response body content
	 * @return void
	 */
	protected function _checkCfgKey($sKey,$sContent=''){
		$bReturn=true;
		if(isset($this->aCfgCache[$sKey]['cookie'])){
			foreach($this->aCfgCache[$sKey]['cookie'] as $sEntry){
				if(isset($_COOKIE[$sEntry])){
					$this->addInfo('check '.$sKey.' - found cookie ['.$sEntry.'] = ' . $_COOKIE[$sEntry]);
					$bReturn=false;
				}
			}
		}

		if(isset($this->aCfgCache[$sKey]['session'])){
			foreach($this->aCfgCache[$sKey]['session'] as $sEntry){
				if(isset($_SESSION[$sEntry])){
					$this->addInfo('check '.$sKey.' - found session var ['.$sEntry.'] = ' . $_SESSION[$sEntry]);
					$bReturn=false;
				}
			}
		}

		if(isset($this->aCfgCache[$sKey]['get'])){
			foreach($this->aCfgCache[$sKey]['get'] as $sEntry){
				if(isset($_GET[$sEntry])){
					$this->addInfo('check '.$sKey.' - found GET var ['.$sEntry.'] = ' . $_GET[$sEntry]);
					$bReturn=false;
				}
			}
		}

		if($sContent && isset($this->aCfgCache[$sKey]['body'])){
			foreach($this->aCfgCache[$sKey]['body'] as $sEntry){
				if(
					strstr($sContent, $sEntry)
					|| preg_match('#'.$sEntry.'#', $sContent)
				){
					$this->addInfo('check '.$sKey.' - matching ['.$sEntry.'] in content');
					$bReturn=false;
				}
			}
		}
		return $bReturn;

	}

	/**
	 * get ttl for a request in sec by reading regex inmm the config
	 *
	 * @return integer
	 */
	public function getConfiguredTtl(){
		$iTtl=$this->aCfgCache['ttl']['_default'];
		// $this->addInfo('ttl default = '.$iTtl.'s');
		foreach($this->aCfgCache['ttl'] as $sRegex => $iValue){
			// $this->addInfo('... test '.$sRegex);
			if (preg_match("#$sRegex#", $this->_sRequest)){
				// $this->addInfo('...... matched --> set ttl to '.$iValue);
				$iTtl=$iValue;
			}
		}
		$this->addInfo('Cache-ttl = '.$iTtl.'s');
		if(!$iTtl){
			$this->deleteCache();
		}
		return $iTtl;
	}

	/**
	 * get age of cachefile
	 *
	 * @return void
	 */
	public function getFileAge(){
		return $this->_oCache->getAge();

	}

	/**
	 * get content of the cached data 
	 * if cached data exists and is not expired it will be delivered and process dies
	 *
	 * @return boolean (false)
	 */
	public function getCachedContent(){

		if($this->isRefresh()){
			$this->addInfo('Using Cache = NO (refreshing it)');
			return false;
		}
		
		if(!$this->_oCache->isExpired()){
			$iTtl=$this->_oCache->getTtl();
			$iAgeOfCache=$this->_oCache->getAge();
			$iLiftime=round($iAgeOfCache/$iTtl*100);
			$this->addInfo('cache age '.$iAgeOfCache.'s');
			$this->addInfo('ttl '.$iTtl.'s');
			$this->addInfo('lifetime '.$iLiftime.'%');

			$this->addInfo('Using Cache :-)');
			$aData=$this->_oCache->read();
			echo str_replace(
				'</body',
				$this->renderHeaders('fromcache').'</body',
				$aData['content']
			);
			die();
		}
		$this->addInfo('Using Cache = NO');
		return false;

	}

	/**
	 * get an array with a list of cached elements without data
	 * key is the filename
	 * items in the array
	 *            [iTtl] => ttl of the cache item [s]
     *            [tsExpire] => expire date
     *            [module] => preDispatcher
     *            [cacheid] => id of the cached item
     *            [_lifetime] => lifetime left [s]
     *            [_age] => age since last update [s]
	 *
	 * @param array $aFilter  filter; valid keys are
	 *                        - ageOlder       integer  return items that are older [n] sec
	 *                        - lifetimeBelow  integer  return items that expire in less [n] sec
	 *                        no filter returns all cached entries
	 * @return array
	 */
	public function getListOfCachefiles($aFilter){
		$aReturn=$this->_oCache->getCachedItems(false, $aFilter);
		return $aReturn;
	}

	public function getRefreshUrl(){
		$sUrl='http'.(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 's' : '') .'://'.$_SERVER['HTTP_HOST'].$this->_sRequest;
		$sUrl.=(strstr($sUrl, '?') ? '&' : '?' ) . $this->sDeleteKey.'=1';
		return $sUrl;
	}
	/**
	 * return boolean if the current request can be cached
	 * remark: additionally the existance of a cache, its age and ttl
	 *         will be handled in getCachedContent
	 * 
	 * @see getCachedContent
	 * 
	 * @param string $sContent
	 * @return boolean
	 */
	public function isCachable($sContent=''){
		$bReturn=true;
		if(!$this->_checkCfgKey('delcache',$sContent)){
			$this->addInfo('isCachable found a delcache info');
			$this->deleteCache();
			$bReturn=false;
		}
		if($_SERVER['REQUEST_METHOD']!=='GET'){
			$this->addInfo('no caching for method ' . $_SERVER['REQUEST_METHOD']);
			$bReturn=false;
		}
		if(!$this->_checkCfgKey('nocache',$sContent)){
			$this->deleteCache();
			$bReturn=false;
		}
		return $bReturn;
	}


	/**
	 * check if the current request is a refresh of a cache
	 *
	 * @return boolean
	 */
	public function isRefresh(){
		$bRefresh=false;
		if(isset($this->aCfgCache['refreshcache']['get'])){
			foreach($this->aCfgCache['refreshcache']['get'] as $sEntry){
				if(isset($_GET[$sEntry])){
					$this->addInfo('check refresh - found GET var ['.$sEntry.'] = ' . $_GET[$sEntry]);
					$this->_sRequest=str_replace($sEntry.'='.$_GET[$sEntry], '', $this->_sRequest);

					unset($_GET[$sEntry]);
					$bRefresh=true;
				}
			}
		}
		if($bRefresh){
			$this->_sRequest=str_replace(
				array('?&', '&&'), 
				array('?',  '&'), 
				$this->_sRequest
			);
			# cut trailing "?"
			$this->_sRequest=preg_replace('#\?$#','',$this->_sRequest);
			
			$this->addInfo('Request ' . $this->_sRequest);
			$this->_oCache=new AhCache('preDispatcher', $this->_sRequest);
		}
		return $bRefresh;
	}
	/**
	 * delete cache item
	 *
	 * @return boolean
	 */
	public function deleteCache(){
		$bDeleted=$this->_oCache->delete();
		$this->addInfo($bDeleted ? 'cache was deleted' : 'Cache not deleted (mabe it did not exist)');
		return $bDeleted;
	}

	/**
	 * store content as cache item
	 *
	 * @param string $sContent  content of fetched request
	 * @return boolean
	 */
	public function doCache($aData){

		// ensure if cachable *with* content check
		$iTtl=$this->getConfiguredTtl();
		$sContent=$aData['content'];
		if(!$sContent || strlen($sContent)<200 || !$this->isCachable($sContent) || !$iTtl){
			$this->addInfo('Request is not cachable');
			return false;
		}
		$this->addInfo('store '.strlen($sContent).' byte as cache item');
		return $this->_oCache->write($aData, $iTtl);
	}
	/**
	 * refresh cache with current content
	 *
	 * @param string $sContent  content of fetched request
	 * @return boolean
	 */
	public function refreshCache($sCacheId){

		// ensure if cachable *with* content check
		$iTtl=$this->getConfiguredTtl();
		$sContent=$aData['content'];
		if($sContent && strlen($sContent)<200 && (!$this->isCachable($sContent) || !$iTtl)){
			$this->addInfo('Request is not cachable');
			return false;
		}
		$this->addInfo('store '.strlen($sContent).' byte as cache item');
		return $this->_oCache->write($aData, $iTtl);
	}

}

