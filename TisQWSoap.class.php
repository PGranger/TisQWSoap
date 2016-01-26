<?php

	/**
	*	Classe d'utilisation du webservice QuestionnaireWebService de Tourinsoft V5
	*
	*	Cette classe a pour but de récupérer un maximum d'informations du questionnaire web créé sur Tourinsoft, pour faciliter la construction d'un formulaire. Elle construit principalement un tableau $champs qui contiendra la liste des champs présents, dans le bon ordre, avec chacun des informations complémentaires sur le comportement à adopter par le formulaire (le champ est obligatoire ? doit être un mail ? est limité à X caractères ?)
	*
	*	@author	Pierre Granger <p.granger@allier-tourisme.net>
	*	@licence	MIT Licence
	*	@link	https://github.com/PGranger/TisQWSoap
	*	@link	http://cdt.allier-auvergne-tourisme.com/TisQWSoap/docs
	*	@link	http://www.allier-auvergne-tourisme.com
	*	@link	http://www.pierre-granger.fr
	*	@link	http://api-doc.tourinsoft.com/#/questionnaire-web
	*	@version	v1.0
	*	@date	2014-09-23
	*/
	
	require_once(realpath(dirname(__FILE__)).'/../Cache.class.php') ;

	class TisQWSoap extends Cache {
		
		/**
		*	@var	guid	GUID du questionnaire. Vous le trouverez dans Tourinsoft dans : Gen. Web > Questionnaire Web > Gestion de la publication > *Votre questionnaire* > Tout en bas
		*/
		protected $questionnaireId ;
		/**
		*	@var	string	Nom de votre structure sur Tourinsoft. Le plus souvent, [xxx].tourinsoft.com (ex: cdt03.tourinsoft.com)
		*/
		protected $client ;
		/**
		*	@var	guid	GUID de votre structure. Comme pour questionnaireId, en bas de la modification de la publication du questionnaire web : choisissez votre structure dans le menu déroulant puis le GUID correspondant s'affichera en dessous (Structure Id : XXX...)
		*/
		protected $structureId ;
		/**
		*	@var	guid	GUID de l'utilisateur (pour savoir quels dossiers et fichiers de la médiathèque vous avez le droit de voir ou non). Un peu plus dur à trouver : dans Admin > Utilisateur, trouvez votre utilisateur dans la liste, puis placez le curseur de souris sur le bouton "modifier", puis clic droit, copier l'adresse du lien : ça doit ressembler à ça :  http://cdt03.tourinsoft.com/t.../general.aspx?id=XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX. Il faut récupérer le GUID tout à la fin (id=...)
		*/
		private $utilisateurId ;
		/**
		*	@var	int	les services uploadFile et uploadFileNext recoivent les fichiers par "paquet" et non en une seule fois. On définit la taille de ce paquet ici (en byte)
		*/
		private $packet_size = 262144 ; // 1024
		/**
		*	@var	bool	Affichera le déroulement du script dans la console (SSH) ou à l'écran (http)
		*/
		protected $debug = false ;
		/**
		*	@var	string	Adresse mail à qui seront envoyées les erreurs si debug OFF
		*/
		private $mail_administrateur = 'p.granger@allier-tourisme.net' ;
		/**
		*	@var	string	Adresse mail de l'expéditeur des erreurs si debug OFF
		*/
		private $mail_expediteur = 'sdit@allier-tourisme.net' ;
		/**
		*	@var	string	URL du webservice questionnaire web
		*/
		private $url_wcf_qweb = "http://wcf.tourinsoft.com/QuestionnaireWeb/QuestionnaireWebService.svc?wsdl" ;
		/**
		*	@var	string	URL du webservice mediathèque
		*/
		private $url_wcf_media = "http://wcf.tourinsoft.com/Mediatheque/ImportMediaService.svc?wsdl" ;
		/**
		*	@var	object	Implémentation (soap) du webservice $url_wcf_qweb
		*/
		private $soap ;
		/**
		*	@var	object	Implémentation (soap) du webservice $url_wcf_media
		*/
		private $soap_media ;
		
		protected $dossierId ;
		
		public $messageNoValeurs ;
		
		private $guid_syndic_brochures ;
		
		protected $champs ;
		private $tis_url_ws = 'http://wcf.tourinsoft.com/QuestionnaireWeb/QuestionnaireWebService.svc' ;
		protected $tis_url_qw = 'http://#TIS_STRUCTURE#.tourinsoft.com/questionnaire-web/' ;
		private $title ;
		private $already_forced ;
		//private $user_agent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.97 Safari/537.36' ;
		protected $id_offre ;
		private $surcharge ;
		private $TypeChampsTis = Array('TypeString','TypeDouble','TypeGuid','TypeListGuid','TypeBool','TypeDate','TypeTime','TypeCommandeBrochure') ;
		private $TypeChampsForm = Array('TypeCache','TypeLecture','TypeTextarea') ;
		private $filtresPossibles = Array('majuscule','obligatoire','minuscule','nombre','mail','telephone','maxlength') ;
		
		public $envoyerMailChangements = false ;
		public $mail_changements = null ;
		
		private $languesBrochures = Array(
			'Français'=>'99ba1d4b-a4dd-4ea8-922c-09108fd8f462', // Français [FR]	
			'Allemand'=>'e917b627-fab1-411b-862a-4ea5c0156347', // Allemand [AL]	
			'Anglais'=>'6318154c-0fa2-40a8-91c4-b8486a97fd12', // Anglais [AN]	
			'Arabe'=>'3bdfff90-e37e-4aea-b847-36a33771aa9b', // Arabe [AR]	
			'Chinois'=>'7d61611e-67d5-439c-8e60-023d84961334', // Chinois [CH]	
			'Espagnol'=>'385dd669-3c30-4293-9037-c3b59ecc7058', // Espagnol [ES]	
			'Hongrois'=>'e26e7040-4284-4312-9e26-8f2ef60d38c0', // Hongrois [HONGR]	
			'Italien'=>'96f219e8-6bf5-46eb-9e96-02fef78290ad', // Italien [IT]	
			'Japonais'=>'ab3df671-8e76-4694-b238-bac3f350d896', // Japonais [JP]	
			'Néerlandais'=>'e36298d6-8d03-46be-b0da-714fe9c00c19', // Néerlandais [NE]	
			'Portugais'=>'6e9a3662-5a8a-4ebe-86e6-9d9224e46047', // Portugais [PU]
			'Russe'=>'9f969989-23f6-4610-824f-81482c49fe41', // Russe [RU]
			'Autre(s)'=>'771d5d17-2c4a-44e1-947c-d9760e016653', // Autre(s) [AUTRE]
		) ;
		
		private $header_addaspx = array(
			'method' => 'GET',
			'max_redirects' => '0',
			'ignore_errors' => 1,
			'header' => "User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.97 Safari/537.36\r\nAccept-Charset: UTF-8 \r\n"
		) ;
		
		/**
		*	Constructeur
		*	@param	array	$params	Liste des paramètres du constructeur. Voir les variables privées de la classe pour la description détaillée
		*					$params['questionnaireId']	guid	(obligatoire)
		*					$params['client']	string	(obligatoire)
		*					$params['structureId']	guid	(obligatoire)
		*/
		public function __construct($params)
		{
			if ( ! is_array($params) ) return false ;
			if ( isset($params['questionnaireId']) ) $this->questionnaireId = $params['questionnaireId'] ; else return false ;
			if ( isset($params['client']) ) $this->client = $params['client'] ; else return false ;
			if ( isset($params['structureId']) ) $this->structureId = $params['structureId'] ; else return false ;
			if ( isset($params['utilisateurId']) ) $this->utilisateurId = $params['utilisateurId'] ;
			if ( isset($params['dossierId']) ) $this->dossierId = $params['dossierId'] ;
			if ( isset($params['debug']) ) $this->debug = $params['debug'] ? true : false ;
			if ( isset($_GET['debug']) ) $this->debug = true ;
			if ( isset($params['envoyerMailChangements']) ) $this->envoyerMailChangements = $params['envoyerMailChangements'] ;
			if ( isset($params['mail_administrateur']) && $this->check_mail($params['mail_administrateur']) ) $this->mail_administrateur = $params['mail_administrateur'] ;
			if ( isset($params['mail_expediteur']) && $this->check_mail($params['mail_expediteur']) ) $this->mail_expediteur = $params['mail_expediteur'] ;
			if ( isset($params['surcharge']) && is_array($params['surcharge']) ) $this->surcharge = $params['surcharge'] ;
			
			if ( isset($params['guid_syndic_brochures']) && $this->isGuid($params['guid_syndic_brochures']) )
				$this->guid_syndic_brochures = $params['guid_syndic_brochures'] ;
			
			parent::__construct(realpath(dirname(__FILE__)).'/cache/',$this->debug,@$params['expiration']) ;
			
			try { $this->soap = new SoapClient($this->url_wcf_qweb); } catch(SoapFault $fault) { $this->error($fault) ; return false ; }
			try { $this->soap_media = new SoapClient($this->url_wcf_media); } catch(SoapFault $fault) { $this->error($fault,__LINE__) ; }
			
			$this->getChamps() ;
			
			/*
				Hack permettant de renseigner 1 champ pour que l'occurrence "Demande de brochure" soit bien créée sur Tourinsoft.
			*/
			if ( $champ = $this->getChamp('Date de la demande1') )
			{
				$this->champs[$champ->ID]->NouvelleValeur = date('d/m/Y') ;
				$this->champs[$champ->ID]->TypeChampFormulaire = 'TypeCache' ;
			}
		}
		
		/**
		*	Récupère la liste des champs, dans le bon ordre, avec les valeurs s'il s'agit de liste de choix multiples
		*	@param	bool	$force	Forcer la recharge complète des champs sans chercher dans le cache
		*	@return	array	Liste des champs
		*/
		public function getChamps($force=false)
		{
			if ( $this->debug ) echo '<div style="color:#FFF;background:#482;"><h1>getChamps();</h1></div>' ;
			
			if ( ! $this->isGuid($this->questionnaireId) ) return false ;
			if ( is_array($this->champs) && ( ! $force || $this->already_forced ) ) return $this->champs ;
			
			$this->champs = Array() ;
			
			$temp = false ;
			
			if ( $force || ! $cacheget = $this->get($this->questionnaireId) )
			{
				try {
					$temp = $this->soap->Get(Array('client'=>$this->client,'questionnaireId'=>$this->questionnaireId)) ;
					
					$this->already_forced = true ;
					
					if ( $this->debug )
					{
						echo '<div style="color:#FFF;background:#482;">' ;
							echo '<h2>$temp->GetResult->Champ</h2>' ;
							pre($temp->GetResult->Champ) ;
						echo '</div>' ;
					}
					
					foreach ( $temp->GetResult->Champ as $i => $champ )
					{
						if ( in_array($champ->TypeChamp,Array('TypeGuid','TypeListGuid')) )
						{
							try {
								$items = $this->soap->GetItems(Array('client'=>$this->client,'questionnaireId'=>$this->questionnaireId,'champId'=>$champ->ID)) ;
								if ( isset($items->GetItemsResult) && isset($items->GetItemsResult->Item) )
								{
									$temp->GetResult->Champ[$i]->Items = Array() ;
									
									if ( is_array($items->GetItemsResult->Item) )
									{
										foreach ( $items->GetItemsResult->Item as $ii => $item )
										{
											if ( is_object($item) )
											{
												$temp->GetResult->Champ[$i]->Items[$item->ID] = $item->Libelle ;
											}
										}
									}
									elseif ( isset($items->GetItemsResult->Item->ID) && isset($items->GetItemsResult->Item->Libelle) )
									{
										$item = $items->GetItemsResult->Item ;
										$temp->GetResult->Champ[$i]->Items[$item->ID] = $item->Libelle ;
									}
								}
							} catch(SoapFault $fault) { pre($fault); }
						}
					}
				} catch(SoapFault $fault) { var_dump($fault); }
				
				if ( $temp !== false && is_array($temp->GetResult->Champ) )
				{
					foreach ( $temp->GetResult->Champ as $champ )
					{
						$this->champs[mb_strtolower($champ->ID)] = $champ ;
					}
					
					$this->buildInfosChamps() ;
					$this->buildSurcharge() ;
					
					$this->set($this->questionnaireId,serialize($this->champs)) ;
					
					if ( $this->debug )
					{
						echo '<div style="color:#FFF;background:#482;">' ;
							echo '<h2>$this->champs</h2>' ;
							pre($this->champs) ;
						echo '</div>' ;
					}
					
					return $this->champs ;
					
				}
				return false ;
			}
			
			$this->champs = unserialize($cacheget) ;
			return $this->champs ;
		}
		
		/**
		*	Recherche le formulaire dans sa version HTML d'ajout (sans valeurs renseignées) afin d'obtenir des informations supplémentaires sur chaque champ.
		*	@return	array	Champs triés et renseignés avec des informations complémentaires
		*/
		public function buildInfosChamps($params=null)
		{
			$http_qweb_html = preg_replace('/#TIS_STRUCTURE#/',$this->client,$this->tis_url_qw).'/'.$this->structureId.'/'.$this->questionnaireId.'/add.aspx' ;
			
			if ( ! 
				(
					$qweb_html = $this->gimme($http_qweb_html,
											Array(
												'stream_options'=>Array('http'=>$this->header_addaspx)
											)
										)
				)
			) return false ;
			
			// On a récupéré le contenu HTML du formulaire en ajout.
			// On récupère dans le code tous les champs présents via leur identifiant pour avoir l'ordre d'affichage.
			// Problème : si on champ n'est pas présent en affichage (ex: code INSEE) alors il ne sera pas remis dans $this->champs.
			$temp = $this->champs ; // On stocke les champs actuels trouvés via SOAP dans un temporaire
			$this->champs = Array() ; // On vide les champs actuels pour les resaisir
			
			// Recherche des champs "lecture"
			// Ces champs sont absents des champs récupérés avec Soap. On va donc les ajouter nous même à la liste des champs.
			if ( preg_match_all('#id="([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})"#i',$qweb_html,$ids) )
			{
				foreach ( $ids[1] as $id )
				{
					$id = mb_strtolower($id) ;	
					$this->champs[$id] = ( isset($temp[$id]) ) ? $temp[$id] : (object) Array('ID'=>$id,'TypeChampFormulaire'=>'TypeLecture') ;
				}
			}
			
			// Maintenant on a tous nos champs renseignés dans $this->champs, y compris les champs Lecture.
			// Reste 2 cas particuliers : INSEE et TypeCommandeBrochure
			$got_insee = Array() ;
			foreach ( $temp as $k => $champ )
			{
				if ( ! isset($this->champs[$k]) )
				{
					// 1) le code INSEE ! Lui est bien présent dans les champs récupérés via Soap (maintenant stockés dans $temp) mais est absent du add.aspx.
					// Il faut donc qu'on le récupère du $temp pour le remettre dans $this->champs.
					if ( preg_match('#^INSEE Commune$#ui',$champ->Libelle) )
					{
						$champ->TypeChampFormulaire = 'TypeInsee' ;
						
						// Si on a detecté un champ code insee, on va marquer les champs commune et code postal pour les traiter différement à l'affichage.
						// Le fonctionnement pour le Soap sera toujours le même : ce n'est que visuellement dans notre formulaire qu'on fera des particularités (select chosen sur code insee, masquage des CP/Com...)
						$temp_commune = $this->getGuidChamp('Commune') ;
						if ( $temp_commune ) $this->champs[$temp_commune]->TypeChampFormulaire = 'TypeCommune' ;
						$temp_cp = $this->getGuidChamp('Code Postal') ;
						if ( $temp_cp ) $this->champs[$temp_cp]->TypeChampFormulaire = 'TypeCodePostal' ;
						
						$got_insee[] = Array('champINSEE'=>$champ,'guidCommune'=>$temp_commune,'guidCP'=>$temp_cp) ;
						
						// Attention : ici on n'ajoute pas le champ insee en fin de tableau. On va voir au dessous si on ne peut pas lui trouver une position plus appropriée.
					}
					// 2) 2ème cas particulier : le champ TypeCommandeBrochure. Lui aussi peut avoir été récupéré avec via le webservice (dans getChamps, $this->soap->Get(...))
					// Mais comme ici on ne "garde" que les champs qu'on a AUSSI trouvé dans le code html (add.aspx...), du coup le champ TypeCommandeBrochure a sauté.
					// On va donc vérifier ici s'il était présent : s'il l'était, on le rajoute !
					elseif ( $champ->TypeChamp == 'TypeCommandeBrochure' )
					{
						$this->champs[$champ->ID] = $champ ;
					}
					else
					{
						// Quid du else ? que faire des champs 
						// $this->champs[$k] = $champ ;
					}
				}
			}
			
			
			
			
			if ( $this->debug )
			{
				echo '<hr />' ;
				var_dump($got_insee) ;
				echo '<hr />' ;
			}
			
			// Comme on aime bien quand c'est joli, au lieu de pousser le champ INSEE en bas de formulaire on va chercher la position de la commune et le glisser derrière.
			if ( sizeof($got_insee) > 0 )
			{
				foreach ( $got_insee as $insee )
				{
					$champ_insee = $insee['champINSEE'] ;
					
					$temp = $this->champs ;
					$this->champs = Array() ;
					$trouve = false ;
					foreach ( $temp as $k => $champ )
					{
						$this->champs[$k] = $champ ;
						if ( $k == $insee['guidCommune'] )
						{
							$this->champs[$champ_insee->ID] = $champ_insee ;
							$trouve = true ;
						}
					}
					if ( ! $trouve ) $this->champs[$champ_insee->ID] = $champ_insee ;
				}
			}
			
			// Maintenant on va rentrer plus en détail dans le fichier HTML add.aspx (toujours sans offre renseignée)
			$doc = new DOMDocument() ;
			$html = preg_replace('"\n"',' ',$qweb_html) ;
			$html = preg_replace('"\r"',' ',$html) ;
			$html = preg_replace('"\t"',' ',$html) ;
			@$doc->loadHTML($html) ;
			$domxpath = new DOMXpath($doc) ;
			
			$title = $doc->getElementsByTagName('title') ;
			foreach ( $title as $t ) { $this->title = $this->preparer($t->nodeValue) ; break ; }
			
			/**
			*	Certaines "alertes" de champs se présentent sous forme de tables.
			*	On récupère toutes les tables, on cherche celles qui ont une classe qui correspond à un GUID
			*	Une fois récupérées, on cherche le span dont l'identifiant est le guid en question
			*	Et on récupère le "title" de ce span qu'on stocke 
			*/
			$tables = $doc->getElementsByTagName('table') ;
			foreach ( $tables as $table )
			{
				$class = $table->getAttribute('class') ;
				if ( preg_match('#([a-f0-9]{8}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{12})$#',$class,$reg) )
				{
					$span = $doc->getElementById($class) ;
					foreach ( $span->childNodes as $node )
					{
						$id_table = strtolower(preg_replace('#_#','-',$reg[1])) ;
						if ( isset($this->champs[$id_table]) )
						{
							echo '<h2>'.$id_table.'</h2>' ;
							pre($table) ;
							$this->champs[$id_table]->validatorMessage = $this->preparer($node->getAttribute('title')) ;
						}
						break ;
					}
				}
			}
			
			/**
			*	Certains champs ont un champ "validator_ID" qui contient le texte d'alerte en cas d'erreur.
			*	On va chercher ce texte pour renseigner le champ.
			*	Ex : 
			*	<span id="validator_4BB64D5A_BDA0_49FC_BF30_73E6AF885C48"><span class="ui-icon ui-icon-alert" title="* Millésime obligatoire"></span></span>
			*	<span id="validator_dataType_heure_C26F0D3A_46DA_47CA_8F63_6F22A63A7C05"><span class="ui-icon ui-icon-alert" title="Le format de la date inserée n'est pas valide (Date de la relance)"></span></span>
			*	<span id="validator_dataType_num_F7B5DA90_5D4F_4F4C_9A8F_B237552B3189"><span class="ui-icon ui-icon-alert" title="La valeur du champ doit être numérique (Numéro relance)"></span></span>
			*	Attention : il peut y avoir plusieurs validateurs pour le même champ (un commentaire "perso" + une alerte du genre "Le champ doit être numérique")
			*	L'alerte perso est construite validator_[GUID], l'alerte spécifique validator_dataType_[TYPE]_[GUID]
			*/
			
			$spans = $domxpath->query('//span') ;
			
			$spans = $domxpath->query('//span[starts-with(@id,"validator_")]') ;
			foreach ( $spans as $span )
			{
				$id = $span->getAttribute('id') ;
				if ( ! preg_match('#validator_(dataType_)?(([a-z]+)_)?([a-f0-9]{8}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{12})#i',$id,$reg) )
					continue ;
				
				$id = mb_strtolower(preg_replace('#_#','-',$reg[4])) ;
				$spans_int = $span->getElementsByTagName('span') ;
				$message = null ;
				foreach ( $spans_int as $span_int )
				{
					$message = $span_int->getAttribute('title') ;
					break ;
				}
				
				if ( $reg[1] == '' && $reg[2] == '' )
				{
					$this->champs[$id]->validatorMessage = $this->preparer($message) ;
				}
				else
				{
					$this->champs[$id]->validatorType = $reg[3] ;
					$this->champs[$id]->validatorMessageTis = $this->preparer($message) ;
				}
			}
			
			/*
				Fun fact. S'il s'agit d'un champ Oui-Non, il n'y a pas de validator_.
				A la place il y a une table :
				
				<table id="8B09DD62-454A-4F33-AED2-CE4DD1609875" class="base498dfac4_b52a_465f_a562_a6e6e9d46ddc" onclick="TestValidation('base498dfac4_b52a_465f_a562_a6e6e9d46ddc')">
					<tbody>
					<tr><td><input id="8B09DD62-454A-4F33-AED2-CE4DD1609875_0" type="radio" name="8B09DD62-454A-4F33-AED2-CE4DD1609875" value="1"><label for="8B09DD62-454A-4F33-AED2-CE4DD1609875_0">Oui</label></td></tr>
					<tr><td><input id="8B09DD62-454A-4F33-AED2-CE4DD1609875_1" type="radio" name="8B09DD62-454A-4F33-AED2-CE4DD1609875" value="0"><label for="8B09DD62-454A-4F33-AED2-CE4DD1609875_1">Non</label></td></tr>
					</tbody>
				</table>
				<span id="base498dfac4_b52a_465f_a562_a6e6e9d46ddc" class="ui-state-error ui-corner-all" style="display:inline-block;height:16px;width:16px;padding:2px;visibility:hidden;">
					<span class="ui-icon ui-icon-alert" title="Vous devez accepter les conditions générales d'utilisation (obligatoire)"></span>
				</span>
				
				<table id="8B09DD62-454A-4F33-AED2-CE4DD1609875" class="baseb8f77964_8bb9_4a42_ad58_fc99ce0b6a48" OnClick="TestValidation(&#39;baseb8f77964_8bb9_4a42_ad58_fc99ce0b6a48&#39;)">
					<tr>
						<td><input id="8B09DD62-454A-4F33-AED2-CE4DD1609875_0" type="radio" name="8B09DD62-454A-4F33-AED2-CE4DD1609875" value="1" /><label for="8B09DD62-454A-4F33-AED2-CE4DD1609875_0">Oui</label></td>
					</tr><tr>
						<td><input id="8B09DD62-454A-4F33-AED2-CE4DD1609875_1" type="radio" name="8B09DD62-454A-4F33-AED2-CE4DD1609875" value="0" /><label for="8B09DD62-454A-4F33-AED2-CE4DD1609875_1">Non</label></td>
					</tr>
				</table>
				<span id="baseb8f77964_8bb9_4a42_ad58_fc99ce0b6a48" class="ui-state-error ui-corner-all" style="display:inline-block;height:16px;width:16px;padding:2px;visibility:hidden;">
					<span class="ui-icon ui-icon-alert" title="Vous devez accepter les conditions générales d'utilisation (obligatoire)"></span>
				</span>
				
				Cette table a l'identifiant de notre champ comme ID. On lui ajoute une classe baseXXXXX, et cette classe baseXXXXX est utilisée comme ID par le SPAN qui contient un 2ème SPAN avec le title de l'erreur obligatoire dedans. Pratique.
				TODO !
			*/
			$spans = $domxpath->query('//span[starts-with(@id,"base")]') ;
			
			foreach ( $spans as $span )
			{
				$id_span = $span->getAttribute('id') ;
				if ( ! preg_match('#(base[a-f0-9]{8}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{4}_[a-f0-9]{12})#i',$id_span,$reg) )
					continue ;
				
				$spans_int = $span->getElementsByTagName('span') ;
				$message = null ;
				foreach ( $spans_int as $span_int )
				{
					$message = $span_int->getAttribute('title') ;
					break ;
				}
				
				$id = $reg[1] ;
				$table = $domxpath->query('//table[@class="'.$id.'"]') ;
				foreach ( $table as $t )
				{
					$id_champ = $t->getAttribute('id') ;
					$this->champs[mb_strtolower($id_champ)]->validatorMessage = $this->preparer($message) ;
				}
			}
			
			// Recherche des textareas
			$textareas = $domxpath->query('//textarea') ;
			foreach ( $textareas as $ta )
			{
				$id_ta = mb_strtolower($ta->getAttribute('id')) ;
				$this->champs[mb_strtolower($id_ta)]->TypeChampTisQweb = 'TypeTextarea' ;
				// Si on a trouvé un textarea, et que sa valeur max est grande, on transforme le type de champ du formulaire à afficher en textarea.
				// La ligne du dessus, TypeChampTisQweb, sert pour le getValeurs : si le formulaire TIS utilise un textarea, il faut qu'on le sache pour getValeurs pour
				// aller le chercher en textarea et non en input.
				
				if ( $this->isGuid($id_ta) )
				{
					if (
						( $ta->getAttribute('maxlength') > 255 || $ta->getAttribute('maxlength') == null )
						&& $this->champs[$id_ta]->TypeChamp == 'TypeString'
					)
					{
						$this->champs[$id_ta]->TypeChampFormulaire = 'TypeTextarea' ;
						if ( $ta->getAttribute('maxlength') != null && preg_match('#^[0-9]+$#',$ta->getAttribute('maxlength')) ) $this->champs[$id_ta]->filtres = Array('maxlength'=>$ta->getAttribute('maxlength')) ;
					}
					elseif ( $ta->getAttribute('maxlength') != null )
					{
						$this->champs[$id_ta]->filtres = Array('maxlength'=>$ta->getAttribute('maxlength')) ;
						/*
						if ( $this->debug )
						{
							echo '<h2>'.$id_ta.'</h2>' ;
							pre($this->champs[$id_ta]->filtres) ;
						}
						*/
					}
				}
					
			}
			
			$inputs = $domxpath->query('//input') ;
			foreach ( $inputs as $input )
			{
				$guid = mb_strtolower($input->getAttribute('id')) ;
				if ( ! $this->isGuid($guid) ) continue ;
				if ( $input->getAttribute('maxlength') != null && preg_match('#^[0-9]+$#',$input->getAttribute('maxlength')) )
				{
					if ( ! isset($this->champs[$guid]->filtres) ) $this->champs[$guid]->filtres = Array() ;
					$this->champs[$guid]->filtres['maxlength'] = $input->getAttribute('maxlength') ;
				}
			}
			
			// Détection des champs "photo"
			foreach ( $this->champs as $id => $champ )
			{
				if ( isset($champ->Libelle) && preg_match('#^Photo([0-9]+)$#',$champ->Libelle) )
					$this->champs[$id]->TypeChampFormulaire = 'TypePhoto' ;
			}
			
			// On a renseigné tous les champs et notamment les messages d'erreurs de Tourinsoft (validatorMessageTis).
			// Dans ces messages on peut savoir quel filtre a été choisi sur TOurinsoft (mail, téléphone...), on va donc l'extraire pour le noter comme filtre effectif dans notre champ
			// On va aussi regarder dans le champ saisi "à la main" (validatorMessage) : s'il est présent, c'est qu'on a coché "obligatoire" sur Tourinsoft
			foreach ( $this->champs as $id => $champ )
			{
				if ( ! isset($this->champs[$id]->filtres) || ! is_array($this->champs[$id]->filtres) ) $this->champs[$id]->filtres = Array() ;
				if ( isset($champ->validatorMessageTis) )
				{
					if ( preg_match('#_______@_______\.__#',$champ->validatorMessageTis) && $champ->TypeChamp == 'TypeString' ) $this->champs[$id]->filtres[] = 'mail' ;
					if ( preg_match('#99 99 99 99 99#',$champ->validatorMessageTis) ) $this->champs[$id]->filtres[] = 'telephone' ;	
				}
				if ( isset($champ->validatorMessage) )
				{
					// Cas particulier des photos : le validator est présent même si la photo n\'est pas obligatoire.
					if ( @$this->champs[$id]->TypeChampFormulaire == 'TypePhoto' )
					{
						if ( preg_match('#obligatoire#',$champ->validatorMessage) ) $this->champs[$id]->filtres[] = 'obligatoire' ;
					}
					else
					{
						$this->champs[$id]->filtres[] = 'obligatoire' ;
					}
				}
			}
			
		}
		
		/**
		*	Surcharger les champs avec divers paramètres. la surcharge se fait sur le constructeur de la classe et ne peut plus se faire ensuite.
		*	par exemple, vous pouvez changer le libellé du champ [Nom de l'offre] (attention, le libellé [Nom de l'offre] doit correspondre à celui indiqué sur Tourinsoft dans votre questionnaire.
		*	Vous pouvez également utiliser le GUID du champ au lieu de son libellé (moins pratique)
		*		$params['surcharge']['Nom de l\'offre']['Libelle'] = 'Nom de votre structure' ;
		*		$params['surcharge']['Nom de l\'offre']['filtres']['maxlength'] = '100' ; // Le champ ne pourra pas dépasser les 100 caractères
		*		$params['surcharge']['Nom de l\'offre']['message'] = 'Saisissez le nom commercial de votre structure qui sera affiché sur tous les supports de communication' ; // Message ajouté au champ
		*		$params['surcharge']['Type d\'Accès Télécom']['ValeursAutorisees'] = Array('mél','téléphone filaire') ; // N'autoriser à choisir que ces valeurs (si ce champ est précisé, il remplacera les valeurs choisies dans le formulaire sur Tourinsoft)
		*/
		private function buildSurcharge()
		{
			if ( is_array($this->surcharge) )
			{
				foreach ( $this->champs as $id => $champ )
				{
					$cle_champ = isset($champ->Libelle) ? $champ->Libelle : $id ;
					
					// Surcharge du libellé affiché à l'écran
					if ( isset($this->surcharge[$cle_champ]['LibelleAffichage']) )
						$this->champs[$id]->LibelleAffichage = $this->surcharge[$cle_champ]['LibelleAffichage'] ;
					
					// Surcharge du message affiché sous le libellé
					if ( isset($this->surcharge[$cle_champ]['message']) )
						$this->champs[$id]->message = $this->surcharge[$cle_champ]['message'] ;
					
					// Surcharge du type de champ (passer un champ texte en saisie Date, Heure ou mail par ex.)
					if ( isset($this->surcharge[$cle_champ]['TypeChamp']) )
						$this->champs[$id]->TypeChampFormulaire = $this->surcharge[$cle_champ]['TypeChamp'] ;
					
					// Définit une valeur par défaut pour le champ
					if ( isset($this->surcharge[$cle_champ]['DefaultVal']) )
						$this->champs[$id]->DefaultVal = $this->surcharge[$cle_champ]['DefaultVal'] ;
					
					// Limite les valeurs possibles pour les champs liste/cases
					if ( isset($this->surcharge[$cle_champ]['ValeursAutorisees']) )
						$this->champs[$id]->ValeursAutorisees = $this->surcharge[$cle_champ]['ValeursAutorisees'] ;
					
					if ( ! isset($this->champs[$id]->filtres) ) $this->champs[$id]->filtres = Array() ;
					// Ajout de filtres à la main (parmi les filtres autorisés dans $filtresPossibles
					if ( isset($this->surcharge[$cle_champ]['filtres']) && is_array($this->surcharge[$cle_champ]['filtres']) )
						foreach ( $this->surcharge[$cle_champ]['filtres'] as $k => $v )
							if ( in_array($k,$this->filtresPossibles) || in_array($v,$this->filtresPossibles) )
								$this->champs[$id]->filtres[$k] = $v ;
				}
			}
		}
		
		public function setIdOffre($id_offre,$getValeurs=true,$refresh=false)
		{
			$this->id_offre = $id_offre ;
			if ( $getValeurs ) return $this->getValeurs($refresh) ;
			return true ;
		}
		
		public function makeIdOffreTisUpdate($id_offre)
		{
			if ( $id_offre == null || strlen($id_offre) < 5 ) return false ;
			$temp = str_split($id_offre) ;
			$ret = Array() ;
			foreach($temp as $car ) @$ret[@$i++%2] .= $car ;
			return @$ret[0].'|'.@$ret[1] ;
		}
		
		public function getUrlValeurs()
		{
			return preg_replace('/#TIS_STRUCTURE#/',$this->client,$this->tis_url_qw).'/'.$this->structureId.'/'.$this->questionnaireId.'/'.$this->makeIdOffreTisUpdate($this->id_offre).'/update.aspx' ;
		}
		
		public function getValeurs($refresh=false)
		{
			$http_qweb_html = $this->getUrlValeurs() ;
			
			$this->debug($http_qweb_html) ;
			
			$expiration = ( $refresh ) ? 1 : null ;
			$qweb_html = $this->gimme($http_qweb_html,Array('expiration'=>$expiration)) ;
			$html = $qweb_html ;
			$html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
			
			if ( strlen($html) < 500 && ! preg_match('#<body>#',$html) )
			{
				$this->messageNoValeurs = $html ;
				return false ;
			}
			
			//$html = unhtmlentities($html) ;
			$html = preg_replace('#<input type="hidden" name="__VIEWSTATE" id="__VIEWSTATE"(.*)/>#Umi','',$html) ;
			$html = preg_replace('"\n"',' ',$html) ;
			$html = preg_replace('"\r"',' ',$html) ;
			$html = preg_replace('"\t"',' ',$html) ;
			$html = preg_replace('#<script([^>]*)>(.*)</script>#Umi','',$html) ;
			
			$dom = new DOMDocument ;
			@$dom->loadHTML($html) ;
			$domxpath = new DOMXpath($dom) ;
			
			foreach ( $this->champs as $cle => $champ )
			{
				$this->champs[$cle]->DefaultVal = null ;
				
				$TypeChamp = isset($champ->TypeChampFormulaire) ? $champ->TypeChampFormulaire : @$champ->TypeChamp ;
				// TypeTel TypeMail TypeDateNaissance ne sont pas des TypeChamp mais des TypeChampFormulaire
				// Petite particularité, même si on considère que le TypeChampformulaire est un champ TypeString, il est possible
				// que le champ sur le formulaire Tourinsoft soit un textarea. Dans ce cas on vérifie si TypeChampTisQweb est un TypeTextArea
				if ( $TypeChamp == 'TypeTextarea' || ( isset($champ->TypeChampTisQweb) && $champ->TypeChampTisQweb == 'TypeTextarea' ) )
				{
					$nodes = $domxpath->query('//textarea[@id="'.strtoupper($champ->ID).'"]') ;
					if ( $nodes->length > 0 )
					{
						foreach ( $nodes as $node )
						{
							$this->champs[$cle]->DefaultVal = $node->textContent ;
						}
					}
				}
				elseif ( in_array($TypeChamp,Array('TypeString','TypeDate','TypeDateNaissance','TypeDouble','TypeMail','TypeTel','TypeTime')) )
				{
					$nodes = $domxpath->query('//input[@id="'.strtoupper($champ->ID).'"]') ;
					foreach ( $nodes as $node )
					{
						$this->champs[$cle]->DefaultVal = $node->getAttribute('value') ;
					}
				}
				elseif ( $TypeChamp == 'TypeGuid' )
				{
					$nodes = $domxpath->query('//select[@id="'.strtoupper($champ->ID).'"]//option[@selected="selected"]') ;
					if ( $nodes->length > 0 )
					{
						foreach ( $nodes as $node )
							$this->champs[$cle]->DefaultVal[] = $node->getAttribute('value') ;
					}
					else
					{
						$nodes = $domxpath->query('//input[starts-with(@id,"'.strtoupper($champ->ID).'")][@checked="checked"]') ;
						if ( $nodes->length > 0 )
							foreach ( $nodes as $node )
								$this->champs[$cle]->DefaultVal[] = $node->getAttribute('value') ;
					}
				}
				elseif ( $TypeChamp == 'TypeListGuid' )
				{
					$nodes = $domxpath->query('//input[starts-with(@id,"'.strtoupper($champ->ID).'")][@checked="checked"]') ;
					foreach ( $nodes as $node )
						$this->champs[$cle]->DefaultVal[] = $node->getAttribute('value') ;
				}
				// TypeCGU et TypeNl ne sont pas des TypeChamp mais des TypeChampFormulaire
				elseif ( in_array($TypeChamp,Array('TypeBool','TypeCGU','TypeNl')) )
				{
					$nodes = $domxpath->query('//input[starts-with(@id,"'.strtoupper($champ->ID).'")][@checked="checked"]') ;
					if ( $nodes->length > 0 )
						foreach ( $nodes as $node )
						{
							$nv = $node->getAttribute('value') ;
							$v = null ;
							if ( $node->getAttribute('value') == '' ) $v = 'true' ;
							elseif ( $node->getAttribute('value') == 1 ) $v = 'true' ;
							elseif ( $node->getAttribute('value') == 0 ) $v = 'false' ;
							$this->champs[$cle]->DefaultVal = $v ;
						}
				}
				elseif ( $TypeChamp == 'TypeLecture' )
				{
					$nodes = $domxpath->query('//span[@id="'.strtoupper($champ->ID).'"]') ;
					if ( $nodes->length > 0 )
						foreach ( $nodes as $node )
							$this->champs[$cle]->DefaultVal = $node->textContent ;
				}
			}
			return true ;
		}
		
		/**
		*	Renvoie la valeur affichée dans le formulaire pour le champ $libelle.
		*	@param	string|guid	$libelle	Libellé ou GUID du champ où écrire la valeur
		*	@param	string|array	$valeur	Remplit DefaultVal avec $valeur, qu'il s'agisse d'une valeur simple (string, int...) ou d'un array
		*	@return	bool
		*/
		public function getValeur($libelle)
		{
			if ( $guid = $this->getGuidChamp($libelle) )
			{
				if ( isset($this->champs[$guid]->ValeurDefault) ) return $this->champs[$guid]->ValeurDefault ;
				elseif ( isset($this->champs[$guid]->Valeur) ) return $this->champs[$guid]->Valeur ;
			}
			return false ;
		}
		
		/**
		*	Remplace, dans le formulaire, la valeur intiale. Cette action n'a d'utilité que sur l'affichage du formulaire. Elle sert principalement à mettre une valeur par défaut dans un champ, ou à modifier un champ existant (en le passant en majuscules par exemple).
		*	@param	string|guid	$libelle	Libellé du champ où écrire la valeur
		*	@param	string|array	$valeur	Remplit DefaultVal avec $valeur, qu'il s'agisse d'une valeur simple (string, int...) ou d'un array
		*	@return	bool
		*/
		public function setValeur($libelle,$valeur)
		{
			if ( $guid = $this->getGuidChamp($libelle) )
			{
				$this->champs[$guid]->ValeurDefault = $valeur ;
				return true ;
			}
			return false ;
		}
		
		/**
		*	@param	string|guid	Libellé ou GUID du champ recherché
		*	@return	bool|guid	Renvoie le GUID du champ trouvé ou false si non trouvé
		*/
		public function getGuidChamp($libelle)
		{
			$champs = $this->getChamps() ;
			if ( ! $champs ) return false ;
			
			// Recherche par ID
			if ( $this->isGuid($libelle) && isset($this->champs[$libelle]) ) return $libelle ;
			
			// Recherche par libellé
			foreach ( $champs as $id => $champ )
			{
				if ( isset($champ->Libelle) && $champ->Libelle == $libelle )
				{
					return $id ;
				}
			}
			return false ;
		}
		
		public function getChamp($guid)
		{
			$guid = $this->getGuidChamp($guid) ;
			if ( $guid !== false && isset($this->champs[$guid]) )
			{
				return $this->champs[$guid] ;
			}
			return false ;
		}
		
		
		/**
		*	@params	array	$params['type'] string	null|manuel	Serg à déterminer comment le "checkFormulaire" va vérifier les données. Si le type est manuel, on ne vient pas d'un formulaire HTML : la structure des valeurs passées dans $params['post'] est donc plus simple.
		*					$params['post']	array	Contient la liste des champs en provenance du formulaire. Le plus souvent correspondra à $_POST. Mais si on veut faire un script d'enregistrement automatique par exemple, on passera les valeurs dans un array classique, sans passer par un formulaire HTML et donc sans $_POST.
		*/
		public function save($params)
		{
			$debug = Array() ;
			if ( ! isset($params['type']) ) $params['type'] = null ;
			
			$ko = $this->checkFormulaire($params['post'],$params['type']) ;
			if ( sizeof($ko) > 0 ) return $ko ;
			
			$guids = $this->filesToGuid($params) ;
			
			$champsSave = $this->saveChamps($params,$guids) ;
			
			$this->mailChangements() ; // Envoie le différentiel entre les valeurs initiales et les valeurs enregistrées aux adresses mails définies dans $this->mail_changements.
			
			if ( $this->debug )
			{
				echo '<h2>params['.$params['type'].']</h2>' ;
				echo '<pre>' ;
				var_dump($params) ;
				echo '</pre>' ;
				echo '<h2>$this->getChamps() ;</h2>' ;
				echo '<pre>' ;
				var_dump($this->getChamps()) ;
				echo '</pre>' ;
				echo '<h2>champsSave</h2>' ;
				echo '<pre>' ;
				var_dump($champsSave) ;
				echo '</pre>' ;
				echo 'DEBUG ON : Aucun envoi au serveur' ;
				return false ;
			}
			else
			{
				try {
					$temp = $this->soap->Save(Array(
						'client'=>$this->client,
						'questionnaireId'=>$this->questionnaireId,
						'offre'=>$this->id_offre,
						'champs'=>$champsSave,
						'structureId'=>$this->structureId
					)) ;
				} catch(SoapFault $fault) {
					$this->error($fault,__LINE__) ;
					return false ;
				}
			}
			
			// On supprime le cache de l'offre pour qu'au prochain tour on affiche "offre en cours de validation"
			$this->clear($this->getUrlValeurs()) ;
			return true ;
		}
		
		/**
		*	@return	bool	
		*/
		private function mailChangements()
		{
			if ( ! $this->envoyerMailChangements ) return false ;
			
			$mails_ch = explode(',',$this->mail_changements) ;
			
			foreach ( $mails_ch as $k => $v )
			{
				if ( ! $this->check_mail(trim($v)) ) unset($mails_ch[$k]) ;
				else $mails_ch[$k] = trim($v) ;
			}
			
			if ( sizeof($mails_ch) == 0 ) return false ;
			
			$changes = Array() ;
			$temp = $this->getChamps() ;
			
			foreach ( $temp as $id => $champ )
			{
				/*
				echo '<h1>'.$id.'</h1>' ;
				pre($champ->Items) ;
				pre($champ->DefaultVal) ;
				pre($champ->NouvelleValeur) ;
				*/
				// Champs liste ou cases à cocher
				if ( isset($champ->Items) && is_array($champ->Items) )
				{
					$defaultVals = Array() ;
					
					if ( isset($champ->DefaultVal) && is_array($champ->DefaultVal) )
					{
						$temp2 = $champ->DefaultVal ;
						sort($temp2) ;
						$defaultVals = $temp2 ;
					}
					
					$newVals = Array() ;
					// Cases à cocher multiples : le post est un tableau
					if ( isset($champ->NouvelleValeur) )
					{
						if ( is_array($champ->NouvelleValeur) )
						{
							foreach ( $champ->NouvelleValeur as $k2 => $v2 )
							{
								$temp2 = explode('|',$v2) ;
								$newVals[] = $temp2[0] ;
							}
						}
						else
						{
							$temp2 = explode('|',$champ->NouvelleValeur) ;
							if ( sizeof($temp2) == 2 ) $newVals[] = $temp2[0] ;
						}
					}
					sort($newVals) ;
					
					$defaultVals_comp = implode('_',array_values($defaultVals)) ;
					$newVals_comp = implode('_',array_values($newVals)) ;
					
					if ( $defaultVals_comp != $newVals_comp )
					{
						$newVals_aff = Array() ;
						foreach ( $newVals as $nv ) $newVals_aff[] = $champ->Items[$nv] ;
						
						$defaultVals_aff = Array() ;
						foreach ( $defaultVals as $nv ) $defaultVals_aff[] = $champ->Items[$nv] ;
						
						$changes[$id] = Array('DefaultVal'=>$defaultVals_aff,'NouvelleValeur'=>$newVals_aff,'Libelle'=>$champ->Libelle) ;
					}
				}
				else
				{
					if ( isset($champ->NouvelleValeur) && trim($champ->NouvelleValeur) != trim($champ->DefaultVal) )
						$changes[$id] = Array('DefaultVal'=>$champ->DefaultVal,'NouvelleValeur'=>$champ->NouvelleValeur,'Libelle'=>$champ->Libelle) ;
				}
			}
			
			if ( sizeof($changes) > 0 )
			{
				$table = Array() ;
				$table[] = '<table border="1" width="100%" cellspacing="0" cellpadding="8">' ;
					
					$table[] = '<thead>' ;
						$table[] = '<tr>' ;
							$table[] = '<th>Champ concerné</th>' ;
							$table[] = '<th>Valeur initiale</th>' ;
							$table[] = '<th>Nouvelle valeur</th>' ;
						$table[] = '</tr>' ;
					$table[] = '</thead>' ;
				
				foreach ( $changes as $c )
				{
					$avant = null ;
					$apres = null ;
					
					if ( is_array($c['DefaultVal']) && is_array($c['NouvelleValeur']) )
					{
						sort($c['DefaultVal']) ;
						sort($c['NouvelleValeur']) ;
						
						$moins = array_diff($c['DefaultVal'],$c['NouvelleValeur']) ;
						$plus = array_diff($c['NouvelleValeur'],$c['DefaultVal']) ;
						
						$temp_avant = Array() ;
						foreach ( $c['DefaultVal'] as $dv )
						{
							if ( in_array($dv,$moins) ) $temp_avant[] = '<strike>'.$dv.'</strike>' ;
							else $temp_avant[] = $dv ;
						}
						
						$temp_apres = Array() ;
						foreach ( $c['NouvelleValeur'] as $nv )
						{
							if ( in_array($nv,$plus) ) $temp_apres[] = '<strong>'.$nv.'</strong>' ;
							else $temp_apres[] = $nv ;
						}
						
						$avant = implode(', ',$temp_avant) ;
						$apres = implode(', ',$temp_apres) ;
					}
					else
					{
						if ( is_array($c['DefaultVal']) ) $avant .= implode(' | ',$c['DefaultVal']) ;
							else $avant .= '"'.$c['DefaultVal'].'"' ;
						if ( is_array($c['NouvelleValeur']) ) $apres .= implode(' | ',$c['NouvelleValeur']) ;
							else $apres .= '"'.$c['NouvelleValeur'].'"' ;
					}
					
					$table[] = '<tr>' ;
						$table[] = '<td>'.$c['Libelle'].'</td>' ;
						$table[] = '<td>' ;
							$table[] = $avant ;
						$table[] = '</td>' ;
						$table[] = '<td>' ;
							$table[] = $apres ;
						$table[] = '</td>' ;
					$table[] = '</tr>' ;
				}
				$table[] = '</table>' ;
				
				$champ_titre = $this->getChamp('Nom de l\'offre') ;
				
				$titre = $this->id_offre ;
				if ( $champ_titre ) $titre .= ' &raquo; '.(string)$champ_titre->DefaultVal ;
				
				$message = '<h1>'.$titre.'</h1>'."\r\n" ;
				$message .= '<p>Un formulaire web vient d\'être enregistré :</p>'."\r\n" ;
				$message .= '<p>L\'enregistrement a été fait sur cette page : http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'].'</p>'."\r\n" ;
				$message .= '<p>Vous trouverez ci-dessous un résumé indicatif des modifications détectées : rendez-vous sur Tourinsoft pour consulter la fiche complète.</p>'."\r\n" ;
				$message .= implode("\r\n",$table) ;
				
				$entete = Array() ;
				$additional_parameters = null ;
				
				$entete['MIME-Version'] = '1.0' ;
				$entete['Content-type'] = 'text/html; charset=iso-8859-1' ;
				
				if ( isset($this->mail_expediteur) && $this->check_mail($this->mail_expediteur) )
				{
					$entete['From'] = $this->mail_expediteur . '<'.$this->mail_expediteur.'>' ;
					$additional_parameters = '-f '.$this->mail_expediteur ;
				}
				
				if ( $this->debug )
				{
					echo $message ;
				}
			
				$header = null ;
				foreach ( $entete as $key => $value )
					$header .= $key . ' : ' . $value . PHP_EOL ;
			
				foreach ( $mails_ch as $mc )
				{
					$mc = trim($mc) ;
					if ( $this->check_mail($mc) )
					{
						if ( ! $retour = mail($mc,'Questionnaire Web pour l\'offre '.$this->id_offre,$message,$header,$additional_parameters) )
						{
							$this->debug('Impossible d\'envoyer un mail') ;
							return false ;
						}
						if ( $mc != $this->mail_administrateur )
						{
							$message = 'Initialement destiné à '.$mc.$message ;
							mail($this->mail_administrateur,'Questionnaire Web pour l\'offre '.$this->id_offre,$message,$header,$additional_parameters) ;
						}
					}
				}
			}
			
			return true ;
		}
		
		
		
		
		/**
		*	Cette fonction est utile si on n'a pas de dropzone : le fichier est donc envoyé et stocké dans _FILES.
		*/
		protected function filesToGuid($params)
		{
			$guids = Array() ;
			if ( isset($params['files']) )
			{
				foreach ( $params['files'] as $cle => $file )
				{
					if ( isset($file['error']) && $file['error'] === UPLOAD_ERR_OK )
					{
						$infos = pathinfo($file['name']) ;
						if ( in_array(strtolower($infos['extension']),Array('gif','jpg','jpeg','png')) )
						{
							$langues = Array() ;
							$lng_iso = 'fr-FR' ;
							$titre = null ; $credit = null ;
							
							if ( isset($params['post'][$cle]) )
							{
								foreach ( $params['post'][$cle] as $lng_iso => $vals )
									$langues[] = Array('titre'=>$vals['titre'],'credit'=>$vals['credit'],'langueID'=>$lng_iso) ;
							}
							if ( sizeof($langues) == 0 ) $langues[] = Array('titre'=>'','credit'=>'','langueID'=>'fr-FR') ;
							
							$nom_fichier = $infos['filename'] ;
							$nom_fichier = preg_replace('#\.#','-',$nom_fichier) ;
							$nom_fichier = date('ymdHis').'_'.preg_replace('#[^0-9a-z._-]#Ui','-',$this->sans_accent(mb_strtolower($nom_fichier))) ;
							$nom_fichier .= '.'.strtolower($infos['extension']) ;
							$filepath = $this->dir.'/'.$nom_fichier ;
							// Déplacement du fichier dans le dossier
							move_uploaded_file($file['tmp_name'],$filepath) ;
							
							if ( $guid = $this->envoyerFichier($filepath,$this->dossierId,$langues) )
								$guids[$cle] = $guid ;
						}
					}
				}
			}
			return $guids ;
		}
		
		/**
		*	Transforme les valeurs passées ($params['post']) et les guid de fichiers passés ($guid) dans le format attendu par SaveXML (API Tourinsoft).
		*/
		protected function saveChamps($params,$guids)
		{
			$champsSave = Array() ;
			$toSave = Array('ID','Libelle','TypeChamp','Valeur') ;
			$i = 0 ;
			foreach ( $this->champs as $id => $champ )
			{
				// S'il n'y a ni ID ni TypeChamp, c'est un champ d'affichage qui ne doit pas être envoyé en enregistrement
				if ( ! isset($champ->ID) || ! isset($champ->TypeChamp) ) continue ;
				
				$champsSave[$i] = new stdClass() ;
				
				foreach ( $champ as $k => $v )
					if ( in_array($k,$toSave) )
						$champsSave[$i]->$k = $v ;
				
				if ( isset($champ->NouvelleValeur) && is_array($champ->NouvelleValeur) )
				{
					$valeurs = Array() ;
					foreach ( $champ->NouvelleValeur as $k => $v )
						$valeurs[] = @array_shift(@explode('|',$v)) ;
					$champsSave[$i]->Valeur = implode(',',$valeurs) ;
				}
				elseif ( is_array($guids) && isset($guids[$id]) ) // Il s'agit d'une photo !
				{
					$champsSave[$i]->Valeur = $guids[$id] ;
				}
				else
				{
					$valeur = @array_shift(@explode('|',@$champ->NouvelleValeur)) ;
					if ( $champ->TypeChamp == 'TypeBool' && $valeur != 'true' ) $valeur = 'false' ;
					if ( $valeur == 'true' || $valeur == 'false' ) $valeur = ucfirst($valeur) ;
					$champsSave[$i]->Valeur = @$valeur ;
				}
				$i++ ;
			}
			
			
				/*
				$valeurs = Array() ;
						foreach ( $_POST['brochure'] as $idb => $b )
						{
							$val = ( isset($_POST['qte'][$idb]) && $_POST['qte'][$idb] > 0 && $_POST['qte'][$idb] <= 10 ) ? $_POST['qte'][$idb] : 1 ;
							$valeurs[] = $idb.':'.$this->languesTis['fr'].':'.$val ;
						}
						$champXml .= $tab.$tab.'<Valeur>'.$this->to_xml(implode(';',$valeurs)).'</Valeur>'.$eol ;
				*/
				
			/*
			["brochure"]=>
			array(3) {
			  ["BROCHAUV003V5000TN"]=>
			  array(1) {
				["99ba1d4b-a4dd-4ea8-922c-09108fd8f462"]=>
				string(1) "1"
			  }
			  ["BROCHAUV003V5000TP"]=>
			  array(1) {
				["6318154c-0fa2-40a8-91c4-b8486a97fd12"]=>
				string(1) "1"
			  }
			  ["BROCHAUV003V500MA8"]=>
			  array(1) {
				["99ba1d4b-a4dd-4ea8-922c-09108fd8f462|6318154c-0fa2-40a8-91c4-b8486a97fd12"]=>
				string(1) "1"
			  }
			}
			["qte"]=>
			array(35) {
			  ["BROCHAUV003V5000TN"]=>
			  array(1) {
				["99ba1d4b-a4dd-4ea8-922c-09108fd8f462"]=>
				string(1) "1"
			  }
			  ["BROCHAUV003V5000TP"]=>
			  array(1) {
				["6318154c-0fa2-40a8-91c4-b8486a97fd12"]=>
				string(1) "2"
			  }
			*/
			if (
				( isset($params['post']['brochure']) && is_array($params['post']['brochure']) )
			)
			{
				$cmdBrochures = Array() ;
				foreach ( $params['post']['brochure'] as $id_tis => $lngs )
				{
					$id_langues = @array_shift(@array_keys($lngs)) ;
					$langues = explode('|',$id_langues) ;
					if ( isset($params['post']['qte'][$id_tis][$id_langues]) && preg_match('#^[0-9]+$#',$params['post']['qte'][$id_tis][$id_langues]) && (int) $params['post']['qte'][$id_tis][$id_langues] > 0 )
					{
						// ID_BROCHURE1:ID_ITEM_LANGUE_DE_BROCHURE1:QUANTITE1;...;ID_BROCHUREn:ID_ITEM_LANGUE_DE_BROCHUREn:QUANTITEn
						$cmdBrochure = Array($id_tis,array_shift($langues),(int)$params['post']['qte'][$id_tis][$id_langues]) ;
						$cmdBrochures[] = implode(':',$cmdBrochure) ;
					}
				}
				
				if ( sizeof($cmdBrochures) > 0 )
				{
					foreach ( $champsSave as $k => $v )
					{
						if ( $v->TypeChamp == 'TypeCommandeBrochure' )
						{
							$champsSave[$k]->Valeur = implode(';',$cmdBrochures) ;
						}
					}
				}
			}
			
			return $champsSave ;
		}
		
		/**
		*	@return	array	Retourne la liste des erreurs rencontrées. Le tableau sera vide s'il n'y a pas eu d'erreurs.
		*/
		public function checkFormulaire($post,$type)
		{
			$retour = Array() ;
			
			// Le type sert à déterminer la façon de "tester" les variables.
			// Si on vient d'un formulaire (auto) le $_POST est assez complexe, il est à plusieurs niveaux (ex:inseecommune[577a90da-f4ca-4bf1-828c-2d54709618ec]) : l'identifiant est la clé de chaque champ.
			// En revanche lors d'un traitement automatisé (type="manuel"), le $post est beaucoup plus simple. Il n'a donc pas besoin d'être linearisé.
			if ( $type !== 'manuel' ) $post_l = $this->getPostLinearize($post) ;
			else $post_l = $post ;
			
			$temp = $this->getChamps() ;
			
			foreach ( $temp as $id => $champ )
			{
				$temp = $this->checkChampFormulaire($id,@$post_l[$id]) ;
				if ( $temp !== true && is_array($temp) )
				{
					$retour = array_merge($retour,$temp) ;
				}
			}
			return $retour ;
		}
		
		/**
		*	Vérifie la validité d'un champ en fonction des restrictions le concernant (son type et ses filtres)
		*	@return	bool
		*	@todo	Vérifier les filtres majuscules, minuscules
		*/
		public function checkChampFormulaire($id,$NouvelleValeur)
		{
			$erreurs = Array() ;
			
			if ( ! $champ = $this->getChamp($id) ) { $erreurs[] = 'Champ '.$id.' introuvable...' ; return $erreurs ; }
			$val = $NouvelleValeur ;
			
			$libAff = isset($champ->LibelleAffichage) ? $champ->LibelleAffichage : @$champ->Libelle ;
			$typeChamp = isset($champ->TypeChampFormulaire) ? $champ->TypeChampFormulaire : $champ->TypeChamp ;
			
			// Ajou du 24/08/2015 : un champ lecture était noté obligatoire et posait problème.
			if ( $typeChamp == 'TypeLecture' ) return true ;
			
			$filtres = Array() ;
			foreach ( $this->filtresPossibles as $fp )
			{
				$filtres[$fp] = false ;
				if ( isset($champ->filtres) && is_array($champ->filtres) )
				{
					if ( isset($champ->filtres[$fp]) ) $filtres[$fp] = $champ->filtres[$fp] ;
					else $filtres[$fp] = in_array($fp,$champ->filtres) ;
				}
			}
			
			if ( ! $filtres['obligatoire'] && ( ( is_array($val) && sizeof($val) == 0 ) || ( $val == '' ) ) ) ;
			else
			{
				if ( $filtres['obligatoire'] && ( ( is_array($val) && sizeof($val) == 0 ) || ( $val == '' ) ) )
				{
					$erreurs[] = isset($champ->message) ? $champ->message : 'Le champ '.$libAff.' est obligatoire' ;
				}
			
				if ( $filtres['majuscule'] ) 
				{
					$val = mb_strtoupper($val,'UTF-8') ;
				}
				if ( $filtres['minuscule'] ) 
				{
					$val = mb_strtolower($val,'UTF-8') ;
				}
				
				if ( $typeChamp == 'TypeDouble' || $filtres['nombre'] )
				{
					$champ->valid = true ;
					$val = preg_replace('#\.#',',',$val) ;
					$val = preg_replace('#,+#',',',$val) ;
					$val = preg_replace('[^0-9,]','',$val) ;
					if ( 
						( ! $filtres['obligatoire'] && $val != '' && ! preg_match('#^[0-9]+,?([0-9]*)$#',$val) )
						|| ( $filtres['obligatoire'] && ! preg_match('#^[0-9]+,?([0-9]*)$#',$val) )
					)
						$erreurs[] = $val.' n\'est pas un nombre valide ('.$libAff.')' ;
				}
				elseif( $typeChamp == 'TypeMail' || $filtres['mail'] )
				{
					$champ->valid = true ;
					if (
						( ! $filtres['obligatoire'] && $val != '' && ! checkMail($val) )
						|| ( $filtres['obligatoire'] && ! checkMail($val) )
						)
						if ( $val != '' ) $erreurs[] = $val .' n\'est pas une adresse mail valide ('.$libAff.').' ;
				}
				elseif ( $typeChamp == 'TypeTel' || $filtres['telephone'] )
				{
					$champ->valid = true ;
					$temp = preg_replace('#[^0-9+]#','',$val) ;
					$val = chunk_split($temp,2,' ') ;
					if (
						( ! $filtres['obligatoire'] && $val != '' && ! preg_match('#[+]?[0-9]{10,14}#',$temp) )
						|| ( $filtres['obligatoire'] && ! preg_match('#[+]?[0-9]{10,14}#',$temp) )
						)
						if ( $val != '' ) $erreurs[] = $val .' n\'est pas un téléphone valide ('.$libAff.').' ;
				}
				elseif ( $typeChamp == 'TypeDate' || $typeChamp == 'TypeDateNaissance' )
				{
					$champ->valid = true ;
					$temp = preg_replace('#[^0-9/]#','',$val) ;
					if (
						( ! $filtres['obligatoire'] && $val != '' && ! preg_match('#[0-9]{2}/[0-9]{2}/[0-9]{4}#',$temp) )
						|| ( $filtres['obligatoire'] && ! preg_match('#[0-9]{2}/[0-9]{2}/[0-9]{4}#',$temp) )
						)
						if ( $val != '' ) $erreurs[] = $val .' n\'est pas une date valide ('.$libAff.').' ;
				}
				elseif ( $typeChamp == 'TypeTime' )
				{
					$champ->valid = true ;
					if (
						( ! $filtres['obligatoire'] && $val != '' && ! preg_match('#^[0-9]{2}:[0-9]{2}$#',$val) )
						|| ( $filtres['obligatoire'] && ! preg_match('#^[0-9]{2}:[0-9]{2}$#',$val) )
						)
						if ( $val != '' ) $erreurs[] = $val .' n\'est pas une heure valide ('.$libAff.').' ;
				}
				
				if ( sizeof($erreurs) > 0 )
					$champ->valid = false ;
			}
			
			$this->champs[$id]->NouvelleValeur = $val ;
			
			if ( sizeof($erreurs) > 0 ) 
			
			return ( sizeof($erreurs) > 0 ) ? $erreurs : true ;
		}
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		/**
		*	Affiche une erreur et arrête le script si debug ON
		*	@param	string|array	$fault	Message à afficher ou exception renvoyée
		*	@return	bool	N.C.
		*/
		public function error($fault,$line=null)
		{
			$entete = Array() ;
			$additional_parameters = null ;
			
			if ( isset($this->mail_expediteur) && $this->check_mail($this->mail_expediteur) )
			{
				$entete['From'] = $this->mail_expediteur . '<'.$this->mail_expediteur.'>' ;
				$additional_parameters = '-f '.$this->mail_expediteur ;
			}
			
			$message = null ;
			if ( $line != null ) $message .= 'Erreur ligne '.__LINE__."\n" ;
			$message .= var_export($fault,true) ;
			$message .= var_export($_SERVER,true) ;
		
			$header = null ;
			foreach ( $entete as $key => $value )
				$header .= $key . ' : ' . $value . PHP_EOL ;
			
			/**
			*	Toute erreur detectée en debug entraine l'arrêt du script
			*/
			if ( $this->debug ) { echo '<h1>Error</h1>' ; $this->debug($fault) ; die() ; }
			
			if ( ! $retour = mail($this->mail_administrateur,get_class($this).' error',$message,$header,$additional_parameters) )
			{
				$this->debug('Impossible d\'envoyer un mail') ;
				return false ;
			}
			return true ;
		}
		
		/**
		*	Affiche un message si debug true
		*	@param	string|array	$msg	Message à afficher
		*	@param	bool	$rl	Afficher la date et un retour ligne
		*	@todo	Si on est en debug off, prévoir un envoi de tous les messages logués par mail (ou dans un fichier log ?)
		*/
		public function debug($msg,$rl=true)
		{
			$log = null ;
			
			if ( $rl ) $log .= "\n".date('Y-m-d H:i:s').' | ' ;
			if ( is_array($msg) || is_object($msg) ) $log .= '<pre>'."\n".var_export($msg,true)."\n".'</pre>' ;
			else $log .= $msg ;
			
			if ( $this->debug )	echo $log ;
			// @todo : envoyer le $log par mail si debug off
		}
		
		/**
		*	Vérifie que $guid est bien un GUID valide
		*	@param	guid	$guid N.C.
		*	@return	bool	N.C.
		*/
		protected function isGuid($guid)
		{
			if ( ! is_string($guid) ) return false ;
			return preg_match('/^[A-Za-z0-9]{8}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4}-[A-Za-z0-9]{12}?$/', $guid) ;
		}
		public function is_guid($var) { return $this->isGuid($var) ; }
	
		/**
		*	Vérifie que $mail est un mail valide
		*	@param	string	$mail	N.C.
		*	@return	bool	N.C.
		*/
		private function check_mail($mail)
		{
			return preg_match('#^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]{2,}[.][a-zA-Z]{2,3}$#i',$mail) ;
		}
		
		protected function preparer($var,$striptags=true)
		{
			if ( is_array($var) )
			{
				foreach ( $var as $k => $v ) $var[$k] = $this->preparer($v,$striptags) ;
				return $var ;
			}
			if ( $striptags ) $var = strip_tags($var) ;
			$var = stripslashes(trim($var)) ;
			if ( mb_detect_encoding($var, 'UTF-8, ISO-8859-1') == 'UTF-8' ) $var = utf8_decode($var) ;
			return $var ;
		}
		
		protected function sans_accent($chaine)
		{
			$accent = "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿ" ;
			$noaccent= "AAAAAAACEEEEIIIIDNOOOOOOUUUUYbsaaaaaaaceeeeiiiidnoooooouuuyyby" ;
			return str_replace(str_split_unicode($accent),str_split_unicode($noaccent),$chaine) ;
		}
		
		protected function to_input($input)
		{
			return preg_replace('#"#',htmlentities('"'),(trim($input))) ;
		}
		
		protected function to_textarea($input)
		{
			return preg_replace('#"#',htmlentities('"'),(trim($input))) ;
		}
		
		/**
		*	Transforme les données du formulaire reçu en POST (donc après que la personne l'ait rempli puis ait cliqué sur "Enregistrer") en données structurées et lisibles
		*	@param	array	$post	Liste des champs modifiés
		*	@param	array	$type	Types
		*	@return	array	Tableau structuré
		*/
		private function getPostLinearize($nouvelles_valeurs,$type=null)
		{
			$post_l = Array() ;
			//foreach ( $_POST as $lib => $obj ) 17/12/2014
			foreach ( $nouvelles_valeurs as $lib => $obj )
			{
				if ( is_array($obj) )
				{
					foreach ( $obj as $cle => $valeur )
					{
						if ( is_array($valeur) )
						{
							foreach ( $valeur as $valeur2 )
								$post_l[$cle][] = $valeur2 ;
						}
						else
							$post_l[$cle] = $valeur ;
					}
				}
				else
					$post_l[$lib] = $obj ;
			}
			$this->post_l = $post_l ;
			return $this->post_l ;
		}
		
		/*
		*	Récupère la liste des brochures en fonction du GUID de la syndication 2.0 passées
		*/
		public function getBrochures($guid_syndic_brochures=null)
		{
			$guid = null ;
			
			echo $this->guid_syndic_brochures ;
			
			if ( $this->is_guid($guid_syndic_brochures) ) $guid = $guid_syndic_brochures ;
			elseif ( $this->is_guid($this->guid_syndic_brochures) ) $guid = $this->guid_syndic_brochures ;
			
			if ( $guid == null ) return false ;
			
			$syndic_brochures = 'http://wcf.tourinsoft.com/Syndication/'.$this->client.'/'.$guid.'/Objects?$orderby=ordre' ;
			$xml_broc = $this->gimme($syndic_brochures) ;
			$sxml = simplexml_load_string($xml_broc) ;
			
			if ( ! $sxml ) return false ;
			
			$sxml->registerXPathNamespace('d','http://schemas.microsoft.com/ado/2007/08/dataservices');
			$sxml->registerXPathNamespace('m','http://schemas.microsoft.com/ado/2007/08/dataservices/metadata');
			/*
				<d:SyndicObjectID>BROCHAUV003V500RRX</d:SyndicObjectID>
				<d:Published m:type="Edm.DateTime">2012-05-21T11:39:06.263</d:Published>
				<d:Updated m:type="Edm.DateTime">2013-06-12T10:27:10</d:Updated>
				<d:SyndicObjectName>1</d:SyndicObjectName>
				<d:SyndicStructureId m:type="Edm.Guid">dc9da481-a9ee-4772-8062-1c0a4505a1c6</d:SyndicStructureId>
				<d:GmapLatitude></d:GmapLatitude>
				<d:GmapLongitude></d:GmapLongitude>
				<d:ObjectTypeFix>D6287531-B4B0-496E-9E00-DAFBA403FE35</d:ObjectTypeFix>
				<d:ObjectTypeName>Brochure</d:ObjectTypeName>
				<d:SyndicObjectOrder m:type="Edm.Int64">7</d:SyndicObjectOrder>
					<d:titre>Dépliant Le Pal 2013</d:titre>
					<d:image>http://cdt03.media.tourinsoft.eu/upload/Brochure-bienvenue-a-la-ferme.jpg?width=250&amp;amp;height=180#http://cdt03.media.tourinsoft.eu/upload/adjusted-goutez-l-allier-photoredukto.jpg?width=250&amp;amp;height=180</d:image>
					<d:fichier>Guide des Visites et des Loisirs 2013-2014#Français#GVL2013-BD.pdf#GVL2013_BD#</d:fichier>
					<d:ordre>20</d:ordre>
					<d:stocks>Français#Anglais+278|Français#Anglais+10</d:stocks>
					<d:identifiant>BROCHAUV003V500RRX</d:identifiant>
					<d:publication>allier-tourisme FR</d:publication>
					<d:valide>oui</d:valide>
				<d:image_DIAPO m:null="true" />
			*/
			
			$brochures = Array() ;
			
			foreach ( $sxml->xpath('//m:properties') as $property )
			{
				$ret = Array() ;
				$temp = $property->xpath('d:SyndicObjectID') ;
				$id = (string)array_shift($temp) ;
				$ret['id'] = $id ;
				
				$temp = $property->xpath('d:titre') ;
				$ret['titre'] = (string)array_shift($temp) ;
				
				$temp = $property->xpath('d:image') ;
				$images = (string)array_shift($temp) ;
				$images = explode('#',$images) ;
				//if ( sizeof($images) > 0 ) $ret['image'] = array_shift($images) ;
				if ( sizeof($images) > 0 ) $ret['image'] = array_pop($images) ;
				
				$temp = $property->xpath('d:fichier') ;
				$fichiers = (string)array_shift($temp) ;
				$fichiers = explode('[occurrence]',$fichiers) ;
				if ( sizeof($fichiers) > 0 )
				{
					$fichier = array_shift($fichiers) ;
					$fichier = explode('#',$fichier) ;
					if ( sizeof($fichier) > 2 )
						$ret['fichier'] = 'http://'.$this->client.'.media.tourinsoft.eu/upload/'.$fichier[2] ;
				}
				
				$temp = $property->xpath('d:stocks') ;
				$stocks = (string)array_shift($temp) ;
				$stocks = explode('|',$stocks) ; // Français+Anglais#1280|Néerlandais#121
				if ( sizeof($stocks) > 0 )
				{
					foreach ( $stocks as $stock )
					{
						$temp = explode('#',$stock) ;
						if ( isset($temp[1]) )
						{
							if ( ! isset($ret['stocks']) ) $ret['stocks'] = Array() ;
							
							$lngs = explode('+',$temp[0]) ;
							$langues = Array() ;
							foreach ( $lngs as $v )
							{
								if ( isset($this->languesBrochures[$v]) )
									$langues[$this->languesBrochures[$v]] = $v ;
							}
							$stock = Array() ;
							$stock['langues'] = $langues ;
							$stock['stock'] = $temp[1] ;
							$ret['stocks'][] = $stock ;
							break ;
						}
					}
				}
				
				$brochures[$id] = $ret ;
			}
			
			return $brochures ;
			
		}
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		/*
			**************************
			**************************
			**************************
			Classes récupérées de TisQWSMedias.class.php
			**************************
			**************************
			**************************
		*/
		
		/**
		*	Envoyer le fichier $path (local ou distant) dans le dossier $dossierId avec les textes contenus dans $langues
		*	@param	string	$path	Chemin du fichier à envoyer. Peut être une url (http://...) ou un fichier local (/home/...)
		*	@param	guid	$dossierId	Identifiant du dossier sur la médiathèque de Tourinsoft
		*	@param	array	$langues	Tableau des descriptions texte de l'image (titre, description...)
		*		Ex : Array(
		*				Array('titre'=>'Titre en Français','credit'=>'Crédit en Français','langueID'=>'fr-FR'),
		*				Array('titre'=>'Titre en Anglais','credit'=>'Crédit en Anglais','langueID'=>'en-EN')
		*			)
		*	@return	guid|false	Renvoie l'identifiant (guid) de l'image ajoutée à la médiathèque, ou false si une erreur s'est produite
		*/
		public function envoyerFichier($path,$dossierId,$langues)
		{
			$retour = false ;
			if ( ! $this->isGuid($dossierId) ) { $this->error('$dossierId !guid') ; return false ; }
			
			$this->debug("nTisQWSMedias->envoyerFichier(".$path.",".$dossierId.",[textes])") ;
			
			if ( ! ( $file_content = file_get_contents($path) ) ) { $this->error('! file_get_contents('.$path.')') ; return false ; }
			$base64_file = base64_encode($file_content) ;
			$base64_array = str_split($base64_file,$this->packet_size) ;
			
			$this->debug(sizeof($base64_array).' paquets à envoyer : [',false) ;
			
			$guid = null ;
			foreach ( $base64_array as $i => $data )
			{
				$this->debug('.',false) ;
				if ( $guid == null )
				{
					try {
						$uploadFileResponse = $this->soap_media->uploadFile(Array('client'=>$this->client,'data'=>$data,'questionnaireId'=>$this->questionnaireId)) ;
						$guid = (string) $uploadFileResponse->uploadFileResult ;
					} catch (SoapFault $fault) { $this->error($fault,__LINE__) ; }
				}
				else
				{
					try {
						$this->soap_media->uploadFileNext(Array('client'=>$this->client,'id'=>$guid,'data'=>$data,'questionnaireId'=>$this->questionnaireId)) ;
					} catch (SoapFault $fault) { $this->error($fault,__LINE__) ; }
				}
			}
			$this->debug(']',false) ;
			
			$info = Array(
				'dossierId' => $dossierId,
				'lgs' => $langues,
				'structureId' => $this->structureId
			) ;
			
			if ( $guid !== null )
			{
				$fileName = utf8_encode(basename($path)) ;
				$fileName = date('ymdhis').'_'.$fileName ; // Ajout du 24/06/2015 pour éviter que 2 fichiers aient le même nom sur le serveur : AI est incapable de régénérer les miniatures sinon
				$params = Array('client'=>$this->client,'id'=>$guid,'fileName'=>$fileName,'info'=>$info,'questionnaireId'=>$this->questionnaireId) ;
				try {
					$addMediaResponse = $this->soap_media->addMedia($params) ;
					$retour = (string) $addMediaResponse->addMediaResult ;
				} catch (SoapFault $fault) { $this->error($fault,__LINE__) ; }
			}
		
			$this->debug('TisQWSMedias->envoyerFichier() return '.(($retour)?$retour:'ko')) ;
			return $retour ;
		}
		
		/**
		*	Renvoie le GUID du dossier dont le nom est $params['dossierNom'], qui sera recherché dans le dossier dont le GUID est $params['dossierParent']
		*	@param	Array	$params	Contient tous les paramètres
		*							$params['dossierParentId']	(obligatoire)	GUID du dossier racine où rechercher le dossier
		*							$params['dossierNom']	(obligatoire)	Nom du dossier que l'ont recherche (ou que l'on crée)
		*	@return	guid|false	Retourne l'identifiant du dossier trouvée ou créé
		*/
		public function getDossier($params)
		{
			if ( isset($params['dossierParentId']) ) $dossierParentId = $params['dossierParentId'] ; else return false ;
			if ( isset($params['dossierNom']) ) $dossierNom = $params['dossierNom'] ; else return false ;
			if ( ! $this->isGuid($dossierParentId) ) return false ;
			
			try {
				$this->debug('getDossier('.$dossierParentId.','.$dossierNom.')') ;
				$res = $this->soap_media->getDossiers(Array(
					'client'=>$this->client,
					'questionnaireId'=>$this->questionnaireId,
					'structureId'=>$this->structureId,
					'utilisateurId'=>$this->utilisateurId,
					'dossierParentId'=>$dossierParentId
				)) ;
				$dossiers = $res->getDossiersResult->ImportMediaDossier ;
				
				foreach ( $dossiers as $dossier )
				{
					// Le dossier est trouvé
					if ( $dossierNom == $dossier->dossierNom )
					{
						var_dump($dossier->dossierId) ;
						if ( isset($params['viderDossier']) && $params['viderDossier'] === true )
							$this->viderDossier($dossier->dossierId) ;
						return $dossier->dossierId ;
					}
				}
				
				if ( isset($params['creerDossier']) && $params['creerDossier'] === true )
				{
					// Le dossier n'a pas été trouvé : on le crée
					$nouveauDossier = Array(
						'dossierNom' => $dossierNom,
						'dossierParentId' => $dossierParentId
					) ;
					if ( $ret = $this->soap_media->addDossier(Array(
						'client'=>$this->client,
						'questionnaireId'=>$this->questionnaireId,
						'structureId'=>$this->structureId,
						'info'=>$nouveauDossier
					)) )
					{
						if ( isset($ret->addDossierResult) )
							return $ret->addDossierResult ;
					}
				}
				
			} catch(SoapFault $fault) { $this->error($fault,__LINE__) ; }
			return false ;
		}
		
		/**
		*	Vide le dossier $dossierId des images qu'il contient
		*	@param	guid	$dossierId N.C.
		*	@param	array	$params	Paramètres supplémentaires
		*	@param	bool	$params['supprimerRacine']	Supprime le dossier $dossierID
		*	@param	bool	$params['supprimerSousDossiers']	Supprime les sous-dossiers de $dossierID
		*	@todo	interpréter $params['supprimerRacine']
		*	@todo	interpréter $params['supprimerSousDossiers']
		*/
		private function viderDossier($dossierId,$params=null)
		{
			// Refuser de supprimer si on a pas un vrai Guid
			// Ca ne protège pas de tout mais ça évitera au moins une suppression de la racine (pas testé si c'était faisable...)
			if ( ! $this->isGuid($dossierId) ) return false ;
			
			$guids = Array() ;
			$images = $this->getFichiers($dossierId) ;
			foreach ( $images as $img )
				if ( isset($img->elementId) && $this->isGuid($img->elementId) )
					$guids[] = $img->elementId ;
			
			if ( is_array($guids) && sizeof($guids) > 0 )
				$this->supprimerDocuments($guids) ;
			
			// On supprime le dossier qu'on vient de vider ?
			// TODO
			if ( is_array($params) && isset($params['supprimerRacine']) && $params['supprimerRacine'] === true )
			{
				
			}
			
			// On supprime également les sous dossiers (dangereux... à faire si besoin)
			// TODO
			if ( is_array($params) && isset($params['supprimerSousDossiers']) && $params['supprimerSousDossiers'] === true )
			{
				
			}
			return true ;
		}
		
		/**
		*	Supprime tous les documents dont le guid est contenu dans $guids, peu importe son emplacement dans les dossiers de la médiathèque.
		*	@param	guid|array	$guids	GUID du fichier, ou tableau des GUIDs des fichiers à supprimer
		*	@param	array	$params	Paramètres supplémentaires
		*	@param	string	$params['importMediaTypeAction']	SupressionMedia|SupressionAssociationOffreMedia|SupressionOccurrenceOffreMedia : voir http://api-doc.tourinsoft.com/#/questionnaire-web#api-services-medias pour les détails. Defaut : SupressionOccurrenceOffreMedia (supprime le média et les occurrences associées aux offres)
		*	@return	bool
		*/
		private function supprimerDocuments($guids,$params=null)
		{
			$TypeActions = Array('SupressionMedia','SupressionAssociationOffreMedia','SupressionOccurrenceOffreMedia') ;
			$splitsize = 50 ;
			$importMediaTypeAction = 'SupressionOccurrenceOffreMedia' ;
			if ( is_array($params) && isset($params['importMediaTypeAction']) && in_array($params['importMediaTypeAction'],$TypeActions) )
				$importMediaTypeAction = $params['importMediaTypeAction'] ;
			
			if ( ! is_array($guids) ) $guids = Array($guids) ;
			foreach ( $guids as $k => $guid )
				if ( ! $this->isGuid($guid) )
					unset($guids[$k]) ;
			
			if ( sizeof($guids) == 0 ) return false ;
			
			// On sépare en groupes de 10 images
			$groupes = array_chunk($guids,$splitsize) ;
			
			$retour = true ;
			
			$this->debug('supprimerDocuments() ['.sizeof($guids).' fichiers séparées en '.sizeof($groupes).' groupes de '.$splitsize.' fichiers]') ;
			
			$i = 1 ;
			foreach ( $groupes as $groupe )
			{
				$time_start = microtime(true) ; 
				if ( ! $this->soap_media->DeleteElement(Array(
					'client'=>$this->client,
					'utilisateurId'=>$this->utilisateurId,
					'structureId'=>$this->structureId,
					'ids'=>$groupe,
					'importMediaTypeAction' => $importMediaTypeAction,
					'questionnaireId'=>$this->questionnaireId
				)) ) return false ;
				
				$time = microtime(true) - $time_start ;
				$this->debug('supprimerDocuments() ['.$i++.'/'.sizeof($groupes).'] '.round($time,2).'s. ('.round(($time/sizeof($groupe)),2).'s. par fichier)') ;
			}
			
			$this->debug('supprimerDocuments() [terminé]') ;
			
			return $retour ;
		}
		
		/**
		*	Renvoie les fichiers contenus dans $dossierId. Attention : les fichiers sont renvoyés dans un tableau. Chaque fichier est un objet composé sur le modèle suivant :
		*		<xs:element minOccurs="0" name="elementCredit" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementDateCrea" type="xs:dateTime"/>
		*		<xs:element minOccurs="0" name="elementDateMaj" type="xs:dateTime"/>
		*		<xs:element minOccurs="0" name="elementHeight" nillable="true" type="xs:int"/>
		*		<xs:element minOccurs="0" name="elementId" type="ser:guid"/>
		*		<xs:element minOccurs="0" name="elementNom" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementSize" type="xs:int"/>
		*		<xs:element minOccurs="0" name="elementUrl" nillable="true" type="xs:string"/>
		*		<xs:element minOccurs="0" name="elementWidth" nillable="true" type="xs:int"/>
		*		Voir http://wcf.tourinsoft.com/Mediatheque/ImportMediaService.svc?xsd=xsd2 pour le détail
		*	@param	guid	$dossierId	N.C.
		*	@return	array|false
		*/
		public function getFichiers($dossierId)
		{
			
			$numPage = 0 ;
			$this->debug('getFichiers('.$dossierId.')') ;
			$ret_fichiers = Array() ;
			// La fonction ne renvoie les fichiers que par lots de 100 : on doit donc la faire tourner jusqu'à ce que le retour soit de moins de 100 fichiers.
			while ( $numPage == 0 || ( isset($fichiers) && is_array($fichiers) && sizeof($fichiers) >= 100 ) )
			{
				$this->debug('getFichiers('.$dossierId.') : getElementsByDossier('.$this->client.','.$this->questionnaireId.','.$this->structureId.','.$this->utilisateurId.','.$dossierId.','.$numPage.')') ;
				try {
					if ( $res = $this->soap_media->getElementsByDossier(Array(
						'client'=>$this->client,
						'questionnaireId'=>$this->questionnaireId,
						'structureId'=>$this->structureId,
						'utilisateurId'=>$this->utilisateurId,
						'dossierId'=>$dossierId,
						'numPage'=>$numPage++
					)) )
					{
						if ( isset($res->getElementsByDossierResult->ImportMediaElementInfo) )
						{
							$fichiers = $res->getElementsByDossierResult->ImportMediaElementInfo ;
							foreach ( $fichiers as $fic )
								$ret_fichiers[] = $fic ;
							$this->debug('getFichiers('.$dossierId.') : getElementsByDossier('.$numPage.') : '.sizeof($fichiers)) ;
						}
					}
				} catch(SoapFault $fault) { $this->error($fault,__LINE__) ; return false ; }
			}
			$this->debug('getFichiers('.$dossierId.') : '.sizeof($ret_fichiers).' fichiers') ;
			return $ret_fichiers ;
		}
		
	}
	