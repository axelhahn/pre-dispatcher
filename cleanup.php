<?php
/*
 *
 *     P R E   D I S P A T C H E R    CLEANUP
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
    $oAhd=new preDispatcher();
    
    // delete all cache files older 14d
	$oAhd->cleanup(60*60*24*14, true);
    $oAhd->renderHeaders();
