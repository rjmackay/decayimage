<?php defined('SYSPATH') or die('No direct script access.');
/**
 * Actionable Hook - Load All Events
 *
 * PHP version 5
 * LICENSE: This source file is subject to LGPL license 
 * that is available through the world-wide-web at the following URI:
 * http://www.gnu.org/copyleft/lesser.html
 * @author	   Ushahidi Team <team@ushahidi.com> 
 * @package	   Ushahidi - http://source.ushahididev.com
 * @copyright  Ushahidi - http://www.ushahidi.com
 * @license	   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License (LGPL) 
 */

class decayimage extends endtime {
	
	/**
	 * Registers the main event add method
	 */
	public function __construct()
  {
    parent::__construct();
  }

  public function add() {
    if (preg_match(':^admin/reports/edit:', url::current())) {
      // We replace this because we want to add our configureables in the same
      // section.
      Event::replace('ushahidi_action.report_form_admin_after_time', 
        array(new endtime, '_report_form'),
        array($this, '_report_form'));

      // Hook into the report_submit_admin (post_POST) event right before saving
      Event::replace('ushahidi_action.report_submit_admin', 
        array(new endtime, '_report_validate'),
        array($this, '_report_validate'));

      // Hook into the report_edit (post_SAVE) event
      Event::replace('ushahidi_action.report_edit', 
        array(new endtime, '_report_form_submit'),
        array($this, '_report_form_submit'));

    } else if (preg_match(':^decayimage:', url::current())) {
      Event::add('ushahidi_filter.header_js', 
        array($this, 'decayimage_ushahidi_filter_header_js'));
    } else if (preg_match(':admin/manage:', url::current())) {
      Event::add('ushahidi_action.category_save', 
        array($this, 'decayimage_ushahidi_filter_category_save'));
    }
	}
	
	public function decayimage_ushahidi_filter_header_js()
	{
    // Append a new showIncidentMap function to the end of the file
    preg_match(':^(.+)(//-->\s*</script>\s*)$:s', Event::$data, $matches);
    $layerName = Kohana::lang('ui_main.reports');
    $site = url::site();

$new_js = <<<ENDJS
  var showIncidentMapOrig = showIncidentMap;
  //showIncidentMapBak = (function() {
  showIncidentMap = (function() {
  //return showIncidentMapOrig();

  // Set the layer name
  var layerName = "{$layerName}";
      
  // Get all current layers with the same name and remove them from the map
  currentLayers = map.getLayersByName(layerName);
  for (var i = 0; i < currentLayers.length; i++)
  {
    map.removeLayer(currentLayers[i]);
  }

  // TODO: I am not really sure if this is needed
  currentLayersIcons = map.getLayersByName(layerName + ' Category Icons');
  for (var i = 0; i < currentLayersIcons.length; i++)
  {
    map.removeLayer(currentLayersIcons[i]);
  }

  // Default styling for the reports
  var reportStyle = OpenLayers.Util.extend({}, 
    OpenLayers.Feature.Vector.style["default"]);

  reportStyle.pointRadius = 8;
  reportStyle.fillColor = "#30E900";
  reportStyle.fillOpacity = "0.8";
  reportStyle.strokeColor = "#197700";
  // Does this make the total point radius = 8+3/2?
  reportStyle.strokeWidth = 3;
  reportStyle.graphicZIndex = 2;

  // Default style for the associated report category icons 
  var iconStyle =  OpenLayers.Util.extend({}, reportStyle);
  iconStyle.graphicOpacity = 1;
  iconStyle.graphicZIndex = 1;
  iconStyle.graphic = true;
  iconStyle.graphicHeight = 25;

  // create simple vector layer where the report icons will be placed
  var vLayer = new OpenLayers.Layer.Vector(layerName, {
    projection: new OpenLayers.Projection("EPSG:4326"),
    style: reportStyle,
    rendererOptions: {zIndexing: true}
  });

  // create a seperate vector layer where the icons associated with the report
  // categories will be placed.
  var vLayerIcons = new OpenLayers.Layer.Vector(layerName + ' Category Icons', {
    projection: new OpenLayers.Projection("EPSG:4326"),
    style: iconStyle,
    rendererOptions: {zIndexing: true}
  });
      
  // URL to be used for fetching the incidents
  fetchURL = "{$site}api/?task=decayimage";

  // TODO: for right now all additional parameters here are disabled  
  /*
  // Generate the url parameter string
  parameterStr = makeUrlParamStr("", urlParameters);
  
  // Add the parameters to the fetch URL
  fetchURL += "?" + parameterStr;
   */

  // Fetch the incidents
  var json = jQuery.getJSON(fetchURL, function(data) {
    $.each(data.payload.incidents, function(key, val) {

      // create a point from the latlon
      var incidentPoint = new OpenLayers.Geometry.Point(
        val.incident.locationlongitude,
        val.incident.locationlatitude
      );
      var proj = new OpenLayers.Projection("EPSG:4326");
      incidentPoint.transform(proj, map.getProjectionObject());

      // If the incident has ended but it is configured to "decay" we should
      // set the incident icon to the decayimage default icon
      var newIncidentStyle =  OpenLayers.Util.extend({}, reportStyle);
      if (val.incident.incidenthasended == 1) {
        newIncidentStyle.externalGraphic = data.payload.decayimage_default_icon;
      }

      // create a feature vector from the point and style
      var feature = new OpenLayers.Feature.Vector(incidentPoint, null, newIncidentStyle);
      feature.attributes = {
        link: "{$site}reports/view/"+val.incident.incidentid,
        id: val.incident.incidentid
      };
      vLayer.addFeatures([feature]);

      var offsetRadius = reportStyle.pointRadius+iconStyle.graphicHeight/2;

      var numIcons = val.categories.length;
      var iconCt = 1;
      // Loop over each icon setting externalGraphic and x,y offsets
      $.each(val.categories, function(index, category) {
        
        var newIconStyle =  OpenLayers.Util.extend({}, iconStyle);
        // TODO: make sure we are using the decayimage category icons if they
        // are set.  I think this should be transparently set by the json 
        // controller anyhow.
        if (val.incident.incidenthasended) {
          newIconStyle.externalGraphic = category.category.icon;
        } else {
          newIconStyle.externalGraphic = category.category.decayimage;
        }

        // TODO: -13 is a magic number here that got this working.
        // I dont totally understant what its related to.
        // pointRadius + strokeWidth + 2FunPixels?
        newIconStyle.graphicXOffset = -13+
          offsetRadius*Math.cos(((2*3.14)/(numIcons))*index);
        newIconStyle.graphicYOffset = -13+
          offsetRadius*Math.sin(((2*3.14)/(numIcons))*index);

        iconPoint = incidentPoint.clone();
        var feature = new OpenLayers.Feature.Vector(
          iconPoint, null, newIconStyle);
        vLayerIcons.addFeatures([feature]);
      });

    });
  });

  // Add the vector layer to the map
  map.addLayer(vLayer);
  map.addLayer(vLayerIcons);

  // Add feature selection events
  addFeatureSelectionEvents(map, vLayer);
});
ENDJS;

    Event::$data = $matches[1] . $new_js . $matches[2];
  }//end method

  // We want to add default decayimage module here
  //public function decayimage_ushahidi_filter_category_save($data) {
  public function decayimage_ushahidi_filter_category_save() {
    $data = Event::$data;

    // Get the last id that was added to the category table
    $db = new Database();
    $result = $db->query('SELECT MAX(id) id FROM category');
    foreach ($result as $row) {}

    // Get the category_id and the image file
    $category = ORM::factory('category')->where('id', $row->id)->find();

    // TODO: the errors below are not used by the calling controller
    $image = Kohana::config('upload.directory', TRUE).$category->category_image;
    if (!file_exists($image)) {
      $data->add_error('category', 'did_not_find_category_image_for_grayscale');
      return false;
    }

    // TODO: the errors below are not used by the calling controller
    $type = $data['category_image']['type'];
    if (!preg_match('/png/i', $type)) {
      $data->add_error('category', 'invalid_image_type');
      return false;
    }

    $gdimg = imagecreatefrompng($image);
    imagealphablending($gdimg, false);
    imagesavealpha($gdimg, true);
    $new_filename = "decayimage_".$category->id."_".time();
    $new_filename_with_path = Kohana::config('upload.directory', TRUE) . $new_filename .'.png';
    if (
      $gdimg && 
      imagefilter($gdimg, IMG_FILTER_GRAYSCALE) &&
      imagepng($gdimg, $new_filename_with_path) &&
      imagedestroy($gdimg)
    ) {
      $cat_img_file = $new_filename.".png";
      $cat_img_thumb_file = $new_filename."_16x16.png";

      // Also create a thumbnail of the decayimage
      Image::factory($new_filename_with_path)->resize(16,16,Image::HEIGHT)
        ->save(Kohana::config('upload.directory', TRUE) . $cat_img_thumb_file);

      // Delete pre-existing ids of same name
       $cat_id=$category->id;
       ORM::factory('decayimage')->where(array('category_id' => $cat_id))->delete_all();
     
      // Create the decayimage row
      $decayimage = new Decayimage_Model();
      $decayimage->category_id = $category->id;
      $decayimage->decayimage_image = $cat_img_file;
      $decayimage->decayimage_thumb = $cat_img_thumb_file;
      $decayimage->save();

      Kohana::log('debug', 'sector::decayimage_ushahidi_filter_category_save '. 
        'created a decayimage for the added category icon.');
    } else {
      Kohana::log('error', 'sector::decayimage_ushahidi_filter_category_save '. 
        'failed to create a decayimage for the added category icon.');
    }

  }

  public function _report_form() {
		// Load the View
		$view = View::factory('decayimage/endtime_form');
		// Get the ID of the Incident (Report)
		$id = Event::$data;
		
		//initialize the array
		$form = array
			(
			    'end_incident_date'  => '',
			    'end_incident_hour'      => '',
			    'end_incident_minute'      => '',
			    'end_incident_ampm' => ''
			);
		
		
		if ($id)
		{
			// Do We have an Existing Actionable Item for this Report?
			$endtime_item = ORM::factory('endtime')
				->where('incident_id', $id)
				->find();

			$view->applicable = $endtime_item->applicable;
			$view->remain_on_map = $endtime_item->remain_on_map;
			$endtime_date = $endtime_item->endtime_date;
			
			if($endtime_date == "")
			{
				$incident = ORM::factory('incident')->where('id', $id)->find();
				$i_date_time = $incident->incident_date;
				$form['end_incident_date'] = date('m/d/Y', strtotime($i_date_time));
				$form['end_incident_hour'] = date('h', strtotime($i_date_time));
				$form['end_incident_minute'] = date('i', strtotime($i_date_time));
				$form['end_incident_ampm'] = date('a', strtotime($i_date_time));
			}
			else
			{
				$form['end_incident_date'] = date('m/d/Y', strtotime($endtime_date));
				$form['end_incident_hour'] = date('h', strtotime($endtime_date));
				$form['end_incident_minute'] = date('i', strtotime($endtime_date));
				$form['end_incident_ampm'] = date('a', strtotime($endtime_date));
			}
		}		
		else //initialize to now using submitted values if available
		{
      $view->applicable = isset($_POST['endtime_applicable'])?
        htmlspecialchars($_POST['endtime_applicable'], ENT_QUOTES, 'UTF-8') : 0;

      $view->remain_on_map= isset($_POST['remain_on_map'])?
        htmlspecialchars($_POST['remain_on_map'], ENT_QUOTES, 'UTF-8') : 0;

      $form['end_incident_date'] = isset($_POST['end_incident_date']) ?
        htmlspecialchars($_POST['end_incident_date']) : date("m/d/Y",time());

      $form['end_incident_hour'] = isset($_POST['end_incident_hour']) ?
        htmlspecialchars($_POST['end_incident_hour']) : date('h', time());

      $form['end_incident_minute'] = isset($_POST['end_incident_minute']) ?
        htmlspecialchars($_POST['end_incident_minute']) : date('i', time());

      $form['end_incident_ampm'] = isset($_POST['end_incident_ampm']) ?
        htmlspecialchars($_POST['end_incident_ampm']) : date('a', time());
		}
		
		// Time formatting
		$view->minute_array = $this->_minute_array();
		$view->hour_array = $this->_hour_array();
		$view->ampm_array = $this->_ampm_array();
		$view->date_picker_js = $this->_date_picker_js();

		$view->form = $form;
    $view->render(TRUE);
  }

	/**
	 * Validate Form Submission
	 */
	public function _report_validate() {
    parent::_report_validate();
		if(is_object($this->post_data))
		{
			$this->post_data->add_rules('remain_on_map','digit');
		}
	}

	/**
	 * Handle Form Submission and Save Data
	 */
	public function _report_form_submit() {
    parent::_report_form_submit();

		$incident = Event::$data;
		$id = $incident->id;
    if ($this->post_data) {
      // TODO: remain_on_map should be moved to a table independent of the 
      // endtime table.
			$endtime = ORM::factory('endtime')
				->where('incident_id', $id)
        ->find();

      $endtime->remain_on_map = isset($this->post_data['remain_on_map']) ? 
        $this->post_data['remain_on_map'] : "0";

      Kohana::log('info', 'decayimage::_report_form_submit() remain_on_map: '.
        $endtime->remain_on_map);

			$endtime->save();
    }
  }

}//end class

new decayimage;
