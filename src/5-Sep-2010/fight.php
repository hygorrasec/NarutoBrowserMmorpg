<?php // fight.php :: Handles all fighting action.

function fight() { // One big long function that determines the outcome of the fight.
    
	$avisochakra = $_GET['avisochakra'];
	
	
	
    global $userrow, $controlrow;
    if ($userrow["currentaction"] != "Fighting") { display("Tentativa de trapa�a detectada.", "Error"); }
    $pagearray = array();
    $playerisdead = 0;
	
	//Graficos
		$pagearray["grafico"] = $userrow["avatar"]."_stance.gif";
		 //frase da batalha
   global $indexconteudo;
   $pagearray["indexconteudo"] = $indexconteudo;
    
    $pagearray["magiclist"] = "";
    $userspells = explode(",",$userrow["spells"]);
    $spellquery = 
	doquery("SELECT id,name,mp FROM {{table}}", "spells");
    while ($spellrow = mysql_fetch_array($spellquery)) {
        $spell = false;
        foreach ($userspells as $a => $b) {
            if ($b == $spellrow["id"]) { $spell = true; }
        }
        if ($spell == true) {
		$chakra = $spellrow["mp"];
		           $pagearray["magiclist"] .= "<option value=\"".$spellrow["id"]."\">".$spellrow["name"]." _ CH: $chakra</option>\n";
        }
        unset($spell);
    }
    if ($pagearray["magiclist"] == "") { $pagearray["magiclist"] = "<option value=\"0\">None</option>\n"; }
    $magiclist = $pagearray["magiclist"];
    
    $chancetoswingfirst = 1;

    // First, check to see if we need to pick a monster.
    if ($userrow["currentfight"] == 1) {
        $aux1 = $userrow["latitude"];
		$aux2 = $userrow["longitude"];
        if ($aux1 < 0) { $aux1 *= -1; } // Equalize negatives. Mudei pro mapa n�o inverter...
        if ($aux2 < 0) { $aux2 *= -1; } // Ditto.
        $maxlevel = floor(max($aux1+5, $aux2+5) / 5); // One mlevel per five spaces.
        if ($maxlevel < 1) { $maxlevel = 1; }
        $minlevel = $maxlevel - 2;
        if ($minlevel < 1) { $minlevel = 1; }
        
        
        // Pick a monster.
        $monsterquery = doquery("SELECT * FROM {{table}} WHERE level>='$minlevel' AND level<='$maxlevel' ORDER BY RAND() LIMIT 1", "monsters");
        $monsterrow = mysql_fetch_array($monsterquery);
        $userrow["currentmonster"] = $monsterrow["id"];
        $userrow["currentmonsterhp"] = rand((($monsterrow["maxhp"]/5)*4),$monsterrow["maxhp"]);
        if ($userrow["difficulty"] == 2) { $userrow["currentmonsterhp"] = ceil($userrow["currentmonsterhp"] * $controlrow["diff2mod"]); }
        if ($userrow["difficulty"] == 3) { $userrow["currentmonsterhp"] = ceil($userrow["currentmonsterhp"] * $controlrow["diff3mod"]); }
        $userrow["currentmonstersleep"] = 0;
        $userrow["currentmonsterimmune"] = $monsterrow["immune"];
        
        $chancetoswingfirst = rand(1,10) + ceil(sqrt($userrow["dexterity"]));
        if ($chancetoswingfirst > (rand(1,7) + ceil(sqrt($monsterrow["maxdam"])))) { $chancetoswingfirst = 1; } else { $chancetoswingfirst = 0; }
        
        unset($monsterquery);
        unset($monsterrow);
        
    }
    
    // Next, get the monster statistics.
    $monsterquery = doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentmonster"]."' LIMIT 1", "monsters");
    $monsterrow = mysql_fetch_array($monsterquery);
    $pagearray["monstername"] = $monsterrow["name"];
    
    // Do run stuff.
    if (isset($_POST["run"])) {

        $chancetorun = rand(4,10) + ceil(sqrt($userrow["dexterity"]));
        if ($chancetorun > (rand(1,5) + ceil(sqrt($monsterrow["maxdam"])))) { $chancetorun = 1; } else { $chancetorun = 0; }
        
        if ($chancetorun == 0) { 
            $pagearray["yourturn"] = "Voc� tentou fugir mas foi bloqueado pela frente!<br /><br />";
            $pagearray["monsterhp"] = "HP do Inimigo: " . $userrow["currentmonsterhp"] . "<br /><br />";
            $pagearray["monsterturn"] = "";
			
            if ($userrow["currentmonstersleep"] != 0) { // Check to wake up.
                $chancetowake = rand(1,15);
				//atributo inteligencia
				$chancetowake = $chancetowake + floor($userrow["inteligencia"]/100);
                if ($chancetowake > $userrow["currentmonstersleep"]) {
                    $userrow["currentmonstersleep"] = 0;
                    $pagearray["monsterturn"] .= "O inimigo acordou.<br />";
                } else {
                    $pagearray["monsterturn"] .= "O inimigo continua dormindo.<br />";
                }
            }
            if ($userrow["currentmonstersleep"] == 0) { // Only do this if the monster is awake.
                $tohit = ceil(rand($monsterrow["maxdam"]*.5,$monsterrow["maxdam"]));
                if ($userrow["difficulty"] == 2) { $tohit = ceil($tohit * $controlrow["diff2mod"]); }
                if ($userrow["difficulty"] == 3) { $tohit = ceil($tohit * $controlrow["diff3mod"]); }
                $toblock = ceil(rand($userrow["defensepower"]*.75,$userrow["defensepower"])/4);
                $tododge = rand(1,150);
				//atributo agilidade antes: $tododge <= sqrt($userrow["dexterity"])
			$tododge = $tododge - floor($userrow["agilidade"]*2/100);
                if ($tododge <= sqrt($userrow["dexterity"])) {
                    $tohit = 0; $pagearray["monsterturn"] .= "Voc� fugiu de um ataque. Nenhum dano foi recebido.<br />";
                    $persondamage = 0;
                } else {
                    $persondamage = $tohit - $toblock;
                    if ($persondamage < 1) { $persondamage = 1; }
                    if ($userrow["currentuberdefense"] != 0) {
                        $persondamage -= ceil($persondamage * ($userrow["currentuberdefense"]/100));
                    }
                    if ($persondamage < 1) { $persondamage = 1; }
                }
                $pagearray["monsterturn"] .= "O inimigo provocou no total, $persondamage de dano.<br /><br />";
                $userrow["currenthp"] -= $persondamage;
                if ($userrow["currenthp"] <= 0) {
                    $newgold = ceil($userrow["gold"]/2);
                    $newhp = ceil($userrow["maxhp"]/4);
                    $updatequery = doquery("UPDATE {{table}} SET currenthp='$newhp',currentaction='In Town',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',latitude='0',longitude='0',gold='$newgold' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
                    $playerisdead = 1;
                }
            }
        }

        $updatequery = doquery("UPDATE {{table}} SET currentaction='Exploring' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
        header("Location: index.php");
        die();
		
			
        
    // Do fight stuff.
    } elseif (isset($_POST["fight"])) {
        
		//Graficos
		$pagearray["grafico"] = $userrow["avatar"]."_ataque.gif";
		
        // Your turn.
        $pagearray["yourturn"] = "";
        $tohit = ceil(rand($userrow["attackpower"]*.75,$userrow["attackpower"])/3);
        $toexcellent = rand(1,150);
		
		//atributo determinacao // antes $toexcellent <= sqrt($userrow["strength"])
		$determinacao = sqrt($userrow["strength"]) + ($userrow["determinacao"]*2/100);
        if ($toexcellent <= $determinacao) { $tohit *= 2; $pagearray["yourturn"] .= "Hit excelente!<br />"; }
        $toblock = ceil(rand($monsterrow["armor"]*.75,$monsterrow["armor"])/3);        
        $tododge = rand(1,200);
		//atributo precisao //  antes $tododge <= sqrt($monsterrow["armor"])
		$tododge = $tododge + floor($userrow["precisao"]*3/100);
        if ($tododge <= sqrt($monsterrow["armor"])) { 
            $tohit = 0; $pagearray["yourturn"] .= "O inimigo est� fugindo. Nenhum dano foi recebido por ele.<br />"; 
            $monsterdamage = 0;
        } else {
            $monsterdamage = $tohit - $toblock;
            if ($monsterdamage < 1) { $monsterdamage = 1; }
            if ($userrow["currentuberdamage"] != 0) {
                $monsterdamage += ceil($monsterdamage * ($userrow["currentuberdamage"]/100));
            }
        }
        $pagearray["yourturn"] .= "Voc� atacou o inimigo provocando $monsterdamage de dano.<br /><br />";
        $userrow["currentmonsterhp"] -= $monsterdamage;
        $pagearray["monsterhp"] = "HP do Inimigo: " . $userrow["currentmonsterhp"] . "<br /><br />";
        if ($userrow["currentmonsterhp"] <= 0) {
            $updatequery = doquery("UPDATE {{table}} SET currentmonsterhp='0' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
            header("Location: index.php?do=victory");
            die();
        }
        
        // Monster's turn.
        $pagearray["monsterturn"] = "";
        if ($userrow["currentmonstersleep"] != 0) { // Check to wake up.
            $chancetowake = rand(1,15);
			//atributo inteligencia
				$chancetowake = $chancetowake + floor($userrow["inteligencia"]/100);
            if ($chancetowake > $userrow["currentmonstersleep"]) {
                $userrow["currentmonstersleep"] = 0;
                $pagearray["monsterturn"] .= "O inimigo acordou.<br />";
            } else {
                $pagearray["monsterturn"] .= "O inimigo continua a dormir.<br />";
            }
        }
        if ($userrow["currentmonstersleep"] == 0) { // Only do this if the monster is awake.
            $tohit = ceil(rand($monsterrow["maxdam"]*.5,$monsterrow["maxdam"]));
            if ($userrow["difficulty"] == 2) { $tohit = ceil($tohit * $controlrow["diff2mod"]); }
            if ($userrow["difficulty"] == 3) { $tohit = ceil($tohit * $controlrow["diff3mod"]); }
            $toblock = ceil(rand($userrow["defensepower"]*.75,$userrow["defensepower"])/4);
            $tododge = rand(1,150);
			//atributo agilidade antes: $tododge <= sqrt($userrow["dexterity"])
			$tododge = $tododge - floor($userrow["agilidade"]*2/100);
            if ($tododge <= sqrt($userrow["dexterity"])) {
                $tohit = 0; $pagearray["monsterturn"] .= "Voc� fugiu do ataque do inimigo. Nenhum dano foi causado.<br />";
                $persondamage = 0;
            } else {
                $persondamage = $tohit - $toblock;
                if ($persondamage < 1) { $persondamage = 1; }
                if ($userrow["currentuberdefense"] != 0) {
                    $persondamage -= ceil($persondamage * ($userrow["currentuberdefense"]/100));
                }
                if ($persondamage < 1) { $persondamage = 1; }
            }
            $pagearray["monsterturn"] .= "O inimigo atacou voc�, provocando $persondamage de dano.<br /><br />";
            $userrow["currenthp"] -= $persondamage;
            if ($userrow["currenthp"] <= 0) {
                $newgold = ceil($userrow["gold"]/2);
                $newhp = ceil($userrow["maxhp"]/4);
                $updatequery = doquery("UPDATE {{table}} SET currenthp='$newhp',currentaction='In Town',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',latitude='0',longitude='0',gold='$newgold' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
                $playerisdead = 1;
            }
        }
        
    // Do spell stuff.
    } elseif (isset($_POST["spell"])) {
	
	//Graficos
		$pagearray["grafico"] = $userrow["avatar"]."_jutsu.gif";
		
		// Your turn.
        $pickedspell = $_POST["userspell"];
        if ($pickedspell == 0) { header("Location: /narutorpg/index.php?do=fight&avisochakra=Voc� deve selecionar um Jutsu primeiro.");die();}
        
        $newspellquery = doquery("SELECT * FROM {{table}} WHERE id='$pickedspell' LIMIT 1", "spells");
        $newspellrow = mysql_fetch_array($newspellquery);
        $spell = false;
        foreach($userspells as $a => $b) {
            if ($b == $pickedspell) { $spell = true; }
        }
        if ($spell != true) { display("Voc� ainda n�o aprendeu esse Jutsu. Por favor volte e tente novamente.", "Error"); die(); }
        if ($userrow["currentmp"] < $newspellrow["mp"]) { header("Location: /narutorpg/index.php?do=fight&avisochakra=Voc� n�o tem chakra suficiente para usar o(a) ".$newspellrow["name"].".");die(); } //chakra jutsu.
        
        if ($newspellrow["type"] == 1) { // Heal spell.
            $newhp = $userrow["currenthp"] + $newspellrow["attribute"];
            if ($userrow["maxhp"] < $newhp) { $newspellrow["attribute"] = $userrow["maxhp"] - $userrow["currenthp"]; $newhp = $userrow["currenthp"] + $newspellrow["attribute"]; }
            $userrow["currenthp"] = $newhp;
            $userrow["currentmp"] -= $newspellrow["mp"];
            $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"]." e ganhou ".$newspellrow["attribute"]." Pontos de Vida.<br /><br />";
        } elseif ($newspellrow["type"] == 2) { // Hurt spell.
            if ($userrow["currentmonsterimmune"] == 0) {
                $monsterdamage = rand((($newspellrow["attribute"]/6)*5), $newspellrow["attribute"]);
                $userrow["currentmonsterhp"] -= $monsterdamage;
                $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"]." e causou $monsterdamage de dano.<br /><br />";
            } else {
                $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"].", mas o inimigo � imune � seu Jutsu.<br /><br />";
            }
            $userrow["currentmp"] -= $newspellrow["mp"];
        } elseif ($newspellrow["type"] == 3) { // Sleep spell.
            if ($userrow["currentmonsterimmune"] != 2) {
                $userrow["currentmonstersleep"] = $newspellrow["attribute"];
                $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"].". O inimigo est� dormindo.<br /><br />";
            } else {
                $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"].", mas o inimigo � imune � ele.<br /><br />";
            }
            $userrow["currentmp"] -= $newspellrow["mp"];
        } elseif ($newspellrow["type"] == 4) { // +Damage spell.
            $userrow["currentuberdamage"] = $newspellrow["attribute"];
            $userrow["currentmp"] -= $newspellrow["mp"];
            $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"]." e ganhou ".$newspellrow["attribute"]."% de dano at� o fim da batalha.<br /><br />";
        } elseif ($newspellrow["type"] == 5) { // +Defense spell.
            $userrow["currentuberdefense"] = $newspellrow["attribute"];
            $userrow["currentmp"] -= $newspellrow["mp"];
            $pagearray["yourturn"] = "Voc� usou: ".$newspellrow["name"]." e ganhou ".$newspellrow["attribute"]."% de defesa at� o fim da batalha.<br /><br />";            
        }
            
        $pagearray["monsterhp"] = "HP do Inimigo: " . $userrow["currentmonsterhp"] . "<br /><br />";
        if ($userrow["currentmonsterhp"] <= 0) {
            $updatequery = doquery("UPDATE {{table}} SET currentmonsterhp='0',currenthp='".$userrow["currenthp"]."',currentmp='".$userrow["currentmp"]."' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
            header("Location: index.php?do=victory");
            die();
        }
        
        // Monster's turn.
        $pagearray["monsterturn"] = "";
        if ($userrow["currentmonstersleep"] != 0) { // Check to wake up.
            $chancetowake = rand(1,15);
			//atributo inteligencia
				$chancetowake = $chancetowake + floor($userrow["inteligencia"]/100);
            if ($chancetowake > $userrow["currentmonstersleep"]) {
                $userrow["currentmonstersleep"] = 0;
                $pagearray["monsterturn"] .= "O inimigo acordou.<br />";
            } else {
                $pagearray["monsterturn"] .= "O inimigo ainda est� dormindo.<br />";
            }
        }
        if ($userrow["currentmonstersleep"] == 0) { // Only do this if the monster is awake.
            $tohit = ceil(rand($monsterrow["maxdam"]*.5,$monsterrow["maxdam"]));
            if ($userrow["difficulty"] == 2) { $tohit = ceil($tohit * $controlrow["diff2mod"]); }
            if ($userrow["difficulty"] == 3) { $tohit = ceil($tohit * $controlrow["diff3mod"]); }
            $toblock = ceil(rand($userrow["defensepower"]*.75,$userrow["defensepower"])/4);
            $tododge = rand(1,150);
			//atributo agilidade antes: $tododge <= sqrt($userrow["dexterity"])
			$tododge = $tododge - floor($userrow["agilidade"]*2/100);
            if ($tododge <= sqrt($userrow["dexterity"])) {
                $tohit = 0; $pagearray["monsterturn"] .= "Voc� fugiu do ataque inimigo. Nenhum dano foi causado.<br />";
                $persondamage = 0;
            } else {
                if ($tohit <= $toblock) { $tohit = $toblock + 1; }
                $persondamage = $tohit - $toblock;
                if ($userrow["currentuberdefense"] != 0) {
                    $persondamage -= ceil($persondamage * ($userrow["currentuberdefense"]/100));
                }
                if ($persondamage < 1) { $persondamage = 1; }
            }
            $pagearray["monsterturn"] .= "O inimigo te atacou, causando $persondamage de dano.<br /><br />";
            $userrow["currenthp"] -= $persondamage;
            if ($userrow["currenthp"] <= 0) {
                $newgold = ceil($userrow["gold"]/2);
                $newhp = ceil($userrow["maxhp"]/4);
                $updatequery = doquery("UPDATE {{table}} SET currenthp='$newhp',currentaction='In Town',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',latitude='0',longitude='0',gold='$newgold' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
                $playerisdead = 1;
            }
        }
    
    // Do a monster's turn if person lost the chance to swing first. Serves him right!
    } elseif ( $chancetoswingfirst == 0 ) {
        $pagearray["yourturn"] = "O inimigo te atacou antes que estivesse preparado!<br /><br />";
        $pagearray["monsterhp"] = "HP do Inimigo: " . $userrow["currentmonsterhp"] . "<br /><br />";
        $pagearray["monsterturn"] = "";
        if ($userrow["currentmonstersleep"] != 0) { // Check to wake up.
            $chancetowake = rand(1,15);
			//atributo inteligencia
				$chancetowake = $chancetowake + floor($userrow["inteligencia"]/100);
            if ($chancetowake > $userrow["currentmonstersleep"]) {
                $userrow["currentmonstersleep"] = 0;
                $pagearray["monsterturn"] .= "O inimigo acordou.<br />";
            } else {
                $pagearray["monsterturn"] .= "O inimigo ainda est� dormindo.<br />";
            }
        }
        if ($userrow["currentmonstersleep"] == 0) { // Only do this if the monster is awake.
            $tohit = ceil(rand($monsterrow["maxdam"]*.5,$monsterrow["maxdam"]));
            if ($userrow["difficulty"] == 2) { $tohit = ceil($tohit * $controlrow["diff2mod"]); }
            if ($userrow["difficulty"] == 3) { $tohit = ceil($tohit * $controlrow["diff3mod"]); }
            $toblock = ceil(rand($userrow["defensepower"]*.75,$userrow["defensepower"])/4);
            $tododge = rand(1,150);
			//atributo agilidade antes: $tododge <= sqrt($userrow["dexterity"])
			$tododge = $tododge - floor($userrow["agilidade"]*2/100);
            if ($tododge <= sqrt($userrow["dexterity"])) {
                $tohit = 0; $pagearray["monsterturn"] .= "Voc� fugiu do ataque inimigo. Nenhum dano foi causado.<br />";
                $persondamage = 0;
            } else {
                $persondamage = $tohit - $toblock;
                if ($persondamage < 1) { $persondamage = 1; }
                if ($userrow["currentuberdefense"] != 0) {
                    $persondamage -= ceil($persondamage * ($userrow["currentuberdefense"]/100));
                }
                if ($persondamage < 1) { $persondamage = 1; }
            }
            $pagearray["monsterturn"] .= "O inimigo te atacou, causando $persondamage de dano.<br /><br />";
            $userrow["currenthp"] -= $persondamage;
            if ($userrow["currenthp"] <= 0) {
                $newgold = ceil($userrow["gold"]/2);
                $newhp = ceil($userrow["maxhp"]/4);
                $updatequery = doquery("UPDATE {{table}} SET currenthp='$newhp',currentaction='In Town',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentfight='0',latitude='0',longitude='0',gold='$newgold' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
                $playerisdead = 1;
            }
        }

    } else {
        $pagearray["yourturn"] = "";
        $pagearray["monsterhp"] = "HP do Inimigo: " . $userrow["currentmonsterhp"] . "<br /><br />";
        $pagearray["monsterturn"] = "";
    }
    
    $newmonster = $userrow["currentmonster"];

    $newmonsterhp = $userrow["currentmonsterhp"];
    $newmonstersleep = $userrow["currentmonstersleep"];
    $newmonsterimmune = $userrow["currentmonsterimmune"];
    $newuberdamage = $userrow["currentuberdamage"];
    $newuberdefense = $userrow["currentuberdefense"];
    $newfight = $userrow["currentfight"] + 1;
    $newhp = $userrow["currenthp"];
    $newmp = $userrow["currentmp"];    
    
if ($playerisdead != 1) { 
if ($avisochakra != "") { $avisochakra2 .= "<font color=red>".$avisochakra."</font><br><br>";}//chakra mensagem
$pagearray["command"] = <<<END
Comando?<br /><br />
<form action="index.php?do=fight" method="post">
<input type="submit" name="fight" value="Atacar" /><br /><br />
<select name="userspell"><option value="0">Escolha um Jutsu</option>$magiclist</select> <input type="submit" name="spell" value="Usar" /><br /><br />
$avisochakra2
<input type="submit" name="run" value="Correr" /><br /><br />
</form>
END;
    $updatequery = doquery("UPDATE {{table}} SET currentaction='Fighting',currenthp='$newhp',currentmp='$newmp',currentfight='$newfight',currentmonster='$newmonster',currentmonsterhp='$newmonsterhp',currentmonstersleep='$newmonstersleep',currentmonsterimmune='$newmonsterimmune',currentuberdamage='$newuberdamage',currentuberdefense='$newuberdefense' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
} else {
    
	
	//Graficos
   $pagearray["grafico"] = $userrow["avatar"]."_morto.gif";
   $pagearray["porcent"] = "50%";
   
  
	
    $pagearray["command"] = "<b>Voc� morreu.</b><br /><br />Como consequencia, voc� perdeu metade de seus Ryou. De qualquer forma, lhe foi dado metade dos seus Pontos de Vida para continuar sua jornada.<br /><br />Voc� pode voltar para a <a href=\"index.php\">cidade</a>, e esperamos que se sinta melhor da pr�xima vez.";
}
    
    // Finalize page and display it.
	if ($pagearray["porcent"] == "") {$pagearray["porcent"] = "30%";}
	$template = gettemplate("fight");
    $page = parsetemplate($template,$pagearray);
    
    display($page, "Lutando");
    
}

function victory() {
    
    global $userrow, $controlrow;
    
    if ($userrow["currentmonsterhp"] != 0) { header("Location: index.php?do=fight"); die(); }
    if ($userrow["currentfight"] == 0) { header("Location: index.php"); die(); }
    
    $monsterquery = doquery("SELECT * FROM {{table}} WHERE id='".$userrow["currentmonster"]."' LIMIT 1", "monsters");
    $monsterrow = mysql_fetch_array($monsterquery);
    
    $exp = rand((($monsterrow["maxexp"]/6)*5),$monsterrow["maxexp"]);
    if ($exp < 1) { $exp = 1; }
    if ($userrow["difficulty"] == 2) { $exp = ceil($exp * $controlrow["diff2mod"]); }
    if ($userrow["difficulty"] == 3) { $exp = ceil($exp * $controlrow["diff3mod"]); }
    if ($userrow["expbonus"] != 0) { $exp += ceil(($userrow["expbonus"]/100)*$exp); }
    $gold = rand((($monsterrow["maxgold"]/6)*5),$monsterrow["maxgold"]);
    if ($gold < 1) { $gold = 1; }
    if ($userrow["difficulty"] == 2) { $gold = ceil($gold * $controlrow["diff2mod"]); }
    if ($userrow["difficulty"] == 3) { $gold = ceil($gold * $controlrow["diff3mod"]); }
    if ($userrow["goldbonus"] != 0) { $gold += ceil(($userrow["goldbonus"]/100)*$exp); }
    if ($userrow["experience"] + $exp < 16777215) { $newexp = $userrow["experience"] + $exp; $warnexp = ""; } else { $newexp = $userrow["experience"]; $exp = 0; $warnexp = "Voc� aumentou seus pontos de experi�ncia."; }
    if ($userrow["gold"] + $gold < 16777215) { $newgold = $userrow["gold"] + $gold; $warngold = ""; } else { $newgold = $userrow["gold"]; $gold = 0; $warngold = "Voc� aumentou seus Ryou."; }
    
    $levelquery = doquery("SELECT * FROM {{table}} WHERE id='".($userrow["level"]+1)."' LIMIT 1", "levels");
    if (mysql_num_rows($levelquery) == 1) { $levelrow = mysql_fetch_array($levelquery); }
    
    if ($userrow["level"] < 100) {
        if ($newexp >= $levelrow[$userrow["charclass"]."_exp"]) {
            $newhp = $userrow["maxhp"] + $levelrow[$userrow["charclass"]."_hp"];
            $newmp = $userrow["maxmp"] + $levelrow[$userrow["charclass"]."_mp"];
            $newtp = $userrow["maxtp"] + $levelrow[$userrow["charclass"]."_tp"];
            $newstrength = $userrow["strength"] + $levelrow[$userrow["charclass"]."_strength"];
            $newdexterity = $userrow["dexterity"] + $levelrow[$userrow["charclass"]."_dexterity"];
            $newattack = $userrow["attackpower"] + $levelrow[$userrow["charclass"]."_strength"];
            $newdefense = $userrow["defensepower"] + $levelrow[$userrow["charclass"]."_dexterity"];
            $newlevel = $levelrow["id"];
			$novospontosamostrar = 5;
			$novospontosdedistrubuicao = $userrow["pontoatributos"] + $novospontosamostrar;
			$novopontonatural = $userrow["maxnp"] + 5;
            
            if ($levelrow[$userrow["charclass"]."_spells"] != 0) {
                $userspells = $userrow["spells"] . ",".$levelrow[$userrow["charclass"]."_spells"];
                $newspell = "spells='$userspells',";
                $spelltext = "Voc� aprendeu um novo Jutsu.<br />";
            } else { $spelltext = ""; $newspell=""; }
            
			
			$updatequery = doquery("UPDATE {{table}} SET pontoatributos='$novospontosdedistrubuicao', maxnp='".$novopontonatural."' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
			
			//Graficos
            $page = "
			<table width=\"100%\">
<tr><td align=\"center\"><center><img src=\"images/title_fighting.gif\" alt=\"Fighting\" /></center></td></tr></table>
			
			<table><tr><td width=\"310\" valign=\"middle\"><center>
			<br><br>Parab�ns. Voc� derrotou ".$monsterrow["name"].".<br />Ganhou $exp de experi�ncia. $warnexp <br />Ganhou $gold de Ryou. $warngold <br /><br /><b>Voc� passou de n�vel!</b><br /><br />Voc� ganhou ".$levelrow[$userrow["charclass"]."_hp"]." Pontos de Vida.<br />Voc� ganhou ".$levelrow[$userrow["charclass"]."_mp"]." Pontos de Chakra.<br />Voc� ganhou ".$levelrow[$userrow["charclass"]."_tp"]." Pontos de Viagem.<br />Voc� ganhou $novospontosamostrar Pontos de Distribui��o.<br />Voc� ganhou 5 Pontos Naturais.<br />Voc� ganhou ".$levelrow[$userrow["charclass"]."_strength"]." de ataque.<br />Voc� ganhou ".$levelrow[$userrow["charclass"]."_dexterity"]." de defesa.<br />$spelltext<br />Voc� pode continuar <a href=\"index.php\">explorando</a>.</center>
			
			</td><td>
			
			
<table width=\"165\" height=\"175\" background=\"layoutnovo/graficos/fundo.png\" style=\"background-repeat:no-repeat;;background-position:left top\"><tr height=\"30%\"><td></td></tr><tr><td><center><img src=\"layoutnovo/graficos/".$userrow["avatar"]."_ganhou.gif\"></center>
</td></tr><tr  height=\"15\"><td></td></tr></table>


</td></tr></table>
			";
            $title = "A determina��o foi sua sorte!";
            $dropcode = "";
			
			
			
        } else {
            $newhp = $userrow["maxhp"];
            $newmp = $userrow["maxmp"];
            $newtp = $userrow["maxtp"];
            $newstrength = $userrow["strength"];
            $newdexterity = $userrow["dexterity"];
            $newattack = $userrow["attackpower"];
            $newdefense = $userrow["defensepower"];
            $newlevel = $userrow["level"];
            $newspell = "";
            
			//colocando probabilidade de sorte // antes tava rand(1,30) == 1 // droprate tamb�m..
			$sorte = floor($userrow["sorte"]*2/100 + 1);
			$sorte = $sorte + ($sorte*$userrow["droprate"]/100);
            if (rand(1,20) <= $sorte) {
                $dropquery = doquery("SELECT * FROM {{table}} WHERE mlevel <= '".$monsterrow["level"]."' ORDER BY RAND() LIMIT 1", "drops");
                $droprow = mysql_fetch_array($dropquery);
                $dropcode = "dropcode='".$droprow["id"]."',";
                $page = "<center>Esse inimigo dropou um item. Clique na exclama��o acima para equipar o item, ou voc� pode continuar <a href=\"index.php\">explorando</a> e ignorar o item.</center>";
				$mostrarexc = "<center><a href=\"index.php?do=drop\"><img border=\"0\" src=\"images/exclamacao.gif\" title=\"Pegar o Drop\" alt=\"Pegar o Drop\"></a></center><br>";
            } else { 
                $dropcode = "";
				//alterado
                $page = "<center>Voc� pode continuar <a href=\"index.php\">explorando</a>.</center>";
            }

            $title = "Vit�ria!";
			
			
			
			//Graficos tamb�m abaixo:
			$page = "
			<table width=\"100%\">
<tr><td align=\"center\"><center><img src=\"images/title_fighting.gif\" alt=\"Fighting\" /></center></td></tr></table>
			
			<table><tr><td width=\"310\" valign=\"middle\"><center>
			
			<br><br>Parab�ns. Voc� derrotou ".$monsterrow["name"].".<br />Voc� ganhou $exp de experi�ncia. $warnexp <br />Voc� ganhou $gold Ryou. $warngold <br /><br />$mostrarexc</center>
			
			</td><td>
			
			
<table width=\"165\" height=\"175\" background=\"layoutnovo/graficos/fundo.png\" style=\"background-repeat:no-repeat;;background-position:left top\"><tr height=\"30%\"><td></td></tr><tr><td><center><img src=\"layoutnovo/graficos/".$userrow["avatar"]."_ganhou.gif\"></center>
</td></tr><tr  height=\"15\"><td></td></tr></table>


</td></tr></table>
			".$page;
			
			
        }
    }

    $updatequery = doquery("UPDATE {{table}} SET currentaction='Exploring',level='$newlevel',maxhp='$newhp',maxmp='$newmp',maxtp='$newtp',strength='$newstrength',dexterity='$newdexterity',attackpower='$newattack',defensepower='$newdefense', $newspell currentfight='0',currentmonster='0',currentmonsterhp='0',currentmonstersleep='0',currentmonsterimmune='0',currentuberdamage='0',currentuberdefense='0',$dropcode experience='$newexp',gold='$newgold' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
    

    display($page, $title);
    
}

function drop() {
    
    global $userrow;
    
    if ($userrow["dropcode"] == 0) { header("Location: index.php"); die(); }
    
    $dropquery = doquery("SELECT * FROM {{table}} WHERE id='".$userrow["dropcode"]."' LIMIT 1", "drops");
    $droprow = mysql_fetch_array($dropquery);
    
    if (isset($_POST["submit"])) {
        
        $slot = $_POST["slot"];
        
        if ($slot == 0) { display("Por favor volte e selecione um slot do invent�riao para continuar.","Error"); }
		
						//id do item
						if (($slot > 0) && ($slot < 4)){
							$iddoitem = $userrow["slot".$slot."id"];
						}else{
							$slot -= 3;
							$bpitem = explode(",",$userrow["bp".$slot]);
							$iddoitem = $bpitem[1];
							$true = true;
						}
       
	  if ($true != true){
					if ($userrow["slot".$slot."id"] != 0) {
												
						$slotquery = doquery("SELECT * FROM {{table}} WHERE id='".$iddoitem."' LIMIT 1", "drops");
						$slotrow = mysql_fetch_array($slotquery);
						
						$old1 = explode(",",$slotrow["attribute1"]);
						if ($slotrow["attribute2"] != "X") { $old2 = explode(",",$slotrow["attribute2"]); } else { $old2 = array(0=>"maxhp",1=>0); }
						$new1 = explode(",",$droprow["attribute1"]);
						if ($droprow["attribute2"] != "X") { $new2 = explode(",",$droprow["attribute2"]); } else { $new2 = array(0=>"maxhp",1=>0); }
						
						$userrow[$old1[0]] -= $old1[1];
						$userrow[$old2[0]] -= $old2[1];
						if ($old1[0] == "strength") { $userrow["attackpower"] -= $old1[1]; }
						elseif ($old1[0] == "dexterity") { $userrow["defensepower"] -= $old1[1]; }
						if ($old2[0] == "strength") { $userrow["attackpower"] -= $old2[1]; }
						elseif ($old2[0] == "dexterity") { $userrow["defensepower"] -= $old2[1]; }
			
						
						$userrow[$new1[0]] += $new1[1];
						$userrow[$new2[0]] += $new2[1];
						if ($new1[0] == "strength") { $userrow["attackpower"] += $new1[1]; }
						elseif ($new1[0] == "dexterity") { $userrow["defensepower"] += $new1[1]; }
						if ($new2[0] == "strength") { $userrow["attackpower"] += $new2[1]; }
						elseif ($new2[0] == "dexterity") { $userrow["defensepower"] += $new2[1]; }
						
						if ($userrow["currenthp"] > $userrow["maxhp"]) { $userrow["currenthp"] = $userrow["maxhp"]; }
						if ($userrow["currentmp"] > $userrow["maxmp"]) { $userrow["currentmp"] = $userrow["maxmp"]; }
						if ($userrow["currenttp"] > $userrow["maxtp"]) { $userrow["currenttp"] = $userrow["maxtp"]; }
						if ($userrow["currentnp"] > $userrow["maxnp"]) { $userrow["currentnp"] = $userrow["maxnp"]; }
						if ($userrow["currentep"] > $userrow["maxep"]) { $userrow["currentep"] = $userrow["maxep"]; }
						$newname = addslashes($droprow["name"]);
						
						$query = doquery("UPDATE {{table}} SET slot".$_POST["slot"]."name='$newname',slot".$_POST["slot"]."id='".$droprow["id"]."',$old1[0]='".$userrow[$old1[0]]."',$old2[0]='".$userrow[$old2[0]]."',$new1[0]='".$userrow[$new1[0]]."',$new2[0]='".$userrow[$new2[0]]."',attackpower='".$userrow["attackpower"]."',defensepower='".$userrow["defensepower"]."',currenthp='".$userrow["currenthp"]."',currentmp='".$userrow["currentmp"]."',currenttp='".$userrow["currenttp"]."',currentnp='".$userrow["currentnp"]."',currentep='".$userrow["currentep"]."',sorte='".$userrow["sorte"]."',agilidade='".$userrow["agilidade"]."',determinacao='".$userrow["determinacao"]."',precisao='".$userrow["precisao"]."',inteligencia='".$userrow["inteligencia"]."',droprate='".$userrow["droprate"]."', dropcode='0' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
					
						
					} else {
						
						$new1 = explode(",",$droprow["attribute1"]);
						if ($droprow["attribute2"] != "X") { $new2 = explode(",",$droprow["attribute2"]); } else { $new2 = array(0=>"maxhp",1=>0); }
						
						$userrow[$new1[0]] += $new1[1];
						$userrow[$new2[0]] += $new2[1];
						if ($new1[0] == "strength") { $userrow["attackpower"] += $new1[1]; }
						if ($new1[0] == "dexterity") { $userrow["defensepower"] += $new1[1]; }
						if ($new2[0] == "strength") { $userrow["attackpower"] += $new2[1]; }
						if ($new2[0] == "dexterity") { $userrow["defensepower"] += $new2[1]; }
						
						$newname = addslashes($droprow["name"]);
						$query = doquery("UPDATE {{table}} SET slot".$_POST["slot"]."name='$newname',slot".$_POST["slot"]."id='".$droprow["id"]."',$new1[0]='".$userrow[$new1[0]]."',$new2[0]='".$userrow[$new2[0]]."',attackpower='".$userrow["attackpower"]."',defensepower='".$userrow["defensepower"]."',dropcode='0',currentnp='".$userrow["currentnp"]."',currentep='".$userrow["currentep"]."',sorte='".$userrow["sorte"]."',agilidade='".$userrow["agilidade"]."',determinacao='".$userrow["determinacao"]."',precisao='".$userrow["precisao"]."',inteligencia='".$userrow["inteligencia"]."',droprate='".$userrow["droprate"]."' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
						
					}
	  	}else{//elsefim if... slot and slot
				$newname = addslashes($droprow["name"]);
				$itempronto = $newname.",".$droprow["id"].",4,X";
				$query = doquery("UPDATE {{table}} SET bp".$slot."='$itempronto' WHERE id='".$userrow["id"]."' LIMIT 1", "users");
		}//fim if.. slot and slot...
				
      header("Location: /narutorpg/index.php?conteudo=O item foi equipado ou adicionado � mochila com sucesso.");die();
        
    }//fim do submit...
    
    $attributearray = array("maxhp"=>"Max HP",
                            "maxmp"=>"Max CH",
                            "maxtp"=>"Max TP",
                            "defensepower"=>"Poder de Defesa",
                            "attackpower"=>"Poder de Ataque",
                            "strength"=>"For�a",
                            "dexterity"=>"Defesa",
                            "expbonus"=>"B�nus de Experi�ncia",
                            "goldbonus"=>"B�nus de Ryou",
							"sorte"=>"Sorte",
							"agilidade"=>"Agilidade",
							"determinacao"=>"Determina��o",
							"precisao"=>"Precis�o",
							"inteligencia"=>"Intelig�ncia",
							"droprate"=>"Chance de Drop",
							"maxnp"=>"Max NP",
							"maxep"=>"Max EP");
    
    $page = "<table width=\"100%\"><tr><td width=\"100%\" align=\"center\"><center><img src=\"images/drop.gif\" /></center></td></tr></table><center>O inimigo dropou o seguinte item: <br><table><tr bgcolor=\"#613003\"><td><center><font color=white><b>".$droprow["name"]."</b></font></center></td></tr>";
    
    $attribute1 = explode(",",$droprow["attribute1"]);
    $page .= "<tr bgcolor=\"#E4D094\"><td>".$attributearray[$attribute1[0]];
    if ($attribute1[1] > 0) { $page .= " +" . $attribute1[1] . "</td></tr>"; } else { $page .= " -". $attribute1[1] . "</td></tr>"; }
    
    if ($droprow["attribute2"] != "X") { 
        $attribute2 = explode(",",$droprow["attribute2"]);
        $page .= "<tr bgcolor=\"#FFF1C7\"><td>".$attributearray[$attribute2[0]];
        if ($attribute2[1] > 0) { $page .= " +" . $attribute2[1] . "</td></tr>"; } else { $page .= " -". $attribute2[1] . "</td></tr>"; }
    }
	$page .= "</table></center>";
    
	//backpack mostrar
	for($h = 1; $h <= 4; $h ++){
	$fundo = $h % 2;
	if ($fundo == 0) {$bgcolor = "#E4D094";}else{$bgcolor = "#FFF1C7";}
		if ($userrow["bp".$h] != "None"){
			$bpitem = explode(",",$userrow["bp".$h]);
			$botao .= "<tr bgcolor=\"$bgcolor\"><td width=\"10\"><input type=\"radio\" id=\"slot\" name=\"slot\" value=\"".(3 + $h)."\"></td><td width=\"20\"><img src=\"images/backpack_pequena.gif\" title=\"Mochila Slot ".$h."\"></td><td>".$bpitem[0]."</td></tr>";
		}else{
			$botao .= "<tr bgcolor=\"$bgcolor\"><td width=\"10\"><input type=\"radio\" id=\"slot\" name=\"slot\" value=\"".(3 + $h)."\"></td><td width=\"20\"><img src=\"images/backpack_pequena.gif\" title=\"Mochila Slot ".$h."\"></td><td><font color=gray>None</font></td></tr>";
		}
	}
	
    $page .= "<br />Selecione um slot do invent�rio da lista abaixo para equipar o item. Se o slot estiver cheio, o Item antigo ser� descartado.<br><br>";
    $page .= "<center><form action=\"index.php?do=drop\" method=\"post\"><table><tr bgcolor=\"#613003\"><td colspan=\"3\"><center><font color=white>Onde Equipar?</font></center></td>
	<tr bgcolor=\"#E4D094\"><td width=\"10\"><input type=\"radio\" id=\"slot\" name=\"slot\" value=\"1\"></td><td width=\"20\"><center><img src=\"images/orb.gif\" title=\"Slot 1\"></center></td><td>".$userrow["slot1name"]."</td></tr>
	<tr bgcolor=\"#FFF1C7\"><td width=\"10\"><input type=\"radio\" id=\"slot\" name=\"slot\" value=\"2\"></td><td width=\"20\"><center><img src=\"images/orb.gif\" title=\"Slot 2\"></center></td><td>".$userrow["slot2name"]."</td></tr>
	<tr bgcolor=\"#E4D094\"><td width=\"10\"><input type=\"radio\" id=\"slot\" name=\"slot\" value=\"3\"></td><td width=\"20\"><center><img src=\"images/orb.gif\" title=\"Slot 3\"></center></td><td>".$userrow["slot3name"]."</td></tr>
	$botao
	</table>
 <input type=\"submit\" name=\"submit\" value=\"OK!\" /></form></center><br>";
    $page .= "<center>Voc� pode tamb�m continuar <a href=\"index.php\">explorando</a> e descartar esse item.</center>";
    
    display($page, "Item Drop");
    
}
    

function dead() {
    
    $page = "<b>Voc� morreu.</b><br /><br />Como consequ�ncia, voc� perdeu metade do seu Ryou. De qualque forma, lhe foi dado metade dos seus Pontos de Vida para continuar sua jornada.<br /><br />Voc� pode voltar para a <a href=\"index.php\">cidade</a>, e esperamos que se sinta melhor da pr�xima vez.";
        
}



?>