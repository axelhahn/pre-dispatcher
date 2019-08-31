<?php
/*
 *
 *     P R E   D I S P A T C H E R    REFRESH
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

require_once('pre_dispatcher.class.php');
require_once('cache.class.php');
$iLifetimeBelow=60*60*4;

// ----------------------------------------------------------------------
// FUNCTIONS
// ----------------------------------------------------------------------

    function httpGet($url, $bHeaderOnly = false) {
        $ch = curl_init($url);
        if ($bHeaderOnly) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'php-curl :: preDispatcher refresh');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $res = curl_exec($ch);
        curl_close($ch);
        return ($res);
    }


// ----------------------------------------------------------------------
// MAIN
// ----------------------------------------------------------------------

    $iScriptStart=microtime(true);
    echo "\n";
    echo "PREDISPATCHER :: REFRESH - ".date("Y-m-d H:i:s")."\n";
    echo "\n";

    $oAhd=new preDispatcher(); 
    
    $aItems=$oAhd->getListOfCachefiles(array(
        'lifetimeBelow'=>$iLifetimeBelow,
    ));
    echo "Found urls to refresh (lifetime < $iLifetimeBelow sec): ".count($aItems)."\n\n";

    if (count($aItems)){
        $iCounter=0;
        foreach($aItems as $aItem){
            $iCounter++;

            $oCacheItem=new AhCache($aItem['module'], $aItem['cacheid']); 
            $aData=$oCacheItem->read();
            
            echo date("H:i:s") .' | '. $iCounter . " | " . $aItem['_lifetime'].'s left | '. $aData['url']."... ";
            $iStart=microtime(true);
            $res=httpGet($aData['url']);
            echo " (".(number_format(microtime(true)-$iStart, 3))."s)\n";
        }
    }
    echo "\nTotal time: ".(number_format(microtime(true)-$iScriptStart, 3))."s\n";
    echo "Bye.\n";
