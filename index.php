<?php
/*
 *
 *     P R E   D I S P A T C H E R
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

	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);

	require_once('classes/pre_dispatcher.class.php');


// ======================================================================
//
// FUNCTIONS
//
// ======================================================================


	function handleOutput($buffer){ 
		global $oAhd;
		
		$oAhd->doCache(array(
			'url'=>$oAhd->getRefreshUrl(),
			'header'=>apache_request_headers(),
			'content'=>$buffer,
		));
		$buffer=str_replace(
			'</body',
			$oAhd->renderHeaders().'</body',
			$buffer
		);

		return $buffer;
	} 

// ======================================================================
//
// MAIN
//
// ======================================================================

	global $oAhd;
	$oAhd=new preDispatcher();
	$oAhd->getCachedContent();
	
// ----------------------------------------------------------------------
// no cache? --> run normal request
// ----------------------------------------------------------------------

	$oAhd->removeDispatcherParams();
	$oAhd->addInfo('--> making a request');
	ob_start("handleOutput", 0, false);
	require __DIR__.'/../../concrete/dispatcher.php';
	ob_end_flush();
	die();
