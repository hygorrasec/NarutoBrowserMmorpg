<?php 

include('lib.php');

$link = opendb();
$controlquery = doquery("SELECT * FROM {{table}} WHERE id='1' LIMIT 1", "control");
$controlrow = mysql_fetch_array($controlquery);



$nomechar = $_GET['nomechar'];






    $userquery = doquery("SELECT * FROM {{table}} WHERE charname='$nomechar' LIMIT 1", "users");
    if (mysql_num_rows($userquery) == 1) { $userrow = mysql_fetch_array($userquery); } else { echo "Nenhum usu�rio."; die();}
    
    // Format various userrow stuffs.
    $userrow["experience"] = number_format($userrow["experience"]);
    $userrow["gold"] = number_format($userrow["gold"]);
    if ($userrow["expbonus"] > 0) { 
        $userrow["plusexp"] = "<span class=\"light\">(+".$userrow["expbonus"]."%)</span>"; 
    } elseif ($userrow["expbonus"] < 0) {
        $userrow["plusexp"] = "<span class=\"light\">(".$userrow["expbonus"]."%)</span>";
    } else { $userrow["plusexp"] = ""; }
    if ($userrow["goldbonus"] > 0) { 
        $userrow["plusgold"] = "<span class=\"light\">(+".$userrow["goldbonus"]."%)</span>"; 
    } elseif ($userrow["goldbonus"] < 0) { 
        $userrow["plusgold"] = "<span class=\"light\">(".$userrow["goldbonus"]."%)</span>";
    } else { $userrow["plusgold"] = ""; }
    
    $levelquery = doquery("SELECT ". $userrow["charclass"]."_exp FROM {{table}} WHERE id='".($userrow["level"]+1)."' LIMIT 1", "levels");
    $levelrow = mysql_fetch_array($levelquery);
    $userrow["nextlevel"] = number_format($levelrow[$userrow["charclass"]."_exp"]);

    if ($userrow["charclass"] == 1) { $userrow["charclass"] = $controlrow["class1name"]; }
    elseif ($userrow["charclass"] == 2) { $userrow["charclass"] = $controlrow["class2name"]; }
    elseif ($userrow["charclass"] == 3) { $userrow["charclass"] = $controlrow["class3name"]; }
    
    if ($userrow["difficulty"] == 1) { $userrow["difficulty"] = $controlrow["diff1name"]; }
    elseif ($userrow["difficulty"] == 2) { $userrow["difficulty"] = $controlrow["diff2name"]; }
    elseif ($userrow["difficulty"] == 3) { $userrow["difficulty"] = $controlrow["diff3name"]; }
    
	//sefor administrador
	if ($userrow["authlevel"] == 1) {$userrow["adm"] = "<font color=green>Administrador</font><br>";}
	elseif ($userrow["acesso"] == 2){$userrow["adm"] = "<font color=orange>Tutor</font><br>";}
	elseif ($userrow["acesso"] == 3){$userrow["adm"] = "<font color=blue>GameMaster</font><br>";}
	else {$userrow["adm"] = "";}
	
		//durabilidade
	$durabm = explode(",",$userrow["durabilidade"]);
	for ($i = 1; $i < 7; $i ++){
	if ($durabm[$i] == "X"){$durabm[$i] = "*";}
	$userrow[durabm.$i] = $durabm[$i];
	}
	
	
	$spellquery = doquery("SELECT id,name FROM {{table}}","spells");
    $userspells = explode(",",$userrow["spells"]);
    $userrow["magiclist"] = "";
    while ($spellrow = mysql_fetch_array($spellquery)) {
        $spell = false;
        foreach($userspells as $a => $b) {
            if ($b == $spellrow["id"]) { $spell = true; }
        }
        if ($spell == true) {
            $userrow["magiclist"] .= $spellrow["name"]."<br />";
        }
    }
    if ($userrow["magiclist"] == "") { $userrow["magiclist"] = "None"; }
	
	if ($userrow["senjutsuhtml"] != ""){ $userrow["magiclist"] = "<font color=darkgreen>Senjutsu</font><br>".$userrow["magiclist"];}
	if ($userrow["jutsudebuscahtml"] != ""){ $userrow["magiclist"] = "<font color=darkgreen>Jutsu de Busca</font><br>".$userrow["magiclist"];}
	
	    // Make page tags for XHTML validation.
    $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>\n"
    . "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"DTD/xhtml1-transitional.dtd\">\n"
    . "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">\n";
	

	
	$embaixo = "<center><font color=\"white\">Link do Personagem:</font><br><input type=\"text\" size=\"20\" value=\"http://nigeru.com/narutorpg/mostrarchar.php?nomechar=".$userrow["charname"]."\"></center>";
    $charsheet = gettemplate("onlinechar");
    $page = $xml . gettemplate("minimal").$embaixo;
    $array = array("content"=>parsetemplate($charsheet, $userrow), "title"=>"Informa��o do Personagem");
    echo parsetemplate($page, $array);
    die();
	
    



?>