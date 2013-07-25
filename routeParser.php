<?php
/**
 * Simple, testable class for parsing recorded route
 * for recruitment process of Hailo	
 * Assumptions:
 * - don't care about memory, prepared for files < 100k points
 * - added html view to visualize effect - viewable thru browser
 * 
 * Written in VIM without formatting rules so may contain 
 * some visual mistakes
 * 
 * @author Tad Magiera <tadeusz.magiera@gmail.com> 
 */

/**
 *  Controller logic
 */
$trip = new routeParser();
$trip->loadRouteFromCSV("points.csv");
$trip->cleanData();

// cli and browser viewable
if (php_sapi_name() == 'cli') {
	echo $trip->getAsCSV();
} else {
	echo $trip->renderMap();
}

/**
 * Main logic
 */
class routeParser {

	/**
	 * Recorder trip 
  	 * array of tripPoint object
 	 */
	private $tripPoints;

	/**
	 * Speed limit in km/h that taxi may report
	 * The most import variable for this excercise
	 */
	private $speedLimit = 80; //km per hour

	// some setters and getters - no use to describe them
	// without help from good IDE
	public function setTripPoints($tripPoints) {
		$this->tripPoints = $tripPoints;
	}
	public function getTripPoints() {
		return $tripPoints;
	}
	public function setSpeedLimit($speedLimit) {
		$this->speedLimit = $speedLimit;
	}
	public function getSpeedLimit() {
		return $this->speedLimit;
	}

	/**
	 * Loading route from CSV file
	 *
	 * @return mixed 
	 *		array of tripPoints on success
	 *		false on failure
	 */ 
	public function loadRouteFromCSV($filename) {
		try {
			$points = file($filename);
		} catch (Exception $e) {
			return false;
		}

		foreach($points as $point) {
			list($lat, $lang, $time) = explode(",", trim($point));
			$this->tripPoints[] = new tripPoint($lat, $lang, $time);	
		}

		return $this->tripPoints;
	}

	/**
	 * Cleaning route from bogus points, based on 
	 * elimintaing points unreachable in reported 
	 * time frame 
	 *
	 * @return array of tripPoint
	 */
	public function cleanData() {
		$lastPoint = null;
		foreach ($this->tripPoints as &$point) {
			if (!isset($lastPoint)) {
				$lastPoint = $point;
				continue;
			}
			$distance = $this->calculateDistance($lastPoint, $point);
			$speed = $this->calculateSpeed($distance, $point->getTime() - $lastPoint->getTime());
			if ($speed > $this->speedLimit) {
				$point->setValid(false);
				continue;
			}
			$lastPoint = $point;
		}

		return $this->tripPoints;
	}

	/**
	 * Calculate distance between given points in km
	 *
	 * @param tripPoint $pointFrom
	 * @param tripPoint $pointTo
	 * @return int distance
	 */
	private function calculateDistance(tripPoint $pointFrom, tripPoint $pointTo) {
		$dLat = deg2rad($pointTo->getLat()-$pointFrom->getLat());
		$dLang = deg2rad($pointTo->getLang()-$pointFrom->getLang());
		$rLatFrom = deg2rad($pointFrom->getLat());
		$rLatTo = deg2rad($pointTo->getLat());
		$a = pow(sin($dLat/2), 2) + cos($rLatFrom) * cos($rLatTo) * pow(sin($dLang/2), 2);
		$b = 2 * atan2(sqrt($a), sqrt(1-$a));
		$distance = 6367 * $b;
		
		return $distance;
	}

	/**
	 * Calculate speed of vehicle
	 * 
	 * @param int $distance in km
	 * @param int $time in seconds
	 * @return float avg speed in km/h
	 */
	private function calculateSpeed($distance, $time) {
		return $distance/($time/3600);
	}

	/**
	 * Render CSV file from given data
	 * Part of presentation logic
	 */
	public function getAsCSV() {
		foreach ($this->tripPoints as $point) {
			if (!$point->getValid()) {
				continue;
			}
			echo $point->getLang().",".$point->getLat().",".$point->getTime()."\n\r";
		}
	} 

	/**
	 * Render HTML file with Gogole Maps with points of route
	 */
	public function renderMap() {

$html = <<<EOT
		<!DOCTYPE html>
		<html>
		  <head>
		    <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
		    <meta charset="utf-8">
		    <title>Simple Polylines</title>
		    <link href="https://developers.google.com/maps/documentation/javascript/examples/default.css" rel="stylesheet">
		    <script src="https://maps.googleapis.com/maps/api/js?v=3.exp&sensor=false"></script>
		    <script>
		function initialize() {
		  var myLatLng = new google.maps.LatLng(51.512093692083,-0.14079097399996);
		  var mapOptions = {
		    zoom: 13,
		    center: myLatLng,
		    mapTypeId: google.maps.MapTypeId.TERRAIN
		  };

		  var map = new google.maps.Map(document.getElementById('map-canvas'), mapOptions);

		  var flightPlanCoordinates = [
EOT;

		foreach($this->tripPoints as $point) {
			if (!$point->getValid()) {
				continue;
			}
			$html .= "new google.maps.LatLng(".$point->getLat().", ".$point->getLang()."),\n\r";
		}

$html .= <<<EOT
		  ];
		  var flightPath = new google.maps.Polyline({
		    path: flightPlanCoordinates,
		    strokeColor: '#FF0000',
		    strokeOpacity: 1.0,
		    strokeWeight: 2
		  });

		  flightPath.setMap(map);
		}

		google.maps.event.addDomListener(window, 'load', initialize);

		    </script>
		  </head>
		  <body>
		    <div id="map-canvas"></div>
		  </body>
		</html>
EOT;

		return $html;
	}
}

/**
 * Simple entity for storage of trip points
 */
class tripPoint {
	private $lang;
	private $lat;
	private $time;
	private $valid = true;
	public function __construct($lat, $lang, $time) {
		$this->lat = $lat;
		$this->lang = $lang;
		$this->time = $time;
	}
	public function setLang($lang) {
		$this->lang = $lang;
	}
	public function getLang() {
		return $this->lang;
	}
	public function setLat($lat) {
		$this->lat = $lat;
	}
	public function getLat() {
		return $this->lat;
	}
	public function setTime($time) {
		$this->time = $time;
	}
	public function getTime() {
		return $this->time;
	}
	public function setValid($valid) {
		$this->valid = $valid;
	}
	public function getValid() {
		return $this->valid;
	}	
}
?>
