CakePHP File Upload Component

Installation
Place the component in your app/controllers/components/ directory.

Purpose
Component that handles file-uploads.

Useage
Place upload.php in your CakePHP components directory and follow the example below. 

Controller
class ExampleController extends AppController {
	var $name = 'Example';
	var $uses = array('Example');
	var $helpers = array('Html', 'Form');	
	var $components = array('Upload');
	
	function add() {
		$this->Upload->set_destination('/_my/_destination/_directory/_from/_document/_root/');
		$this->Upload->extensions = array('jpg', 'jpeg', 'gif', 'png', 'bmp');
			
		if (!empty($this->data)) {
			$this->Example->set($this->data);
				
			if( $this->Upload->file_is_uploaded('Example.file') ){	// In view: <?php echo $form->file('Example.file'); ?>
				if( $this->Upload->process('Example.file') ){
					
					// Upload Success. Uploaded file details are accessible here:
					$this->Upload->info['filename'];  //Name of the file - after upload
					$this->Upload->info['origname'];  //Original name of the file - before upload
					$this->Upload->info['extension']; //File extension/type
					$this->Upload->info['directory']; //Location of file on the server
					
					$this->Photo->validates();
				}else{
					//Error occurred: The upload component will output an error message using `$this->Session->setFlash`
				}
			}else{
				$this->Session->setFlash('File not uploaded.  Double check that your form has the `enctype="multipart/form-data"` attribute.');
			}			
		} 
	}

}

Follow the example above with slight modifications to upload files.