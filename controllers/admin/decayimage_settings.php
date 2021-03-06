<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Decayimage admin settings controller
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   March-Hare Communications Collective <info@march-hare.org>
 * @module	   Decayimage
 * @license    http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */



class Decayimage_Settings_Controller extends Admin_Controller
{

	public function __construct()
	{
		parent::__construct();
		$this->template->this_page = 'Decayimage';

		// If user doesn't have access, redirect to dashboard
		if ( ! admin::permissions($this->user, "manage"))
		{
			url::redirect(url::site().'admin/dashboard');
		}
  }

	/**
	 * Add Edit decayimage 
	 */
	public function index()
  {
    // The default decayimage thumb file name
    $default_decayimage_thumb = 'Question_icon_thumb.png';
    
		$this->template->content = new View('decayimage/settings');
    $this->template->content->title = Kohana::lang('decayimage.decayimage');
    plugin::add_stylesheet('decayimage/css/decayimage');

		// Setup and initialize form field names
		$form = array
		(
			'action' => '',
			'decayimage_id' => '',
      'decayimage_image' => '',
      'decayimage_file' => '',
      'decayimage_thumb' => '',
			'category_id' => '',
		);

		// Copy the form as errors, so the errors will be stored with keys corresponding to the form field names
		$errors = $form;
		$form_error = FALSE;
		$form_saved = FALSE;
		$form_action = "";
		$parents_array = array();

		// Check, has the form been submitted, if so, setup validation
		if ($_POST)
    {
      $post = new Validation($_POST);
      $post->pre_filter('trim');

      $post->add_callbacks('category_id', array($this, '_is_valid_category'));
      // if we have an action == 'a' but and a decayimage_id then what we really 
      // mean is to perform and edit
      if (($post->action == 'a') && isset($post->category_id)) {
        $post->add_rules('category_id', 'required', 'numeric');
        if (
          $post->validate()  &&
          ($decayimage = ORM::factory('decayimage')->
          where('category_id', $post->category_id)->find())  &&
          $decayimage->loaded
        ) {
          $post->decayimage_id = $decayimage->id;
          $post->action = 'e';
        }
      }

			// Check for action
      if ($post->action == 'a')
      {
        // Create a new decayimage row
        $decayimage = new Decayimage_Model($post->decayimage_id);

        // Handle the case where we recieve new files
        if (
          upload::valid($_FILES['decayimage_file']) && 

          strlen($_FILES['decayimage_file']['name']) &&

          ($_FILES = Validation::factory($_FILES)
            ->add_rules('decayimage_file', 'upload::valid', 
            'upload::type[gif,jpg,png]', 'upload::size[50K]')) &&

          $_FILES->validate()  &&

          $post->validate() 
        ) {

          // Upload the file and create a thumb
          $modified_files = $this->_handle_new_decayimage_fileupload(0);
          if (!$modified_files) {
            $form_saved = false;
            $form_error = TRUE;
            $post->add_error('decayimage', Kohana::lang('decayimage.cant_upload_file'));
          } else {
            $decayimage->decayimage_image = $modified_files[0];
            $decayimage->decayimage_thumb = $modified_files[1];

            // Update the relevant decayimage from the db
            $decayimage->category_id = $post->category_id;
            $decayimage->save();

            $form_saved = TRUE;
            $form_action = Kohana::lang('decayimage.added');
          }
        } 
        // Handle the case where we recieve only existing thumbnails
        else if (
          $post->add_rules('decayimage_thumb', 'required', 'length[5,255]') &&

          $post->add_callbacks('decayimage_thumb', 
          array($this, '_is_valid_decayimage_thumb')) &&

          $post->validate() 

        ){
            // Upload the file and create a thumb
            $decayimage->decayimage_thumb = $post->decayimage_thumb;

            // Update the relevant decayimage from the db
            $decayimage->category_id = $post->category_id;
            $decayimage->save();

            $form_saved = TRUE;
            $form_action = Kohana::lang('decayimage.added');
        } 
        // If we did not reieve valid upload or valid exisitng thumbs then we error
        else {
          // There was an error in validation
          $form_error = TRUE;
          $form = arr::overwrite($form, $post->as_array());
          $errors = arr::overwrite($errors, $post->errors('decayimage'));
        }
      } 

      // Handle form edits
      elseif ($post->action == 'e') {
        // Setup validation for new $_FILES
        if (upload::valid($_FILES['decayimage_file']) && 
          strlen($_FILES['decayimage_file']['name'])) {
          $_FILES = Validation::factory($_FILES)
            ->add_rules('decayimage_file', 'upload::valid', 
            'upload::type[gif,jpg,png]', 'upload::size[50K]');
        } else {
          $post->add_rules('decayimage_thumb', 'required', 'length[5,255]');
          $post->add_callbacks('decayimage_thumb', 
            array($this, '_is_valid_decayimage_thumb'));
        }

        // Validate all input
        $post->add_rules('decayimage_id', 'required', 'numeric');
        $post->add_callbacks('decayimage_id', array($this, '_is_valid_decayimage_id'));

        if ($post->validate()) {
          // Get the relevant decayimage from the db
          $decayimage = new Decayimage_Model($post->decayimage_id);

          // If a file was uploaded we will need to convert it to an apropriate icon size
          if (upload::valid($_FILES['decayimage_file']) && 
            strlen($_FILES['decayimage_file']['name']) &&
            $_FILES->validate())  {

            $modified_files = 
              $this->_handle_new_decayimage_fileupload($post->decayimage_id);
            if (!$modified_files) {
              $form_saved = false;
              $form_error = TRUE;
              $post->add_error('decayimage', Kohana::lang('decayimage.cant_upload_file'));
            } else {
              $decayimage->decayimage_image = $modified_files[0];
              $decayimage->decayimage_thumb = $modified_files[1];
            }
          } else {
            $decayimage->decayimage_thumb = $post->decayimage_thumb;
          }

          // Update the relevant decayimage from the db
          $decayimage->category_id = $post->category_id;
          $decayimage->save();

          $form_saved = TRUE;
          $form_action = Kohana::lang('decayimage.updated');

        } else {
          // There were errors
          $form_error = TRUE;
        }

      }
      elseif ($post->action == 'd') {
        // TODO: https://github.com/March-hare/decayimage/issues/3
        // Make sure its not the Default entry
        $post->add_rules('decayimage_id', 'required', 'numeric');
        if ($post->validate()) {
          $decayimage = ORM::factory('decayimage', $post->decayimage_id);
          if ($decayimage->decayimage_image != 'Question_icon.png') {
            $decayimage->delete();
          }
          else {
            $form_error = TRUE;
            $post->add_error('decayimage', Kohana::lang('decayimage.cant_del_default'));
          }
        }
        else {
          $form_error = TRUE;
        }
      }
      elseif ($post->action == 'r') {
        // TODO: Revert to default decayimage action
        $decayimage = ORM::factory('decayimage')->
          where('category_id', 0)->find();
        $decayimage->decayimage_image = 'Question_icon.png';
        $decayimage->decayimage_thumb = 'Question_icon_thumb.png';
        $decayimage->save();
      }

      if ($form_error) {
        $form = arr::overwrite($form, $post->as_array());
        $errors = arr::overwrite($errors, $post->errors('decayimage'));
      }
		}

		//get array of categories
		$categories = ORM::factory("category")->where("category_visible", "1")->find_all();
    $cat_array[0] = Kohana::lang('decayimage.default_incident_icon');
		foreach($categories as $category)
		{
			$cat_array[$category->id] = $category->category_title;
    }

    //get array of decay images
		$decayimages = ORM::factory("decayimage")->find_all();
		$decayimage_array = array();
		foreach($decayimages as $decayimage)
		{
			$decayimage_array[$decayimage->decayimage_thumb] = $decayimage->decayimage_thumb;
    }

		$this->template->content->form_action = $form_action;
		$this->template->content->errors = $errors;
		$this->template->content->cat_array = $cat_array;
		$this->template->content->decayimage_array = $decayimage_array;
    $this->template->content->url_site = url::site();
    $this->template->content->default_decayimage_thumb = $default_decayimage_thumb;
    $this->template->content->decayimages = $decayimages;
    $this->template->content->form_error = $form_error;
    $this->template->content->form_saved = $form_saved;
		$this->template->js = new View('decayimage/settings_js');
    $this->template->js->default_decayimage_thumb = $default_decayimage_thumb;
  }//end index function

  public function _is_valid_decayimage_thumb(Validation $array, $field) {
    // The Kohana documentation says that all input is auto sanitized
    $decayimage_exists = ORM::factory('decayimage')
      ->where('decayimage_thumb', $array[$field])
      ->count_all();

    if (!$decayimage_exists) {
      $array->add_error($field, 'invalid_decayimage_thumb');
    }
  }
	
  public function _is_valid_decayimage_id(Validation $array, $field) {
    $decayimage_exists = (bool) ORM::factory('decayimage')
      ->where('id', $array[$field])
      ->count_all();

    if (!$decayimage_exists) {
      $array->add_error($field, 'invalid_decayimage_id');
    }
  }
	
  public function _is_valid_category(Validation $array, $field) {
    // If category_id == 0 then it is the default decayimage icon
    if (
      !Category_Model::is_valid_category($array[$field]) &&
      $array[$field] != 0
    ) {
      $array->add_error($field, 'category_id');
    }
  }

  // There is no asumption that the decayimage has already been saved
  private function _handle_new_decayimage_fileupload($id) {
    $filename = upload::save('decayimage_file');
    if ($filename) {
      $new_filename = "decayimage_".$id."_".time();
      
      // Name the files for the DB
      $cat_img_file = $new_filename.".png";
      $cat_img_thumb_file = $new_filename."_16x16.png";

      // Resize Image to 32px if greater
      Image::factory($filename)->resize(32,32,Image::HEIGHT)
        ->save(Kohana::config('upload.directory', TRUE) . $cat_img_file);
      // Create a 16x16 version too
      Image::factory($filename)->resize(16,16,Image::HEIGHT)
        ->save(Kohana::config('upload.directory', TRUE) . $cat_img_thumb_file);
    } else {
      Kohana::log('error', 'we were not able to save the file upload');
      return false;
    }

    // Okay, now we have these three different files on the server, now check to see
    //   if we should be dropping them on the CDN
    
    if(Kohana::config("cdn.cdn_store_dynamic_content"))
    {
      $cat_img_file = cdn::upload($cat_img_file);
      $cat_img_thumb_file = cdn::upload($cat_img_thumb_file);
      
      // We no longer need the files we created on the server. Remove them.
      $local_directory = rtrim(Kohana::config('upload.directory', TRUE), '/').'/';
      unlink($local_directory.$new_filename.".png");
      unlink($local_directory.$new_filename."_16x16.png");
    }

    // Remove the temporary file
    if (file_exists($filename)) {
      unlink($filename);
    }

    // Delete Old Image, unless its the default image
    $decayimage = ORM::factory('decayimage')->where('id', $id);
    if ($decayimage && ($id != 0)) {
      $category_old_image = $decayimage->decayimage_image;
      if ( ! empty($category_old_image))
      {
        if(file_exists(Kohana::config('upload.directory', TRUE).$category_old_image))
        {
          unlink(Kohana::config('upload.directory', TRUE).$category_old_image);
        }elseif(Kohana::config("cdn.cdn_store_dynamic_content") AND valid::url($category_old_image)){
          cdn::delete($category_old_image);
        }
      }
    }

    return array($cat_img_file, $cat_img_thumb_file);
  }
	
}

