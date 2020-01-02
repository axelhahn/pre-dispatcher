<?php
/** 
 * --------------------------------------------------------------------------------<br>
 *          __    ______           __       
 *   ____ _/ /_  / ____/___ ______/ /_  ___ 
 *  / __ `/ __ \/ /   / __ `/ ___/ __ \/ _ \
 * / /_/ / / / / /___/ /_/ / /__/ / / /  __/
 * \__,_/_/ /_/\____/\__,_/\___/_/ /_/\___/ 
 *                                        
 * --------------------------------------------------------------------------------<br>
 * AXELS CACHE CLASS<br>
 * --------------------------------------------------------------------------------<br>
 * <br>
 * THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE <br>
 * LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR <br>
 * OTHER PARTIES PROVIDE THE PROGRAM ?AS IS? WITHOUT WARRANTY OF ANY KIND, <br>
 * EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED <br>
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE <br>
 * ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. <br>
 * SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY <br>
 * SERVICING, REPAIR OR CORRECTION.<br>
 * <br>
 * --------------------------------------------------------------------------------<br>
 * <br>
 * --- HISTORY:<br>
 * 2009-07-20  1.0  cache class on www.axel-hahn.de<br>
 * 2011-08-27  1.1  comments added; sCacheFile is private<br>
 * 2012-02-04  2.0  cache serialzable types; more methods, i.e.:<br>
 *                   - comparison of timestimp with a sourcefile<br>
 *                   - cleanup unused cachefiles<br>
 * 2012-05-15  2.1  isExpired() returns as bool; new method iExpired() to get <br>
 *                  expiration in sec<br>
 * 2014-02-27  2.2  - rename to AhCache<br>
 *                  - _cleanup checks with file_exists<br>
 * 2014-03-31  2.3  - added _setup() that to includes custom settings<br>
 *                  - limit number of files in cache directory<br>
 * 2019-11-24  2.4  - added getCachedItems() to get a filtered list of cache files<br>
 *                  - added remove file to make complete cache of a module invalid<br>
 *                  - rename var in cache.class_config.php to "$this->_sCacheDirDivider"<br>
 * 2019-11-26  2.5  - added getModules() to get a list of existing modules that stored<br>
 *                    a cached item<br>
 * --------------------------------------------------------------------------------<br>
 * @version 2.5
 * @author Axel Hahn
 * @link https://www.axel-hahn.de/docs/ahcache/index.htm
 * @license GPL
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL 3.0
 * @package Axels Cache
 */
if(!class_exists("AhCache")){
class AhCache {

    /**
     * a module name string is used as relative cache path
     * @var string 
     */
    var $sModule = '';

    /**
     * id of cachefile (filename will be generated from it)
     * @var string 
     */
    var $sCacheID = '';

    /**
     * where to store all cache data - it can be outside docRoot. If it is 
     * below docRoot think about forbidding access
     * If empty it will be set in the constructor to [webroot]/~cache/
     * or $TEMP/~cache/ for CLI
     * @var string 
     */
    private $_sCacheDir = '';
    // private $_sCacheDir='/tmp/';

    /**
     * absolute filename of cache file
     * @var string 
     */
    private $_sCacheFile = '';

    /**
     * divider to limit count of cachefiles
     * @var type 
     */
    private $_sCacheDirDivider = false;
    
    /**
     * fileextension for storing cachefiles (without ".")
     * @var string
     */
    private $_sCacheExt = 'cacheclass2';

    /**
     * Expiration timestamp; 
     * It will be calculated with current time + ttl in the write() method
     * TTL can be read with getExpire()
     * @var integer
     */
    private $_tsExpire = -1;

    /**
     * TTL (time to live) in s; 
     * TTL can be set in methods setTtl($iTtl) or write($data, $iTtl)
     * TTL can be read with getTtl()
     * @var integer
     */
    private $_iTtl = -1;

    /**
     * cachedata and file infos of cachefile (returned array of php function stat)
     * @var array
     */
    private $_aCacheInfos = array();

    /**
     * Full path to a cache remove file for the current module
     * @var string
     */
    private $_sCacheRemovefile = false;

    /* ----------------------------------------------------------------------
      constructor
      ---------------------------------------------------------------------- */

    /**
     * constructor
     * @param  string  $sModule   name of module or app that uses the cache
     * @param  string  $sCacheID  cache-id (must be uniq for a module; used to generate filename of cachefile)
     * @return boolean 
     */
    public function __construct($sModule = ".", $sCacheID = '.') {
        $this->sModule = $sModule;
        $this->sCacheID = $sCacheID;

        $this->_setup();

        $this->_getCacheFilename();
        $this->read();

        return true;
    }

    /* ----------------------------------------------------------------------
      private funtions
      ---------------------------------------------------------------------- */

    // ----------------------------------------------------------------------
    /**
     * init
     * - load custom config from cache.class_config.php 
     * - set a cache
     * - set remove file (if does not exist)
     * directory
     */
    private function _setup() {
        if (!$this->sModule){
            die("ERROR: no module was given.<br>");
        }
        if (!$this->sCacheID){
            die("ERROR: no id was given.<br>");
        }
        $sCfgfile="cache.class_config.php";
        if (file_exists(__DIR__ . "/".$sCfgfile)){
            include(__DIR__ . "/".$sCfgfile);
        }
        if (!$this->_sCacheDir) {
            if (getenv("TEMP"))
                $this->_sCacheDir = str_replace("\\", "/", getenv("TEMP"));
            if ($_SERVER['DOCUMENT_ROOT'])
                $this->_sCacheDir = $_SERVER['DOCUMENT_ROOT'];
            if (!$this->_sCacheDir)
                $this->_sCacheDir = ".";
            $this->_sCacheDir .= "/~cache";
        }
        $this->_sCacheRemovefile=$this->_sCacheDir.'/'.$this->sModule.'/__remove_me_to_make_caches_invalid__';
        if(!file_exists($this->_sCacheRemovefile)){
            if (!is_dir($this->_sCacheDir.'/'.$this->sModule)){
                if (!mkdir($this->_sCacheDir.'/'.$this->sModule, 0750, true)){
                    die("ERROR: unable to create directory " . $this->_sCacheDir.'/'.$this->sModule);
                }
            }
            touch($this->_sCacheRemovefile);
        }

    }

    // ----------------------------------------------------------------------
    /**
     * private function _getAllCacheData() - read cachedata and its meta infos
     * @since 2.0
     * @return     array  array with data, file stat
     */
    private function _getAllCacheData() {
        if (!$this->_sCacheFile){
            return false;
        }
        $this->_aCacheInfos = array();
        $aTmp = $this->_readCacheItem($this->_sCacheFile);

        if ($aTmp) {
            $this->_aCacheInfos['data'] = $aTmp['data'];
            $this->_iTtl = $aTmp['iTtl'];
            $this->_tsExpire = $aTmp['tsExpire'];
            $this->_aCacheInfos['stat'] = stat($this->_sCacheFile);

            // @see loadCachefile: it sets module + id to false
            $this->sModule=$this->sModule ? $this->sModule : $aTmp['module'];
            $this->sCacheID=$this->sCacheID ? $this->sCacheID : $aTmp['cacheid'];
        }
        return $this->_aCacheInfos;
    }

    /**
     * read a raw cache item and return it as hash
     *
     * @param string  $sFile  filename with full path
     * @return array|boolean
     */
    private function _readCacheItem($sFile) {
        chdir($this->_sCacheDir);
        if (file_exists($sFile)) {
            return unserialize(file_get_contents($sFile));
        }
        return false;
    }

    // ----------------------------------------------------------------------
    /**
     * private function _getCacheFilename() - get full filename of cachefile
     * @return     string  full filename of cachefile
     */
    private function _getCacheFilename() {
        $sMyFile=md5($this->sCacheID);
        if($this->_sCacheDirDivider && (int)$this->_sCacheDirDivider>0){
            $sMyFile=preg_replace('/([0-9a-f]{'.(int)$this->_sCacheDirDivider.'})/', "$1/", $sMyFile);
        }
        $sMyFile.=".".$this->_sCacheExt;
        $sMyFile=str_replace("/.", ".", $sMyFile);
        // $this->_sCacheFile = $this->_sCacheDir . "/" . $this->sModule . "/" . md5($this->sCacheID) . "." . $this->_sCacheExt;
        $this->_sCacheFile = $this->_sCacheDir . "/" . $this->sModule . "/" . $sMyFile;
    return $this->_sCacheFile;
    }

    /**
     * helper function - remove empty cache directories up to module cache dir
     *
     * @param string    $sDir
     * @param boolean   $bShowOutput   flag: show output? default: false (=no output)
     * @return void
     */
    private function _removeEmptyCacheDir($sDir, $bShowOutput=false){
        // echo $bShowOutput ? __METHOD__."($sDir)<br>\n" : '';
        if ($sDir > $this->_sCacheDir . "/" . $this->sModule){
            echo $bShowOutput ? "REMOVE DIR [$sDir] ... " : '';
            if (is_dir($sDir)){
                if (rmdir($sDir)){
                    chdir($this->_sCacheDir);
                    echo $bShowOutput ? "OK<br>\n" : '';
                    usleep(20000); // 0.02 sec
                    $this->_removeEmptyCacheDir(dirname($sDir), $bShowOutput);
                } else {
                    echo $bShowOutput ? "failed.<br>\n" : '';
                    return false;
                }
            } else {
                echo $bShowOutput ? "skip: not a directory.<br>\n" : '';
            }
            return true;
        }
        return false;
    }

    /* ----------------------------------------------------------------------
      public funtions
      ---------------------------------------------------------------------- */

    // ----------------------------------------------------------------------
    /**
     * Cleanup cache directory; delete all cachefiles older than n seconds
     * Other filetypes in the directory won't be touched.
     * Empty directories will be deleted.
     * 
     * Only the directory of the initialized module/ app will be deleted.
     * $o=new Cache("my-app"); $o->cleanup(60*60*24*3); 
     * 
     * To delete all cachefles of all modules you can use
     * $o=new Cache(); $o->cleanup(0); 
     * 
     * @since 2.0
     * @param int       $iSec          max age of cachefile; older cachefiles will be deleted
     * @param boolean   $bShowOutput   flag: show output? default: false (=no output)
     * @return     true
     */
    public function cleanup($iSec = false, $bShowOutput=false) {
        // quick and dirty
        $aData=$this->getCachedItems(false, array('ageOlder'=>$iSec));
        echo $bShowOutput ? 'CLEANUP  '.count($aData) ." files<br>\n" : '';
        if($aData){
            $aFiles=array_keys($aData);
            rsort($aFiles);
            foreach(array_keys($aData) as $sFile){
                echo $bShowOutput ? 'DELETE '.$sFile ."<br>\n" : '';
                unlink($sFile);
                $this->_removeEmptyCacheDir(dirname($sFile), $bShowOutput);
            }
        }
        return true;
    }

    // ----------------------------------------------------------------------
    /**
     * get an array with cached data elements.
     * Remark: this method should be used in an admin interface or cronjob only.
     * It makes a recursive filesystem scan and is quite slow.
     * @since 2.4
     *
     * @param string  $sDir     full path of cache dir; default: false (auto detect cache dir)
	 * @param array   $aFilter  filter; valid keys are
	 *                          - ageOlder         integer  return items that are older [n] sec
	 *                          - lifetimeBelow    integer  return items that expire in less [n] sec (or outdated)
	 *                          - lifetimeGreater  integer  return items that expire in more than [n] sec
	 *                          - ttlBelow         integer  return items with ttl less than [n] sec
	 *                          - ttlGreater       integer  return items with ttl more than [n] sec
	 *                          no filter returns all cached entries
     * @return void
     */
    public function getCachedItems($sDir=false, $aFilter=array()){
        $aReturn=array();
        $sDir=$sDir ? $sDir : $this->_sCacheDir . "/" . $this->sModule;
        if (!file_exists($sDir)) {
            // echo "\t Directory does not exist - [$sDir]";
            return false;
        }
        if (!($d = dir($sDir))) {
            // echo "\t Cannot open directory - [$sDir]</ul></li></ul>";
            return;
        }
        while ($entry = $d->read()) {
            $sEntry = $sDir . "/" . $entry;
            if (is_dir($sEntry) && $entry != '.' && $entry != '..') {
                $aReturn = array_merge($aReturn, $this->getCachedItems($sEntry, $aFilter));
            }

            if (file_exists($sEntry)) {
                $ext = pathinfo($sEntry, PATHINFO_EXTENSION);
                $ext = substr($sEntry, strrpos($sEntry, '.') + 1);

                $exts = explode(".", $sEntry);
                $n = count($exts) - 1;
                $ext = $exts[$n];

                if ($ext == $this->_sCacheExt) {

                    $aData=$this->_readCacheItem($sEntry);
                    unset($aData['data']);

                    $aData['_age']=date('U')-filemtime($sEntry);
                    $aData['_lifetime']=filemtime($sEntry) > filemtime($this->_sCacheRemovefile) ? $aData['tsExpire'] - date('U') : -1;

                    $bAdd=false;

                    if(isset($aFilter['ageOlder']) && ($aData['_age']>$aFilter['ageOlder'])){
                        $bAdd=true;
                    }
                    if(isset($aFilter['lifetimeBelow']) && ($aData['_lifetime']<$aFilter['lifetimeBelow'])){
                        $bAdd=true;
                    }
                    if(isset($aFilter['lifetimeGreater']) && ($aData['_lifetime']>$aFilter['lifetimeGreater'])){
                        $bAdd=true;
                    }
                    if(isset($aFilter['ttlBelow']) && ($aData['iTtl']<$aFilter['ttlBelow'])){
                        $bAdd=true;
                    }
                    if(isset($aFilter['ttlGreater']) && ($aData['iTtl']>$aFilter['ttlGreater'])){
                        $bAdd=true;
                    }

                    if(!is_array($aFilter) || !count($aFilter)){
                        $bAdd=true;
                    } 

                    if($bAdd){
                        $aReturn[$sEntry]=$aData;
                    }
                }
            }
        }
        return $aReturn;

    }

    // ----------------------------------------------------------------------
    /**
     * get a flat array of module names that saved a cache item already
     * @since 2.5
     * 
     * @return array
     */
    public function getModules(){
        $aReturn=array();
        foreach(glob($this->_sCacheDir.'/*') as $sEntry){
            if (is_dir($sEntry)){
                $aReturn[]=basename($sEntry);
            }
        }
        return $aReturn;
    }

    // ----------------------------------------------------------------------
    /**
     * delete a single cache item if it exist.
     * It returns true if a cache item was deleted. It returns false if it
     * does not exist (yet) or the deletion failed.
     * @return     boolean
     */
    public function delete() {
        if (!file_exists($this->_sCacheFile)){
            return false;
        }
        if (unlink($this->_sCacheFile)) {
            $this->_aCacheInfos['data'] = false;
            $this->_aCacheInfos['stat'] = false;
            return true;
        }
        return false;
    }
    // ----------------------------------------------------------------------
    /**
     * delete all existing cached items of the set module
     * Remark: this method should be used in an admin interface or cronjob only.
     * It makes a recursive filesystem scan and is quite slow.
     * 
     * @since 2.6
     * @param boolean   $bShowOutput   flag: show output? default: false (=no output)
     * @return     boolean
     */
    public function deleteModule($bShowOutput=false) {
        if (!$this->sModule){
            return false;
        }
        $this->cleanup(0, $bShowOutput);
        $this->removefileDelete();
        return rmdir($this->_sCacheDir . "/" . $this->sModule);
    }

    // ----------------------------------------------------------------------
    /**
     * public function dump() - dump variables of cache class
     * @return     true
     */
    public function dump() {
        echo "
            <!--<strong>".__METHOD__."()<br></strong>-->
            <strong>module: </strong>" . $this->sModule . "<br>
            <strong>ID: </strong>" . $this->sCacheID . "<br>
            <strong>filename: </strong>" . $this->_sCacheFile."<br>
            "
            ;
        if (file_exists($this->_sCacheFile)){
            echo "
                <strong>size: </strong>" . filesize($this->_sCacheFile). " byte<br>
                <strong>created: </strong>" . filemtime($this->_sCacheFile) . " (" . date("d.m.y - H:i:s", filemtime($this->_sCacheFile)) . ")<br>
                <strong>age: </strong>" . $this->getAge() . " s<br>
                <strong>ttl: </strong>" . $this->getTtl() . " s<br>
                ".($this->getTtl()<0 ? '' : "<strong>expires: </strong>" . $this->getExpire() . " (" . date("d.m.y - H:i:s", $this->getExpire()) . ")<br>")
                ."<br>
                <strong>data in the cache:</strong>
                <pre>"
            ;
            echo htmlentities(print_r($this->_aCacheInfos, 1));
            echo "</pre><hr>";
        } else  {
            echo "Cache file does not exist (yet).<br>
                Maybe the _sDivider was changed in another cache instance.<br>
                ";
        }
        return true;
    }

    // ----------------------------------------------------------------------
    /**
     * public function getCacheAge() - get age in seconds of exisiting cachefile
     * @return     int  age in seconds; -1 if cachefiles does not exist
     */
    public function getAge() {
        if (!isset($this->_aCacheInfos['stat'])){
            $this->_getAllCacheData();
        }
        if (!isset($this->_aCacheInfos['stat'])){
            return -1;
        }
        return date("U") - $this->_aCacheInfos['stat']['mtime'];
    }

    // ----------------------------------------------------------------------
    /**
     * public function getExpire() - get TS of cache expiration
     * @since 2.0
     * @return     int  unix ts of cache expiration
     */
    public function getExpire() {
        return $this->_tsExpire;
    }

    // ----------------------------------------------------------------------
    /**
     * public function getTtl() - get TTL of cache in seconds
     * @since 2.0
     * @return     int  get ttl of cache
     */
    public function getTtl() {
        return $this->_iTtl;
    }

    // ----------------------------------------------------------------------
    /**
     * public function isExpired() - cache expired? To check it 
     * you must use ttl while writing data, i.e.
     * $oCache->write($sData, $iTtl);
     * @since 2.0
     * @return     bool  cache is expired?
     */
    public function isExpired() {
        if (!$this->_tsExpire){
            return true;
        }
        $iAgeOfCache=$this->getAge();
        if($iAgeOfCache > (date("U")-filemtime($this->_sCacheRemovefile))){
			return true;
		}

        return ((date("U") - $this->_tsExpire)>0);
    }
    // ----------------------------------------------------------------------
    /**
     * public function iExpired() - get time in seconds when cachefile expires
     * you must use ttl while writing data, i.e.
     * $oCache->write($sData, $iTtl);
     * @since 2.1
     * @return     int  expired time in seconds; negative if cache is not expired
     */
    public function iExpired() {
        if (!$this->_tsExpire){
            return true;
        }
        return date("U") - $this->_tsExpire;
    }

    // ----------------------------------------------------------------------
    /**
     * function isNewerThanFile($sRefFile) - is the cache (still) newer than
     * a reference file? This function returns difference of mtime of both
     * files.
     * @since 2.0
     * @param   string   $sRefFile  local filename
     * @return  integer  time in sec how much the cache file is newer; negative if reference file is newer
     */
    public function isNewerThanFile($sRefFile) {
        if (!file_exists($sRefFile)){
            return false;
        }
        if (!isset($this->_aCacheInfos['stat'])){
            return false;
        }

        $aTmp = stat($sRefFile);
        $iTimeRef = $aTmp['mtime'];
        
        //echo $this->_sCacheFile."<br>".$this->_aCacheInfos['stat']['mtime']."<br>".$iTimeRef."<br>".($this->_aCacheInfos['stat']['mtime'] - $iTimeRef);
        return $this->_aCacheInfos['stat']['mtime'] - $iTimeRef;
    }

    // ----------------------------------------------------------------------
    /**
     * load cache item from a given file - this is like reverse engineering 
     * by reading data file; needed for a admin interface only
     * 
     * @since 2.6
     * @param string  $sFile  filename with full path
     * @return boolean
     */
    public function loadCachefile($sFile){
        $this->_sCacheFile=false;
        $this->sModule=false;
        $this->sCacheID=false;
        if(file_exists($sFile)){
            $this->_sCacheFile=$sFile;
            $this->_getAllCacheData();
            // reverse engineered _sCacheDirDivider
            $sRelfile=str_replace($this->_sCacheDir . "/" . $this->sModule, '', $this->_sCacheFile);
            $sTmp=preg_replace('#^\/([0-9a-f]*)([/\.].*)#', '$1', $sRelfile);
            $this->_sCacheDirDivider=strlen($sTmp);
            return true;
        }
        return false;
    }

    // ----------------------------------------------------------------------
    /**
     * public function getCacheData() - read cachedata if it exist
     * @return     various  cachedata or false if cache does not exist
     */
    public function read() {
        if (!isset($this->_aCacheInfos['data'])){
            $this->_getAllCacheData();
        }
        if (!isset($this->_aCacheInfos['data'])){
            return false;
        }
        return $this->_aCacheInfos['data'];
    }

    // ----------------------------------------------------------------------
    /**
     * delete module based remove file
     *
     * @since 2.6
     * @return boolean
     */
    public function removefileDelete(){
        if (file_exists($this->_sCacheRemovefile)){
            return unlink($this->_sCacheRemovefile);
        }
        return false;
    }
    // ----------------------------------------------------------------------
    /**
     * make all cache items invalid by touching the remove file
     *
     * @since 2.6
     * @return boolean
     */
    public function removefileTouch(){
        return touch($this->_sCacheRemovefile);
    }
    // ----------------------------------------------------------------------
    /**
     * public function setData($data) - set cachedata into cache object
     * data can be any serializable type, like string, array or object
     * Remark: You additionally need to call the write() method to store data in the filesystem
     * @since 2.0
     * @param      various  $data  data to store in cache
     * @return   boolean
     */
    public function setData($data) {
        return $this->_aCacheInfos['data'] = $data;
    }

    // ----------------------------------------------------------------------
    /**
     * public function setTtl() - set TTL of cache in seconds
     * You need to write the cache data to ap
     * Remark: You additionally need to call the write() method to store a new ttl value with 
     * data in the filesystem
     * @since 2.0
     * @param   int  $iTtl  ttl value in seconds
     * @return  int  get ttl of cache
     */
    public function setTtl($iTtl) {
        return $this->_iTtl = $iTtl;
    }

    // ----------------------------------------------------------------------
    /**
     * public function touch() - touch cachefile if it exist
     * For cached data a new expiration based on existing ttl will be set
     * @return boolean 
     */
    public function touch() {
        if (!file_exists($this->_sCacheFile)){
            return false;
        }

        // touch der Datei reicht nicht mehr, weil tsExpire verloren ginge
        if (!$this->_iTtl){
            $bReturn = touch($this->_sCacheFile);
        }
        else{
            $bReturn = $this->write();
        }

        $this->_getAllCacheData();

        return $bReturn;
    }

    // ----------------------------------------------------------------------
    /**
     * Write data into a cache. 
     * - data can be any serializable type, like string, array or object
     * - set ttl in s (from now); optional parameter
     * @param      various  $data    data to store in cache
     * @param      int      $iTtl    time in s if content cache expires (min. 0)
     * @return     bool     success  of write action
     */
    public function write($data = false, $iTtl = -1) {
        if (!$this->_sCacheFile)
            return false;

        $sDir = dirname($this->_sCacheFile);
        if (!is_dir($sDir)){
            if (!mkdir($sDir, 0750, true)){
                die("ERROR: unable to create directory " . $sDir);
            }
        }

        if (!$data === false){
            $this->setData($data);
        }

        if (!$iTtl >= 0) {
            $this->setTtl($iTtl);
        }

        $aTmp = array(
            'iTtl' => $this->_iTtl,
            'tsExpire' => date("U") + $this->_iTtl,
            'module' => $this->sModule,
            'cacheid' => $this->sCacheID,
            'data' => $this->_aCacheInfos['data'],
        );
        return file_put_contents($this->_sCacheFile, serialize($aTmp));
    }

}
}

// ----------------------------------------------------------------------
