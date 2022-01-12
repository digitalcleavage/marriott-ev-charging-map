<?php
error_reporting(E_ERROR | E_PARSE);

$GOOGLE_API_KEY = "YOUR_API_KEY";
$database = "/path/to/marriott.db";
$url = "https://www.marriott.com/corporate-social-responsibility/electric-vehicle-hotels.mi";


$db = new SQLite3($database);

$html = @file_get_contents($url);
if (!$html) {
  die('Cannot fetch EV locations!');
}



$doc = new DOMDocument();
$doc->loadHTML($html);
$root = $doc->documentElement; 
$xpath = new DOMXpath($doc);


// eastern
$query = "/html/body/div[1]/div/div[1]/section[3]/div[1]/div[1]/section/div/div/p";
$elements = $xpath->query($query);
print "Processing Eastern...\n";
process($elements);

// western
$query = "/html/body/div[1]/div/div[1]/section[3]/div[2]/div[1]/section/div/div/p";
$elements = $xpath->query($query);
print "Processing Western...\n";
process($elements);

// central
$query = "/html/body/div[1]/div/div[1]/section[3]/div[1]/div[2]/section/div/div/p";
$elements = $xpath->query($query);
print "Processing Central...\n";
process($elements);

function process($elements) {
  if (!is_null($elements)) {
    foreach ($elements as $element) {
      $pieces = explode("\n\n", $element->textContent);
      foreach ($pieces as $piece) {
        $parts = explode("\n", $piece);
        if (sizeof($parts)==2) {
          $name = cleanup(trim($parts[0]));
          $address = trim($parts[1]);
          $location = fetch($address);

          if (!$location) {
            // if it does NOT exist, we can store it
            $coords = geocode($address);
            $lat = null;
            $lon = null;
            if ($coords) {
              $lat = $coords[0];
              $lon = $coords[1];
            } else {
              print "WARNING: unable to fetch location information for:\n";
              print "\tName: $name\n";
              print "\tAddress: $address\n";
            }
            store($name, $address, $lat, $lon);
            print "Stored: name='$name', address: '$address', latitude: $lat, longitude: $lon\n";
          }
        }
      }
    }
  }
}

function cleanup($name) {
  if (str_starts_with($name, "[") && str_contains($name, "]")) {
    return substr($name, strpos($name,"]") + 1);
  }
  return $name;
}

// sqlite3 helper
// create table location ( name VARCHAR(200), address VARCHAR(200), lat REAL, lon REAL, primary key(name));

function fetch($address) {
    global $db;

    $statement = $db->prepare('SELECT * FROM location WHERE address = :address;');
    $statement->bindValue(':address', $address);
    $result = $statement->execute();
    if (!$result) {
      return false;
    }
    // otherwise return the array
    return $result->fetchArray();
}

function store($name, $address, $lat, $lon) {
    global $db;

    $statement = $db->prepare('INSERT INTO location (name, address, lat, lon) VALUES (:name, :address, :lat, :lon)');
    $statement->bindValue(':name', $name);
    $statement->bindValue(':address', $address);
    $statement->bindValue(':lat', $lat);
    $statement->bindValue(':lon', $lon);

    $result = $statement->execute();
    if (!$result) {
      return false;
    }
    // otherwise return the array
    return $result->fetchArray();
}

// function to geocode address, it will return false if unable to geocode address
function geocode($address) {
    global $GOOGLE_API_KEY;
  
    // url encode the address
    $address = urlencode($address);
      
    // google map geocode api url
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$GOOGLE_API_KEY}";
  
    // get the json response
    $resp_json = file_get_contents($url);
      
    // decode the json
    $resp = json_decode($resp_json, true);
  
    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
  
        // get the important data
        $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
        $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";
        $formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";
          
        // verify if data is complete
        if ($lati && $longi && $formatted_address){
          
            // put the data in the array
            $data_arr = array();            
              
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $formatted_address
                );
              
            return $data_arr;
              
        } else {
            return false;
        }
          
    }
  
    else {
        echo "<strong>ERROR: {$resp['status']}</strong>";
        return false;
    }
}

?>
