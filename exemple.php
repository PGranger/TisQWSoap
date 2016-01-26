<?php
	
	require_once(realpath(dirname(__FILE__)).'/TisQWSoap.class.php') ;
	require_once(realpath(dirname(__FILE__)).'/TisQWSoapAllier.class.php') ;
	
	$surcharge = Array() ;
	$surcharge['Millésime1']['LibelleAffichage'] = 'Année concernée' ;
	$surcharge['f74336f4-8510-e111-a6cd-000c29d07f29']['LibelleAffichage'] = 'Nom de l\'offre' ;
	
	$qws = new TisQWSoapAllier(Array(
		'questionnaireId'=>$questionnaireId,
		'client'=>$client,
		'structureId'=>$structureId,
		'debug'=>true
	)) ;
	$champs = $qws->getChamps() ;
	
	unset($savesResult) ;
	if ( isset($_POST['enregistrer']) && isset($_POST['ids']) )
	{
		$saveResult = Array() ;
		preg_match_all('#([A-Z0-9]{16,18})#Ui',$_POST['ids'],$matches) ;
		foreach ( $matches[1] as $id_TIF )
		{
			echo $id_TIF.'<hr />' ;
			$qws->setIdOffre($id_TIF,false) ;
			$retour = $qws->save(Array('post'=>$_POST)) ;
			if ( is_array($retour) ) $saveResult = array_merge($saveResult,$retour) ;
		}
		if ( sizeof($saveResult) == 0 ) $saveResult = true ;
	}
	
	if ( isset($saveResult) && $saveResult !== true )
	{
		echo 'Une erreur est survenue :<br />' ;
		if ( is_array($saveResult) ) echo '<ul><li>'.implode('</li><li>',$saveResult).'</li></ul>' ;
		else echo var_dump($saveResult) ;
	}
	
	if ( isset($saveResult) && ! is_array($saveResult) && $saveResult === true )
	{
		echo 'Votre formulaire a bien été enregistré !' ;
	}
	
?><form method="post">
	<input type="hidden" name="enregistrer" value="1" />
	<table border="1" width="100%">
		<tr>
			<th>Identifiants TIS auxquels ajouter les informations (ex: HLOAUV...) séparés par un espace</th>
			<td><textarea cols="80" rows="10" name="ids"></textarea></td>
		</tr>
	<?php
		foreach ( $champs as $champ )
		{
			echo '<tr>' ;
				echo '<th>' ;
					if ( isset($champ->LibelleAffichage) ) echo $champ->LibelleAffichage ;
					else echo $champ->Libelle ;
				echo '</th>' ;
				echo '<td>'."\n" ;
					if ( isset($champ->dom) )
					{
						echo $champ->dom->saveXML($champ->dom->firstChild) ;
					}
				echo '</td>' ;
				
				echo '<td>'."\n" ;
					echo "\n".'<pre>' ;
						var_export($champ) ;
					echo '</pre>' ;
				echo '</td>' ;
			echo '</tr>' ;
		}
	?>
	</table>
	<input type="submit" value="Go" />
</form>