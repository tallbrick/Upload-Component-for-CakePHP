<?php
/*
* File: /includes/controllers/components/upload.php
* A file uploader component for CakePHP
*
* @link https://github.com/tallbrick/upload-component-for-CakePHP
* @author Chris Bricker
* @version 0.1
* @license MIT
*
* Example Usage:
*
*	$this->Upload->set_destination('/_my/_destination/_directory/_from/_document/_root/');
*	$this->Upload->extensions = array('jpg', 'jpeg', 'gif', 'png', 'bmp');
*		
*	if( $this->Upload->file_is_uploaded('form_field_name') ){
*		if( $this->Upload->process('form_field_name') ){
*		
*			// Upload Success. Uploaded file details are accessible here:
*			$this->Upload->info['filename'];  //Name of the file - after upload
*			$this->Upload->info['origname'];  //Original name of the file - before upload
*			$this->Upload->info['extension']; //File extension/type
*			$this->Upload->info['directory']; //Location of file on the server
*		}
*	}else{
*		$this->Session->setFlash('File not uploaded.  Double check that your form has the `enctype="multipart/form-data"` attribute.');
*	}
*
*/

App::import('Core', 'Inflector');

class UploadComponent extends Object{

    var $info = array();
	var $config = array();
	var $fieldname = '';
	var $filename = '';
	var $tmp_filename = '';
	var $destination = '';
	
	var $permissions = '0777';
	var $extensions = array('jpg','gif','png');
	var $create_directories = true;
	
	var $debug = false;
	var $__DOCUMENT_ROOT;
	var $__DS; 


	/**
	 * Initialization method. You may override configuration options from a controller
	 *
	 * @param $controller object
	 * @param $config array
	 */
    function initialize(&$controller, $config) {
		$this->controller = $controller;
		$model_prefix = Inflector::tableize($controller->modelClass); // lower case, studley caps -> underscores
		$prefix = Inflector::singularize($model_prefix); // make singular. 
		
		$this->config = array_merge(
			array('default_col' => $prefix), 	/* default column prefix is lowercase, singular model name */
			$this->config, 						/* default general configuration */
			$config 							/* overriden configurations */
		);
		
		
		if(defined('DS')){
			$this->__DS = DS;
		}else{
			$this->__DS = '/';
		}
		if(defined('WWW_ROOT')){
			$this->__DOCUMENT_ROOT = (substr(WWW_ROOT,-1,1) != $this->__DS)? WWW_ROOT.$this->__DS : WWW_ROOT;
		}else{
			$this->__DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'] . $this->__DS;
		}	  
	}


	/**
	 * Process the upload the specified file
	 *
	 * @param string $field_name - The fieldname from the upload form
	 * @param string $filename   - The name of the file, once uploaded
	 * @return boolean True on success, false on failure
	 */
	function process($field_name = '', $filename = NULL){
		
		if($field_name != ''){
			$this->set_fieldname($field_name);
		}
		
		if( !$filename ){ 
			$filename = uniqid(); 
		}
		$this->set_filename( $filename );
		
		if( $this->file_is_uploaded() ){
			if ( ($this->validate() == true) && ($this->save() == true) ) {
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}	
	}


	/**
	 * Tries to find the details about the uploaded file
	 *
	 */
	function get_uploaded_file_info(){
		
		if (isset($_FILES[(string)$this->fieldname])) {
			$this->info = array(
				'name' 		=> $_FILES[$this->fieldname]['name'],
				'type' 		=> $_FILES[$this->fieldname]['type'],
				'size' 		=> $_FILES[$this->fieldname]['size'],
				'error' 	=> $_FILES[$this->fieldname]['error'],
				'tmp_name' 	=> $_FILES[$this->fieldname]['tmp_name']
			);
		} elseif (isset($GLOBALS['HTTP_POST_FILES'][(string)$this->fieldname])) {
			$this->info = array(
				'name' 		=> $_POST[$this->fieldname]['name'],
				'type' 		=> $_POST[$this->fieldname]['type'],
				'size' 		=> $_POST[$this->fieldname]['size'],
				'error' 	=> $_POST[$this->fieldname]['error'],
				'tmp_name' 	=> $_POST[$this->fieldname]['tmp_name']
			);
		} else {
			$this->info = array(
				'name' 		=> (isset($GLOBALS[$this->fieldname . '_name']) ? $GLOBALS[$this->fieldname . '_name'] : ''),
				'type' 		=> (isset($GLOBALS[$this->fieldname . '_type']) ? $GLOBALS[$this->fieldname . '_type'] : ''),
				'size' 		=> (isset($GLOBALS[$this->fieldname . '_size']) ? $GLOBALS[$this->fieldname . '_size'] : ''),
				'error' 	=> (isset($GLOBALS[$this->fieldname . '_error'])? $GLOBALS[$this->fieldname . '_error'] : ''),
				'tmp_name' 	=> (isset($GLOBALS[(string)$this->fieldname]) ? $GLOBALS[(string)$this->fieldname] : '')
			);
		}
		
		//Extract the file's details via pathinfo()
		if( isset($this->info['name']) && strlen(trim($this->info['name'])) > 0 ){
			$this->info = array_merge($this->info, $this->_pathinfo($this->info['name']));
			
			$this->info['origname'] = $this->info['name'];
			$this->info['filename'] = $this->filename;
		}
		
		return $this->info;
	}


	/**
	 * Decides if the file has been uploaded
	 *
	 * @param string $field_name - The fieldname from the upload form
	 * @return boolean True on success, false on failure
	 */
	function file_is_uploaded($field_name = ''){
		
		if($field_name != ''){
			$this->set_fieldname($field_name);
		}
			
		$file = $this->get_uploaded_file_info();
		if ( tep_not_null($file['tmp_name']) && ($file['tmp_name'] != 'none') && is_uploaded_file($file['tmp_name']) ) {
			return true;
		}else{
			return false;
		}
	}


    /**
	 * Validate the uploaded file
	 *
	 * @return boolean True on success, false on failure
	 */
	function validate() {
	  
		$file = $this->get_uploaded_file_info();
		
		if ( strlen(trim($file['tmp_name'])) > 0 && ($file['tmp_name'] != 'none') && is_uploaded_file($file['tmp_name']) ) {
			
			//Validate file type/extension
			if (sizeof($this->extensions) > 0) {
				if (!in_array($file['extension'], $this->extensions)) {
					$this->log_error_and_return("The '.{$file['extension']}' file type is not allowed.  Try converting your file to one of the supported formats: ".implode(', ', $this->extensions)."");
				}
			}
			
			$this->set_tmp_filename($file['tmp_name']);
	
			return $this->check_destination( $this->destination );
		} else {
			switch ($file['error']){
				case UPLOAD_ERR_INI_SIZE:
				case 1:
					$error = 'The file is bigger than this PHP installation allows.';
					break;
				case UPLOAD_ERR_FORM_SIZE:
				case 2:
					$error = 'The file is bigger than this form allows.';
					break;
				case UPLOAD_ERR_PARTIAL:
				case 3:
					$error = 'Only part of the file was uploaded.';
					break;
				case 4:
				case 5:
					$error = 'No file was uploaded.';
					break;
				case UPLOAD_ERR_NO_TMP_DIR:
				case 6:
					$error = 'Missing a temporary folder.';
					break;
				case UPLOAD_ERR_CANT_WRITE:
				case 7:
					$error = 'Failed to write file to disk.';
					break;
				case UPLOAD_ERR_EXTENSION:
				case 8:
					$error = 'A PHP extension stopped the file upload. PHP does not provide a way to ascertain which extension caused the file upload to stop; examining the list of loaded extensions with phpinfo() may help.';
					break;
				case UPLOAD_ERR_NO_FILE:
				default:
					$error = 'No file was uploaded.';
					break;
			}	
			
			debug("\$_FILES = array(<br />\n".
				  str_repeat("&nbsp;", 8) ."'name' => {$file['name']},<br />\n".
				  str_repeat("&nbsp;", 8) ."'type' => {$file['type']},<br />\n".
				  str_repeat("&nbsp;", 8) ."'size' => {$file['size']},<br />\n".
				  str_repeat("&nbsp;", 8) ."'tmp_name' => {$file['tmp_name']},<br />\n".
				  str_repeat("&nbsp;", 8) ."'error' => {$file['error']} );"
			);
			
			$this->log_error_and_return("Warning:  $error");
		}
	}


    /**
	 * Attempts to save the uploaded file to the destination
	 *
	 * @return boolean True on success, false on failure
	 */
    function save() {
      	if ( $this->_move_uploaded_file($this->info['tmp_name'], $this->destination . $this->filename) ) {

			$this->info['directory'] = $this->destination;

			$this->chmod_file($this->destination . $this->filename, $this->permissions);
			$this->Session->setFlash("Success: File upload saved successfully.");
			return true;
		  
      	}else{
		
			$this->log_error_and_return("Error: File upload not saved.");
			return false;
        }
    }


	/**
	 * Set file permissions in decimal equivalent
	 *
	 * @param string $octal_params - The file permissions in octal, ex: '0777', '0775', etc...
	 */
    function set_permissions($octal_params){
		$this->permissions = octdec($octal_params); 
	}


	/**
	 * Identify the field name of the file input in the form
	 *
	 * @param string $fieldname 
	 */
    function set_fieldname($fieldname) {
		$this->fieldname = $fieldname;
		
		debug("\$this->fieldname set to ".var_export($this->fieldname, true));
    }


	/**
	 * Sets the name of the file
	 *
	 * @param string $filename - Name of the file once on the server
	 */
    function set_filename($filename) {
		if (!strstr($filename, '.')) {
			$this->filename = $filename .".". $this->info['extension'];
		} else {
			$this->filename = $filename;
		}
		
		debug("\$this->filename set to ".(string)$this->filename);
    }


	/**
	 * Identifies which tmp_file to move to the destination directory
	 *
	 * @param string $filename - Name of the tmp_file in the tmp_directory
	 */
    function set_tmp_filename($filename) {
		$this->tmp_filename = $filename;
    }


	/**
	 * Used to populate a set of predefined file extentions - if the user uploads a file not included they will get an error
	 *
	 * @param array or string $extensions - List of allowable file extensions
	 */
    function set_extensions($extensions) {
		if (tep_not_null($extensions)) {
			if (is_array($extensions)) {
				$this->extensions = $extensions;
			} else {
				$this->extensions = array($extensions);
			}
		} else {
			$this->extensions = array();
		}
    }	


	/**
	 * If a directory does not exist, this class will attempt to create it.
	 *
	 * @param boolean $switch - Set diectories to be created, true/false
	 */
	function set_create_directories( $switch ){
		if( (boolean)$switch ){
			$this->create_directories = true;
		}else{
			$this->create_directories = false;
		}
	}




	/* Helper functions */
	

	/**
	 * Sets the destination directory
	 *
	 * @param string $dir - Destination Directory
	 */
    function set_destination($dir) {
		if( strpos($dir, $this->__DOCUMENT_ROOT)===false ){
			if (substr($dir, -1, 1) != $this->__DS) $dir .= $this->__DS;	//Add a trailing directory separator 
			if (substr($dir,  0, 1) == $this->__DS) $dir = substr($dir, 1);	//Remove the leading directory separator 
		}
		$this->destination = $dir;
		
		debug("\$this->destination set to ".(string)$this->destination);
    }


	/**
	 * Checks the destination directory on the server: Does is exist?, Is it writable?, etc...
	 *
	 * @param string $destination - Destination Directory
	 * @return boolean True on success, false on failure	 
	 */
    function check_destination( $destination ) {

		//Force analysis from the drive root...
		if( strpos($destination, $this->__DOCUMENT_ROOT)===false && strpos('/'.$destination, $this->__DOCUMENT_ROOT)===false){
			$destination = $this->__DOCUMENT_ROOT . $destination;
		}
		
		debug("Checking Destination Directory... ( $destination )");
		
		clearstatcache();
		if (!is_writable( $destination )) {
		
			if (is_dir( $destination )) {
				$this->Session->setFlash( sprintf("Error: Destination not writeable ( %s ).", $destination));
			} else {
				debug( sprintf("Error: Destination does not exist ( %s ).", $destination));
				
				if( $this->create_directories ){
					return $this->make_directory( $destination );
				}
			}
			
			return false;
		} else {
			debug("...OK");
			return true;
		}
    }


	/**
	 * Attempts to create a directory on the server
	 *
	 * @param string $dir - Destination Directory
 	 * @return boolean True on success, false on failure
	 */
	function make_directory( $dir ){
	
		if (!is_dir( $dir )) {
		    $msg = "Attempting to create directory ($dir)...";
			
			if( mkdir($dir, $this->permissions) ){
				$this->Session->setFlash("$msg Success!");
				return true;
			}else{
				$this->Session->setFlash("$msg Failed :( ");
				return false;
			}
		}
		return false;	
	}


	/**
	 * Attempts to delete a directory
	 *
	 * @param string $dir - Destination Directory
 	 * @return boolean True on success, false on failure
	 */
	function delete_directory( $dir ){
		
		if( $dir != ''){
			$dir = $this->prepend_root_path( $dir );
			
			if (is_dir( $dir )) {
				$msg = "Attempting to delete directory ($dir)...";
				
				if( rmdir($dir) ){
					debug("$msg Success!");
					return true;
				}else{
					debug("$msg Failed :( ");
					return false;
				}
			}else{
				debug("Cannot Delete Directory: ($dir) is not a recognized directory");
				return false;
			}
		}
		
		debug("Error: directory name argument supplied to delete_directory() was blank ($dir)");
		return false;	
	}


	/**
	 * Attempts to move a file 
	 *
	 * @param string $filename - Target File
	 * @param string $destination - Destination Directory
 	 * @return boolean True on success, false on failure
	 */
	function move_file($filename, $destination){
	
		if(copy($filename, $destination)) {
			debug("<br />File was copied");
			delete_file( $filename );
			return true;
		}else{
			debug("<br />File could not be copied");
			return false;
		}
	}


	/**
	 * Attempts to move an uploaded file, from the upload-tmp-directory to a destination
	 *
	 * @param string $filename - Target File
	 * @param string $destination - Destination Directory
 	 * @return boolean True on success, false on failure
	 */
	function _move_uploaded_file($filename, $destination){
		
		if( $destination != ''){
			$destination = $this->prepend_root_path( $destination );
			
			if( move_uploaded_file($filename, $destination)){
				debug("Uploaded file was moved to ($destination)");
				return true;
			}else{
				debug("Uploaded file could not be moved to ($destination).  Check the path.");
				return false;
			}
		}
		
		debug("Error: destination directory supplied to \$this->_move_uploaded_file() was blank");
		return false;
	}


	/**
	 * Attempts to delete a file
	 *
	 * @param string $file - Target File
 	 * @return boolean True on success, false on failure
	 */
	function delete_file( $file ){
		
		if( $file != ''){
			$file = $this->prepend_root_path( $file );
			
			if( is_file( $file ) ){ 
				if( unlink( $file ) ){
					debug("File: ($file) was deleted");
					return true;
				}else{
					debug("File: ($file) could not be deleted");
					return false;
				}
			}else{
				debug("Cannot Delete File: ($file) is not a recognized file");
				return false;
			}
		}
		
		debug("Error: filename supplied to \$this->delete_file() was blank");
		return false;
	}


	/**
	 * Attempts change a file's permissions
	 *
	 * @param string $filename - Target File
	 * @param string $permissions - File Permissions
 	 * @return boolean True on success, false on failure
	 */
	function chmod_file( $file, $permissions ){
		
		if( $file != ''){
			$file = $this->prepend_root_path( $file );
			
			if( !is_writable( $file ) ){
				if (is_file( $destination )) {
					debug("Cannot chmod(): PHP is not the owner of ($file)");
				} else {
					debug("Cannot chmod(): ($file) is not a recognized file");
				}
				return false;
			}else{
				chmod($file, $permissions);
				debug("File Permissions on ($file) were updated");
				return true;
			}
		}
		
		debug("Error: filename argument supplied to chmod_file() was blank");
		return false;
	}	


	/**
	 * Optionally prepends the document root path to a string
	 *
	 * @param string $path 
	 */
	function prepend_root_path( $path ){
		if( strpos($path, $this->__DOCUMENT_ROOT)===false ){
			$path = $this->__DOCUMENT_ROOT . $path;
		}
		return $path;	
	}
	

	/**
	 * Version safe pathinfo()
	 * PHP's `pathinfo` in versions below 5.2.0 does not extract the file extension
	 *
	 * @param string $path 
	 */
	function _pathinfo($path){
		if(version_compare(phpversion(), "5.2.0", "<")) {
			
			$temp = pathinfo($path);
			
			if($temp['extension']){
				$temp['filename'] = substr($temp['basename'], 0, (strlen($temp['basename']) - strlen($temp['extension']) - 1) );
			}
			
			return $temp;
			
		} else {
			return pathinfo($path);
		}	
	}


	function log_error_and_return($msg) {
		$_error["{$this->config['default_col']}_file_name"] = $msg;
		$this->controller->{$this->controller->modelClass}->validationErrors = array_merge($_error, $this->controller->{$this->controller->modelClass}->validationErrors);
		$this->log($msg, 'upload-component');
		return false;
	}
}
?>