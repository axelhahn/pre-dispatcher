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

class preDispatcher{

	// --------------------------------------------------------------------------------
	// CONFIG
	// --------------------------------------------------------------------------------

	/**
	 * @var array config values
	 */
	var $aCfgCache=array();

	/**
	 * @var array  messages for debugging
	 */
	var $aMsg=array(); 

    /**
     * fileextension for storing cachefiles (without ".")
     * @var string
     */
    protected $_sCacheExt = 'cache';

	var $_sRequest=false; 
	var $_sCachefile=false; 
	var $_sCacheRemovefile=false; 
	var $_bDebug=false;

	// --------------------------------------------------------------------------------
	// CONSTRUCTOR
	// --------------------------------------------------------------------------------

	/**
	 * init
	 */
	public function __construct() {
		// load config
		$this->aCfgCache=@include('pre_dispatcher_config.php');

		// check debug
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
		$this->_sCachefile=$this->getCachefile();

		$this->_sCacheRemovefile=$this->aCfgCache['cache']['dir'].'/__remove_me_to_make_all_caches_invalid__';
		if(!file_exists($this->_sCacheRemovefile)){
			touch($this->_sCacheRemovefile);
		}

		return true;
	}


	// --------------------------------------------------------------------------------
	//
	// cleanup
	//
	// --------------------------------------------------------------------------------

	/**
     * recursive cleanup of a given directory; this function is used by
     * public function cleanup()
     * @since 2.0
     * @param string $sDir full path of a local directory
     * @param string $iTS  timestamp
     * @return     true
     */
    private function _cleanupDir($sDir, $iTS) {
        // echo "<ul><li class=\"dir\">DIR: <strong>$sDir</strong><ul>";

        if (!file_exists($sDir)) {
            // echo "\t Directory does not exist - [$sDir]</ul></li></ul>";
            return false;
        }
        if (!($d = dir($sDir))) {
            // echo "\t Cannot open directory - [$sDir]</ul></li></ul>";
            return false;
        }
        while ($entry = $d->read()) {
            $sEntry = $sDir . "/" . $entry;
            if (is_dir($sEntry) && $entry != '.' && $entry != '..') {
                $this->_cleanupDir($sEntry, $iTS);
            }
            if (file_exists($sEntry)) {
                $ext = pathinfo($sEntry, PATHINFO_EXTENSION);
                $ext = substr($sEntry, strrpos($sEntry, '.') + 1);

                $exts = explode(".", $sEntry);
                $n = count($exts) - 1;
                $ext = $exts[$n];

                if ($ext == $this->_sCacheExt) {

                    $aTmp = stat($sEntry);
                    $iAge = date("U") - $aTmp['mtime'];
                    if ($aTmp['mtime'] <= $iTS) {
						// echo "<li class=\"delfile\">delete cachefile: $sEntry ($iAge s)<br>";
						$this->addInfo("delete cachefile: $sEntry ($iAge s)");
                        unlink($sEntry);
                    } else {
                        // echo "<li class=\"keepfile\">keep cachefile: $sEntry ($iAge s; " . ($aTmp['mtime'] - $iTS) . " s left)</li>";
                    }
                }
            }
        }
        // echo "</ul></li></ul>";

        // try to delete if it should be empty
        @rmdir($sDir);
        return true;
	}
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
		/*
        echo date("d.m.y - H:i:s") . " START CLEANUP ".$this->aCfgCache['cache']['dir'].", $iSec s<br>
                <style>
                    .dir{color:#888;}
                    .delfile{color:#900;}
                    .keepfile{color:#ccc;}
				</style>";
		*/				
        $this->_cleanupDir($this->aCfgCache['cache']['dir'], date("U") - $iSec);
        // echo date("d.m.y - H:i:s") . " END CLEANUP <br>";
        return true;
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
				. 'Total: <strong style="font-size: 130%;">'.(number_format($iLastTime-$iStartTime, 5)).'s</strong>'
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
     * generate a filename with full path for a cache file
	 * 
 	 * @return string
	 */
	public function getCachefile(){
		$sReturn='';

		if(isset($this->aCfgCache['cache']['readable']) && $this->aCfgCache['cache']['readable']){
			$sCacheFile=str_replace(
				array('\\', '?', '&', '='),
				array('/',  '/', '&', '/'),
				$this->_sRequest
			);
		} else {
			$sCacheFile=preg_replace('/([0-9a-f]{6})/', "$1/", md5($this->_sRequest));
		}

		// $this->addInfo('filename = '.$sCacheFile);
		return $this->aCfgCache['cache']['dir'].'/'.$sCacheFile.'.'.$this->_sCacheExt;
	}

	/**
	 * get ttl in sec
	 *
	 * @return integer
	 */
	public function getCacheTtl(){
		$iTtl=$this->aCfgCache['ttl']['_default'];
		// $this->addInfo('ttl default = '.$iTtl.'s');
		foreach($this->aCfgCache['ttl'] as $sRegex => $iValue){
			// $this->addInfo('... test '.$sRegex);
			if (preg_match("|$sRegex|", $this->_sRequest)){
				// $this->addInfo('...... matched --> set ttl to '.$iValue);
				$iTtl=$iValue;
			}
		}
		$this->addInfo('Cache-ttl = '.$iTtl.'s');
		return $iTtl;
	}

	/**
	 * get age of cachefile
	 *
	 * @return void
	 */
	public function getFileAge(){
		
		if(is_file($this->_sCachefile)){
			return date('U')-filemtime($this->_sCachefile);
		}
		return false;
	}

	/**
	 * get content of the cached data 
	 * if cached data exists and is not expired it will be delivered and process dies
	 *
	 * @return boolean (false)
	 */
	public function getCachedContent(){
		if(
			file_exists($this->_sCachefile) 
			&& is_file($this->_sCachefile)
		){
			if(!$this->isExpired()){
				$this->addInfo('Using Cache :-)');
				echo str_replace(
					'</body',
					$this->renderHeaders('fromcache').'</body',
					file_get_contents($this->_sCachefile)
				);
				die();
			}
			$this->addInfo('Using Cache = NO');
		} else {
			$this->addInfo('Cache-exists = NO');
		}
		return false;
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
			$bReturn=false;
		}
		$sCacheDir=dirname($this->_sCachefile);
		if(!is_dir($sCacheDir)){
			if(!@mkdir($sCacheDir, 0755, true)){
				$this->addInfo('ERROR: unable to create cache dir');
				$bReturn=false;
			}
		}
		return $bReturn;
	}

	/**
	 * check if the cache of the current request is expired
	 *
	 * @param [type] $sRequest
	 * @param [type] $iAge
	 * @return boolean
	 */
	public function isExpired(){
		if(filemtime($this->_sCacheRemovefile) > filemtime($this->_sCachefile)){
			$this->addInfo('Override: newer remove file');
			return true;
		}
		$iAgeOfCache=$this->getFileAge();
		$this->addInfo('Cache-exists = true');
		$this->addInfo('Cache-age = '.$iAgeOfCache.'s');
		$iTtl=$this->getCacheTtl($this->_sRequest);
		$this->addInfo('expired: '.($iAgeOfCache>$iTtl ? 'YES':'no ('.($iTtl-$iAgeOfCache).'s left)'));
		return $iAgeOfCache>$iTtl;
	}

	/**
	 * delete cache item
	 *
	 * @return boolean
	 */
	public function deleteCache(){
		
		if(file_exists($this->_sCachefile)){
			$this->addInfo('deleting cache item');
			return unlink($this->_sCachefile);
		} else {
			$this->addInfo('skip deleting cache item - does not exist');
		}
		return false;
	}
	/**
	 * store content as cache item
	 *
	 * @param string $sContent  content of fetched request
	 * @return boolean
	 */
	public function doCache($sContent){

		// ensure if cachable *with* content check
		if(!$this->isCachable($sContent)){
			$this->addInfo('Request is not cachable');
			return false;
		}

		$this->addInfo('store as cache item');
		return file_put_contents($this->_sCachefile, $sContent);
	}

}

