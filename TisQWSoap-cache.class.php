
		/**
	   * get pathname for $key (generated with sha1 from $key)
	   *
	   * @param string $key
	   * @return string
	   */
		private function _name($key)
		{
		return sprintf("%s/%s", $this->cacheDir, sha1($key));
	  }

		/**
	   * get content from cache with key $key
	   * invalid cache file if expirated
	   *
	   * @param string $key
	   * @param int $expiration
	   * @return mixed bool or content from cache
	   */
		public function get($key = 'default', $expiration = null)
		{
		if ( isset($_GET['refresh']) || $this->debug ) $expiration = 0 ;
		elseif ( $expiration == null ) $expiration = $this->expiration ;
		
		// test if cache dir exists and writable
		if ( !is_dir($this->cacheDir) || !is_writable($this->cacheDir) )
		{
			echo $this->cacheDir ;
		  return false;
		}

		// test if cache file exists
		$cache_path = $this->_name($key);
		if (!@file_exists($cache_path))
		{
		  return false;
		}

		// test cache expiration (clear file if expired)
		if (filemtime($cache_path) < (time() - $expiration))
		{
		  $this->clear($key);
		  return false;
		}

		// test file readable
		if (!$fp = @fopen($cache_path, 'rb'))
		{
		  return false;
		}

		// lock file and get cache file content
		flock($fp, LOCK_SH);
		$cache = '';
		if (filesize($cache_path) > 0)
		{
			$cache = unserialize(fread($fp, filesize($cache_path)));
		}
		else
		{
			$cache = NULL;
		}
		flock($fp, LOCK_UN);
		fclose($fp);

		return $cache;
	  }

		/**
		* set cache content for key $key
		*
		* @param string $key
		* @param mixed $data
		* @return bool
		*/
		public function set($key = 'default', $data = '')
		{
			if ( !is_dir($this->cacheDir) || !is_writable($this->cacheDir))
			{
				return false;
			}

			$cache_path = $this->_name($key);
			
			if ( ! $fp = fopen($cache_path, 'wb'))
			{
			  return false;
			}

			// lock file and set content
			if (flock($fp, LOCK_EX))
			{
			  fwrite($fp, serialize($data));
			  flock($fp, LOCK_UN);
			}
			else
			{
			  return false;
			}
			fclose($fp);
			@chmod($cache_path, 0777);
			return true;
		}

		/**
		* clear cache for key $key
		*
		* @param string $key
		* @return bool
		*/
		public function clear($key = 'default')
		{
			$cache_path = $this->_name($key);
			if (file_exists($cache_path))
			{
			  unlink($cache_path);
			  return true;
			}

			return false;
		}

		public function gimme($url,$params=null)
		{
			$this->response_code = null ;
			$retour = false ;
			
			$url = preg_replace('# #','',$url) ;
			
			if ( $this->debug || ! $retour = $this->get($url,( ( is_array($params) && isset($params['expiration']) ) ? $params['expiration'] : $this->expiration )) )
			{
				$opts = array('http' =>
					array(
						'method' => 'GET',
						'max_redirects' => '0',
						'ignore_errors' => 0,
						'request_fulluri' => True, // 24/06/2015 - http://php.net/manual/en/context.http.php#110449
						'header'=>	"Connection: close" . "\r\n" .
									"User-Agent: " . $this->user_agent . " \r\n"
					)
				);
				$context = stream_context_create($opts);
				if ( $stream = fopen($url, 'r', false, $context) )
				{
					$stream_retour = null ;
					while ( ! feof($stream) ) $stream_retour .= fgets($stream,4096) ;
					$stream_retour = preg_replace('#\n#',' ',$stream_retour) ;
					$stream_retour = preg_replace('#\t#',' ',$stream_retour) ;
					$stream_retour = preg_replace('#\r#',' ',$stream_retour) ;
					$retour = $stream_retour ;
					//echo $retour ;
				}
				else
				{
					$curl = curl_init();
					
					curl_setopt_array( $curl, array(
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_NOBODY => ! ( is_array($params) && isset($params['force']) && $params['force'] ) ? $params['force'] : $this->force, // Si on force (true), on veut le body (donc nobody false)
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_URL => $url,
						CURLOPT_USERAGENT => $this->user_agent
					));
					$result = curl_exec( $curl );
					$response_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
					curl_close( $curl );
					
					$this->response_code = $response_code ;
					
					//if ( $force ) return $result ;
					
					if ( $response_code == '404' ) { return false ; } // Le fichier n\'existe plus
					elseif ( $response_code == '400' ) { return false ; }
					elseif ( true )  // Le fichier est inaccessible (pb de serveur...) : On essaye de récupérer un "vieux" fichier dans le cache
					{
						if ( ! $retour = $this->get($url,999999999) ) return false ;
					}
				}
				
				$this->set($url,$retour) ;
			}
			return $retour ;
		}
		