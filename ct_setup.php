<?php
	// Validating key
	if(isset($_POST['action']) && $_POST['action'] == 'key_validate' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkHelper.php');
		$result = CleantalkHelper::noticeValidateKey($_POST['key']);
		die(json_encode($result));
	}
	
	// Gettings key
	if(isset($_POST['action']) && $_POST['action'] == 'get_key' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
		require_once('cleantalk/lib/CleantalkHelper.php');
		$result = CleantalkHelper::getApiKey($_POST['email'], $_SERVER['SERVER_NAME'], 'php-uni');
		die(json_encode($result));
	}
	
	// Installation
	if(isset($_POST['action']) && $_POST['action'] == 'install' && $_POST['security'] == md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME'])){
			
		// Additions to INDEX.PHP
		
		$path_to_index = getcwd() . '/index.php';	
		if(!file_exists($path_to_index)){
			die(json_encode(array('error' => 'Unable to find index.php in the ROOT directory.')));
		}
		
		// Parsing params
		if(preg_match('/^[a-z0-9]{1,20}$/', $_POST['key'], $matches)){
			$api_key = $matches[0];
		}else{
			die(json_encode(array('error' => 'Key is bad. Key is "'.$_POST['key'].'"')));
		}
		
		$index_file = file_get_contents($path_to_index);
		
		$php_open_tags  = preg_match_all("/(<\?)/", $index_file);
		$php_close_tags = preg_match_all("/(\?>)/", $index_file);
		
		$file_lenght     = strlen($index_file);
		$first_php_start = strpos($index_file, '<?');
		$first_php_end   = strpos($index_file, '?>');
		$last_php_end    = strrpos($index_file, '?>');
		
		// Adding <?php to the strat if it's not there
		if($first_php_start !== 0)
			$index_file = "<?php\n\t\n\t\n?>".$index_file;
		
		// Adding ? > to the end if it's not there
		if($php_open_tags <= $php_close_tags)
			$index_file = $index_file."\n\n<?php";
		
		// Addition to index.php Top
		$top_code_addition = "//Cleantalk\n\trequire_once( getcwd() . '/cleantalk/cleantalk.php');";
		$index_file = preg_replace('/(<\?php)|(<\?)/', "<?php\n\t\n\t" . $top_code_addition, $index_file, 1);
		
		// Addition to index.php Bottom (JavaScript test)
		$bottom_code_addition = 
			"\n\n\t//Cleantalk\n"
			."\tif(isset(\$_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(\$_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){\n"
				."\t\tdie();\n"
			."\t}\n"
			."\techo \"<script>var apbct_checkjs_val = '\$apbct_checkjs_val';</script><script src='cleantalk/js/js_test.js'></script>\";\n";
		$index_file = $index_file.$bottom_code_addition;
		
		$fd = fopen($path_to_index, 'w') or die("Unable to open index.php");
		fwrite($fd, $index_file);
		fclose($fd);
		
	// Additions to CT_CONFIG.PHP
		
		$path_to_config = getcwd() . '/cleantalk/ct_config.php';
		$code_addition  = "//Auth key";
		$code_addition .= "\n\t\$auth_key = '$api_key';";
		
		$file_content = file_get_contents($path_to_config);
		$file_content = preg_replace('/(<\?php)|(<\?)/', "<?php\n\t\n\t" . $code_addition, $file_content, 1);
		
		$fd = fopen($path_to_config, 'w') or die('Unable to open ct_config.php');
		fwrite($fd, $file_content);
		fclose($fd);
		
	// Delete instllation file
		unlink(__FILE__);
	
		die(json_encode(array(
			'success' => true
		)));
		
	}
	
?>
<html>
  <head>  	
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <link rel="shortcut icon" href="cleantalk/img/ct_logo.png"> 
    <link href="https://fonts.googleapis.com/css?family=Lato" rel="stylesheet">

	<title>Universal Anti-Spam Plugin by CleanTalk</title>
    <!-- Bootstrap core CSS -->
    <link href="cleantalk/css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles -->
    <link href="cleantalk/css/setup-wizard.css" rel="stylesheet">   
    
    <link href="cleantalk/css/animate-custom.css" rel="stylesheet"> 
   
  </head>
    <body class="fade-in">
    	<!-- start setup wizard box -->
    	<div class="container" id="setup-block">
    		<div class="row">
			    <div class="col-sm-6 col-md-4 col-sm-offset-3 col-md-offset-4">
			    	 
			       <div class="setup-box clearfix animated flipInY">
			       		<div class="page-icon animated bounceInDown">
			       			<img  src="cleantalk/img/ct_logo.png" alt="Cleantalk logo" />
			       		</div>
			        	<div class="setup-logo">
			        		<h3> - Universal Anti-Spam Plugin - </h3>
			        	</div> 
			        	<hr />
			        	<div class="setup-form">
			        		  <div class="alert alert-success alert-dismissible fade in" style="display:none" role="alert">
							    <strong>Success!</strong> <p>Enter to your <a class="underlined" href="https://cleantalk.org/my/">CleanTalk dashboard</a> to view statistics.</p>
							    <br />
								<p>File ctsetup.php was deleted automatically. This page doesn't exists anymore.</p>
								<br />
								<p>You can test any form on your website by using special email stop_email@example.com. Every submit with this email will be blocked.</p>
							  </div>
			        		<!-- Start Error box -->
			        		<div class="alert alert-danger alert-dismissible fade in" style="display:none" role="alert">
								  <button type="button" class="close" > &times;</button>
								   <p id='error-msg'></p>
							</div> <!-- End Error box -->
			        		<form action = 'javascript:void(null);' method="post" id='setup-form'>
						   		 <input type="text" placeholder="Access key or e-mail" class="input-field" required/> 

						   		 <button type="submit" class="btn btn-setup" disabled>Install</button> 
							</form>	
							<div class="setup-links"> 
					            <a href="https://cleantalk.org/publicoffer" target="_blank">
					          	   License agreement
					            </a>
					            <br />
					            <a href="https://cleantalk.org/register?platform=php-uni&website=<?php echo $_SERVER['SERVER_NAME']; ?>" target="_blank">
					              Don't have an account? <strong>Create here!</strong>
					            </a>
							</div>      		
			        	</div> 			        	
			       </div>			        
			    </div>
			</div>
    	</div>
     
      	<!-- End setup-wizard wizard box -->
     	<footer class="container">
     		<p id="footer-text"><small>Please, check the extension for your CMS on our <a href="https://cleantalk.org/help/install" target="_blank">plugins page</a> before setup</small></p>
     		<p id="footer-text"><small>It is highly recommended to create a backup before installation</small></p>
     	</footer>

        <script src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
        <script>window.jQuery || document.write('<script src="cleantalk/js/jquery-1.9.1.min.js"><\/script>')</script> 
        <script src="cleantalk/js/bootstrap.min.js"></script> 
        <script src="cleantalk/js/placeholder-shim.min.js"></script>        
        <script src="cleantalk/js/custom.js"></script>
        <script src="cleantalk/js/detect_cms.js"></script>
		<script type='text/javascript'>
			var security = '<?php echo md5($_SERVER['REMOTE_ADDR'].$_SERVER['SERVER_NAME']) ?>';
		</script>

    </body>
</html>
