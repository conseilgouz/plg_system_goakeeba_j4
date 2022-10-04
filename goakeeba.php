<?php
/** GoAkeeba
*
* Version			: 2.0.0
* Package			: Joomla 4.0
* copyright 		: Copyright (C) 2021 ConseilGouz. All rights reserved.
* license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
* lance akeebabackup                    
*/

defined('_JEXEC') or die;
use Joomla\Registry\Registry;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Filesystem\Folder;

$input = Factory::getApplication()->input;
$pJform = $input->get('jform', '', 'array');

if(isset($pJform['params']['freq'])){ // modification des parametres: on lance la creation du fichier "declencheur"
	$folder = JPATH_SITE.'/plugins/system/goakeeba';
	$chkfile = 'goakeeba_checkfile';
	$fnames=(Folder::files($folder,$chkfile.'.*'));
	$fname=array_pop($fnames);
	if($fname)unlink($folder.'/'.$fname); 
	$dayssecs=$pJform['params']['time'];
	$dayssecs=strtotime(date('Y-m-d').' '.$dayssecs);
	if(!$dayssecs)$dayssecs=0;else $dayssecs-=strtotime(date('Y-m-d'));
	$time=time();
	$round=strtotime(date('Y-m-d',$time));
	$backuptime=$round+$dayssecs;
	$xdays=(int)$pJform['params']['xdays'];
	if($xdays==0)$xdays=1;
	if($xdays==1){
		$interval=(int)$pJform['params']['freq'];
		if($interval==0)
			$interval=86400;
		else 
			$interval=(int)(86400/$interval);
		while($backuptime<$time){
			$backuptime+=$interval;
		}
	}else{
		$interval=$xdays*86400;
		if($backuptime<$time)$backuptime+=86400;
	}
	$fname=$folder .'/'. $chkfile.'.'.$backuptime;
	if(!touch($fname))return;
	$f=fopen($fname,'w');fputs($f,'w'.$interval);fclose($f);
}

class PlgSystemGoAkeeba extends CMSPlugin
{
	function onAfterInitialise()
	{ 
		$folder = JPATH_SITE.'/plugins/system/goakeeba';
		$chkfile = 'goakeeba_checkfile';
		$create=false;
		$fnames=Folder::files($folder,$chkfile.'.*');
		$fname=array_pop($fnames);
		if(!$fname)return;
		$backuptime=substr($fname,-10,10);
		$interval=file_get_contents($folder.'/'.$fname);
		if($interval[0]=='w'){
			$interval=(int)substr($interval,1);
			if ($this->params->get('debug','0') == "1") { // fire on update
				$create=true;
			}
		}
		$time=time();
		if (($time>$backuptime)||$create) { // go
			unlink($folder.'/'.$fname);
			while($backuptime<$time)$backuptime+=$interval;
			$fname=$folder.'/'.$chkfile.'.'.$backuptime;
			if(!touch($fname))return;
			$f=fopen($fname,'w');fputs($f,$interval);fclose($f);
			$this->goAkeeba();
		}
	    return true;		
    }

	function goAkeeba() {
		$app = Factory::getApplication();
		$profile = $this->params->get('profile',1);
		$pass = $this->params->get('pass','');
		$uri = Uri::getInstance();
		$redirectUri = Uri::root() . 'index.php?option=com_akeebabackup&view=backup&profile='.$profile.'&key='.$pass;
		$mode = $this->params->get('mode','curl');
		if ($mode == "redir") { // mode redirect
			if ($this->params->get('log') == "1") {
				JLog::addLogger(array('text_file' => 'goakeeba.trace.log'), JLog::INFO);
				JLog::add($res, JLog::INFO, "go akeeba : redir");
			}
			$app->redirect($redirectUri);
			return true;
		} else { // mode curl
			$curl=curl_init();
			curl_setopt($curl,CURLOPT_URL,$redirectUri);
			curl_setopt($curl,CURLOPT_FOLLOWLOCATION,TRUE);
			curl_setopt($curl,CURLOPT_MAXREDIRS,10000); # Fix by Nicholas
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			$buffer = curl_exec($curl);
			curl_close($curl);
			if (empty($buffer)) {
				$res =  "Sorry, the backup didn't work.";
			} else {
				$res = $buffer;
			}
			if ($this->params->get('log') == "1") {
				JLog::addLogger(array('text_file' => 'goakeeba.trace.log'), JLog::INFO);
				JLog::add($res, JLog::INFO, "go akeeba");
			}
		}
	}
}