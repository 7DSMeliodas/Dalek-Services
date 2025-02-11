<?php

/*				
//	(C) 2021 DalekIRC Services
\\				
//			dalek.services
\\				
//	GNU GENERAL PUBLIC LICENSE
\\				v3
//				
\\				
//				
\\	Title: User
//	
\\	Desc: User class for easy callin's lol
//	
\\	
//	
\\	
//	
\\	Version: 1
//				
\\	Author:	Valware
//				
*/


class User {
	
	public function __construct($user)
	{
		global $cf;
		$u = find_person($user);
		
		if (!$u)
			$this->IsUser = false;
		else 
			$this->IsUser = true;
		if ($this->IsUser)
		{
			$this->nick = $u['nick'];
			$this->uid = $u['UID'];
			$this->ts = $u['timestamp'];
			$this->ident = $u['ident'];
			$this->usermode = $u['usermodes'];
			$this->realhost = $u['realhost'];
			$this->gecos = $u['gecos'];
			$this->cloak = $u['cloak'];
			$this->ip = $u['ip'];
			$this->channels = get_ison($this->uid);
			$this->account = (isset($u['account'])) ? $u['account'] : false;
			$this->fingerprint = (isset($u['fingerprint'])) ? $u['fingerprint'] : false;
			$this->sid = $u['SID'];
			$this->tls = (strpos($u['usermodes'],"z")) ? true : false;
			$this->last = $u['last'];
			$s = find_serv($u['SID']);
			if ($s)
				$this->server = $s['servername'];
			$this->tls = (strpos($u['usermodes'],"z")) ? true : false;
			$this->last = $u['last'];
			$this->meta = new UserMeta($this);
		}
		if (!$this->IsUser)
			if (($s = find_serv($user)) !== false)
			{
				$this->IsServer = true;
				$this->nick = $s['servername'];
				$this->uid = $s['sid'];
			}
		else {
			$this->IsServer = true;
			$this->nick = $cf['servicesname'];
			$this->uid = $cf['sid'];
		}
	}
	function NewNick($nick)
	{
		global $servertime,$cf;
		
		if (!$this->IsUser)
		{ 
			return false;
		}
		if (!validate_nick($nick))
		{
			return false;
		}
		S2S(":".$cf['sid']." SVSNICK ".$this->nick." $nick $servertime");
		update_nick($this->uid,$nick,$servertime);
		$this->nick = $nick;
	}
	function SetMode($mode)
	{
		$charToAdd = NULL;
		$charToDel = NULL;
		if ($mode[0] !== "+" && $mode[0] !== "-")
		{
			return false;
		}
		
		if (!isset($mode[1]))
		{
			return false;
		}
		for ($i = 0; isset($mode[$i]); $i++)
		{
			$tok = $mode[$i];
			
			if ($tok == "+")
			{
				$switch = "add";
				$i++;
			}
			
			elseif ($tok == "-")
			{
				echo "Changed switch to del\n";
				$switch = "del";
				$i++;
			}
			
			if ($switch == "add")
			{
				$charToAdd .= $mode[$i];
			}
			elseif ($switch == "del")
			{
				$charToDel .= $mode[$i];
			}
			if (!$charToDel && !$charToAdd)
			{
				return false;
			}
		}
	
		$validate = validate_modechange($this->usermode,$charToAdd,$charToDel);
		if (!$validate)
		{		
			return;
		}
		sendumode($this->uid,$validate['ToSet']);
		update_usermode($this->uid,$validate['NewModes']);
		return true;
	}
	function get_user_meta()
	{
		$meta = array();

		$conn = sqlnew();
		$prep = $conn->prepare("SELECT * FROM dalek_user_meta WHERE UID = ?");
		$prep->bind_param("s",$this->uid);
		$prep->execute();
		$result = $prep->get_result();
		if (!$result)
		{
			$prep->close();
			return NULL;
		}
		while($row = $result->fetch_assoc())
			$this->$meta->$row['meta_key'] = $row['meta_data'];

		$prep->close();
		return $meta;
	}
	function exit()
	{
		global $sql;
		$sql::user_delete($this->uid);
	}
		
}



class UserMeta {

	function __construct(User $nick)
	{

		$conn = sqlnew();
		$prep = $conn->prepare("SELECT * FROM dalek_user_meta WHERE UID = ?");
		$prep->bind_param("s",$nick->uid);
		$prep->execute();
		$result = $prep->get_result();
		if (!$result)
		{
			$prep->close();
			return NULL;
		}
		while($row = $result->fetch_assoc())
			$this->{$row['meta_key']} = $row['meta_data'];

		$prep->close();
	}
}
function sendumode($uid,$mode)
{
	global $serv;
	
	$serv->svs2mode($uid,$mode);
	return;
}

function validate_modechange($modesThatWeHave,$modesToAdd,$modesToDel)
{
	$AddModeString = NULL;
	$DelModeString = NULL;

	$NewModes = $modesThatWeHave;
	
	for ($i = 0; $i < strlen($modesToAdd); $i++)
	{

		if (!strpos($modesThatWeHave,$modesToAdd[$i]))
		{
			$SetTheMode .= $modesToAdd[$i];
		}
	}
	
	if (isset($SetTheMode))
	{	
		$AddModeString = "+".$SetTheMode ?? NULL;
		$NewModes = $modesThatWeHave.$SetTheMode;
	}
	
	for ($i = 0; $i < strlen($modesToDel); $i++)
	{
		
		if (strpos($NewModes,$modesToDel[$i]))
		{
			$UnsetTheMode .= $modesToDel[$i];
		}
	}
	
	if (isset($UnsetTheMode))
	{
		$DelModeString = "-".$UnsetTheMode ?? NULL;
		
		for ($i = 0; $i < strlen($UnsetTheMode); $i++)
		{
			if (strpos($NewModes,$UnsetTheMode[$i]))
			{
				$NewModes = str_replace($UnsetTheMode[$i],"",$NewModes);
			}
		}
	}
	$TheEntireStringOfModesThatWeAreGoingToSetOnTheUser = $AddModeString.$DelModeString;
	if (!$TheEntireStringOfModesThatWeAreGoingToSetOnTheUser)
	{
		return false;
	}
	$return = [
		'ToSet' => $TheEntireStringOfModesThatWeAreGoingToSetOnTheUser,
		'NewModes' => $NewModes
	];
	
	return $return;
}
		

function validate_modechar($string)
{
	if (!s($string))
	{
		return false;
	}
}

function validate_nick($string)
{
	
	for ($i = 0; $i < strlen($string); $i++){
		$val = $string[$i];
		if ($i == 0){
			
			if (!ctype_alpha($val)){
				
				if ((ord($val) >= 91 && ord($val) <= 96) || (ord($val) >= 123 && ord($val) <= 125)){ continue; }
				else { return false; }
			}
		}
		else {
			if ((ord($val) >= 65 && ord($val) <= 125) || (ord($val) >= 48 && ord($val) <= 57) || ord($val) == 45){ continue; }
				else { return false; }
		}
	}
	return true;
}
