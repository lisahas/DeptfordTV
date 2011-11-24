<?php


class settings
{	
  
    
	//set things like the  the path to tmp and path to  media files here and incluede as a var in all //classes
	
	//the tmp directory where all the archive files will go
	public $tmp = "tmp/";
	//the path to the media files on your local machine
	public $mediapath="xxx";
	public $type = ''; //use this to store this in case its kdenlive
}

//gets the xml file (output from cinelerra from a  given location (url)
class xmlGetter
{
private $settings;  
private $timestamp;
private $path;

    //set to true if we don't want to download a file
	function __construct($remote = false, $settings)   {
		if(!$remote)  {
	    $this->settings = $settings;
		$this->timestamp = time();
		//set the unique dir
		$this->path = $this->settings->tmp . $this->timestamp;
		mkdir($this->path, 0755);
		}
		else $this->timestamp = time();
	}


	public function get_remote_xml($xml_remote_path)    {
		$file = $this->path ."/xml_version.xml" ;
		$curl = curl_init($xml_remote_path);
		$fhandle = fopen($file, "w");
		curl_setopt($curl, CURLOPT_FILE, $fhandle);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml"));
        curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Accept: application/xml"));
		curl_exec($curl);
		if ( curl_error ($curl) ) {
		echo "<br>Error is : ". curl_error ($curl);
		}
		curl_close($curl);
		fclose($fhandle);

	}
	
	
	//use this one if we are just reading a file, and don't need to download it eg. for storyboard
	//returns a big string
	public function read_remote_xml($xml_remote_path)  {
	  
	  $curl = curl_init();
	  curl_setopt($curl, CURLOPT_URL, $xml_remote_path);
	  curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	  curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml"));
	  curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Accept: application/xml"));
	  curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.7.5) Gecko/20041107/Firefox/1.0.');

	    //content is just one big long string in memory
	  return curl_exec($curl);
	  curl_close($curl);
 
	}
	
	public function get_local_xml($xml_local_path)  {
		//copy the file from the local location to the tmp location
		copy($xml_local_path, $this->path ."/xml_version.xml" );
	}
	
	function get_drupal_xml($xml)  {
	  //$xml is already a valid xml string, we just need to open a file and write it to that
	  $extn = 'xml';
	  if(dtv_utils_check_xml_type($xml) == 'kdenlive') {
	    $extn = 'kdenlive';
	  }
	  $file = $this->path ."/xml_version." . $extn;
	  $fhandle = fopen($file, "w");
	  fwrite($fhandle, $xml);
	  fclose ($fhandle);   
	}
	
	function get_xml_path()
	{
		return $this->path."/xml_version.xml" ;
	}
	
	function get_dir_path()
	{
		return $this->path;
	}
	
	function get_timestamp()
	{
		return $this->timestamp;
	}
	
	function get_tmp_path() {
	   return $this->settings->tmp;
	}
	
	

}

//this extracts the asset infor from tthe xml file
//takes the internal path to the xml file and its directory
//later we will tidy it by passing the whole xmlGetter obj
class  xmlParser
{
       //set the path to the directory with the raw asset files. later do this in a config file,
       //no trailing slash
       //in this case, relative to the script
       private $settings;
       // var $assets_path = "xml_versions";
       private $xml;
	
	function __construct($xml = null, $settings = null)  {
		$this->settings = $settings;
		$this->xml = $xml;
	}
	
	//NEXT add to params an optional array of files already downloaded by this user that we can check against and add this
	// as a condition in line 133, ie file should be !in_array($users_files). array default value is array();
	function getAssets($users_files = array())   {
		//open a file for writing the assets list
		$file = $this->xml->get_dir_path() ."/assets.txt" ;
		$fhandle = fopen($file, "w");
		
		//an array to hold the files successfully moved to the tmp/ dir
		$results = array('transferred'=>array(), 'not transferred' => array());
		
		$xmlObj = simplexml_load_file($this->xml->get_xml_path());
		//get all the clips
		foreach ($xmlObj->ASSETS->ASSET as $ASSET)  {
			//at the moment it gets tthe files later it  will  chieck them, retc.
			$asset = $ASSET['SRC'];
			if(in_array($asset, $users_files)) {
			    //return a message
			    $results['not transferred'][]=$asset;
			}
			else {
			    //make a full path to the asset  later we will do this differently
			    $path_to_asset = $this->settings->mediapath ."/" .$asset;
			    //check the asset exists
			    if(@file_exists($path_to_asset))  {
			     if (copy($path_to_asset, $this->xml->get_dir_path()."/".$asset))  {
				     //print "adding $asset to your archive<br>";
				     //write to the assets.txt list: we might not need this later, unless it also contaainns metadata
				     //TODO: refactor metadatagetter to get nice data from drupal instead
				  //   $metaGetter = new metadataGetter();
				  //   $data = $metaGetter->getMetaData($asset);
				     fwrite($fhandle, "$asset \n $data");
				     $results['transferred'][]=$asset;
			     }  //end if copy successful
			     
			  } //end if file exists
			
			}//end if file is already downloaded
		   //TODO handle and error if file not found "There was a problem copying  $asset to the tmp location<br>";
		}   //end loop
		fclose($fhandle);
		return $results;

	}
	
	function getAssetsRegEx($users_files = array(), $xml_text)   {
		//open a file for writing the assets list
		$file = $this->xml->get_dir_path() ."/assets.txt" ;
		$fhandle = fopen($file, "w");
		
		//an array to hold the files successfully moved to the tmp/ dir
		$results = array('transferred'=>array(), 'not transferred' => array());
        $assets = $this->asset_list_full_unique($xml_text);
        
       
        foreach($assets as $asset)  {
          $file_info = $this->getFileInfo($asset);
          //write all the assst metadata each time
          if(!empty($file_info))  {
            $metaGetter = new drupalMetaDataGetter($file_info);
            $data = $metaGetter->getMetaDataFromNode();
            fwrite($fhandle, $data);
           }
          
			if(in_array($asset, $users_files)) {
			    //return a message
			    $results['not transferred'][]=$asset;
			}
			else {
			    //make a full path to the asset using the files db in drupal
			    $path_to_asset = $file_info->filepath;
			    //check the asset exists
			    if(file_exists($path_to_asset))  {
			     if (copy($path_to_asset, $this->xml->get_dir_path()."/".$asset))  {
				      $results['transferred'][]=array($asset => $file_info->fid);
			     }  
			      else {
			       print drupal_set_message ("copy of $asset unsuccesful", 'error');
			     }
			     
			  } 
			  else  {
			      drupal_set_message("file $asset does not exist", 'error');
			    }//end if file exists
			
			}//end if file is already downloaded
		   //TODO handle and error if file not found "There was a problem copying  $asset to the tmp location<br>";
        }
       fclose($fhandle);
	   return $results;
	}
	
	//just return an assets list from an xml text, in the form of node_id
	function get_assets_list($xml_text)  {
	   $asset_nids = array();
	   $assets =  $this->asset_list_full_unique($xml_text);
	   foreach($assets as $asset)  {
	      $file_info = $this->getFileInfo($asset);
	      $metaDataGetter = new drupalMetaDataGetter($file_info);
	      $node = $metaDataGetter->getNodeFromFid();
	      $asset_nids[]=$node->nid;
	   }
	   return $asset_nids;
	}
	
	
	//getting the file object from drupal db so we can use the filepath and fid 
	function getFileInfo($filename)  {
	  $file_info = db_fetch_object(db_query('SELECT * FROM {files} WHERE filename = "%s"', $filename)); 
	  return $file_info;
	}
	
	function asset_list_full_unique($xml_text) {
		//TODO first check for a cinelerra file
	    $initial_chunk = substr($xml_text, 0, 100);
	    $pattern = '#<EDL\sVERSION=\"?(\S*)\"?\s#';
        preg_match($pattern, $initial_chunk, $matches);
         //if its cinelerra
        if(!empty($matches[1])) {
           $assets = array();
           $elements = element_set('ASSET', $xml_text);
           foreach($elements as $element)  {
            //match for the filename is in named match called file
            $pattern = '#(<ASSET SRC=\"?(?P<file>.*)\"?>)#';
            preg_match($pattern, $element,  $file_matches);
            $asset_file = $file_matches['file'];
            //take out anything after any " found
            if($pos = strpos($file_matches['file'], '"')) {
              $asset_file = substr($asset_file, 0, $pos);
            }
            
            //HACK: exclude .mov files as they are likely the rendered out ones
            
            $pattern = '#(.mov)#';
            preg_match($pattern, $element, $matches);
            if(!$matches[1]) {
              if(!in_array($asset_file, $assets)) {
                $assets[]= $asset_file;
              }
            }         
          }
        }
        //assume its kdenlive: TODO check for errors here
        else {
          $xml = simplexml_load_string($xml_text);
          $assets = array();
          $getter = new drupalMetaDataGetter();
          $valid_extensions = $getter->get_types_extensions_array();
          foreach($xml as $key => $simple_xml_object) {
            if(isset($simple_xml_object->property[4])) {
              $resourceobj = (array) $simple_xml_object->property[4];
              $asset = trim($resourceobj[0]);
              $assets[] = $asset;
            }
            else {
              $simple_xml_array = (array) $simple_xml_object;
              if($simple_xml_array['@attributes']['projectfolder']) {
                $path = $simple_xml_array['@attributes']['projectfolder'];
              }
              if(isset($simple_xml_array['kdenlive_producer'])) {
                foreach($simple_xml_array['kdenlive_producer'] as $producer_asset) {
                  $attributes_array = (array) $producer_asset;
                  $resource = $attributes_array['@attributes']['resource'];
                  $asset = trim($resource);
                  $assets[] = $asset;
                }
              }
            }
 
         }

        /*
         * Now we have all the assets, treat them in the ways we want
         * 
         */
        $processed_assets = array();
        foreach($assets as $key => $asset) {
            /*strip the full path if exisits
           $trimmed = trim(str_replace($path, '', $asset), '/');
           */
           //change the way this is done in case path has not been picked up
           $trimmed = array_pop(explode('/', $asset));
           $extension = strtolower(array_pop(explode('.',$trimmed)));
           if(!in_array($trimmed, $processed_assets) && in_array($extension, $valid_extensions)) {
                $processed_assets[] = $trimmed;
             }
          }
         $assets = $processed_assets;
        }  //end if kdenlive
      return $assets;
	}
}

class metadataGetter
{
	private $querybase = "SELECT * FROM watch_video where filepath_web =\"files/rough/";
	
	function getMetaData($file_path)   {
	include_once('commonClasses.php');	
		
		
		$meta = "";
		
		$db = new db();
		$db->db_connect();
		
		$query = $this->querybase . "$file_path". "\"";
		//print $query;
		$result = $db->db_query($query);
		
		while($row = mysql_fetch_array($result))  {
			$meta .= "Author: " . $row['author'] . "\n";
			$meta .= "Project: " . $row['project'] . "\n";
			$meta .= "Year: " . $row['year'] . "\n";
			$meta .= "Postcode: " . $row['postcodeplace'] . "\n";
			$meta .= "Comment: " . $row['comment'] . "\n";
			$meta .= "Description: " . $row['description'] . "\n";
			$meta .= "Online File path: " . $row['filepath_web'] . "\n";
			$meta .=  "\n\n\n";	
		}
		
		return $meta;
	}
   
 }
 
class drupalMetaDataGetter
{
     private $file;
     private $types;
  
     function __construct($file = '')  {
      if(!empty($file)) {
        $this->file = $file;
      }
      $this->types = $this->get_types_extensions();
    }
     
    function getMetaDataFromNode() {
    $node_info = $this->getNodeFromFid();
    $meta = ''; 
    if(!empty($node_info->nid))  {

        $node = node_load($node_info->nid);
        
        //first the info that is common in all the node types
        $meta .= $node->type . ' file:' . "\n";
        $meda .= 'Title: ' . $node->title . "\n";
        $meta .= 'Description: ' . $node->body . "\n";
        $meta .= 'Tags: ' . $this->getDrupalKeywords($node) . "\n";
        $meta .= 'Author(s): ' . $this->getDrupalAuthors($node) .  "\n";
        $meta .= 'Location: ' . $node->locations[0]['latitude'] .  ', ' . $node->locations[0]['longitude'] . "\n";
        
        if($node->type == 'video') {
            
            $meta .= "Filename: " . $node->field_video_file[0]['filename'] . "\n";
            $meta .= "Online File path: " . $node->field_video_file[0]['filepath'] . "\n";
			$meta .= "Creation Date: " . $node->field_video_creation_date[0]['value']  . "\n";
			$meta .= "Originator: " . $node->field_video_original_author[0]['value'] . "\n";
        }
        
        //TODO: we need to improve these when the node type is better specced
        if($node->type == 'audio')  {
              
            $meta .= "Filename: " . $node->field_audio_file[0]['filename'] . "\n";
			$meta .= "Online File path: " . $node->field_audio_file[0]['filepath'] . "\n";
			$meta .= "Originator: " . $node->field_audio_original_author[0]['value'] . "\n";
			$meta .= "Creation Date: " . $node->field_audio_creation_date[0]['value']  . "\n";
        }
        
       if($node->type == 'image')  {
              
            $meta .= "Filename: " . $node->field_image_file[0]['filename'] . "\n";
			$meta .= "Online File path: " . $node->field_image_file[0]['filepath'] . "\n";
			$meta .= "Originator: " . $node->field_image_original_author[0]['value'] . "\n";
			$meta .= "Creation Date: " . $node->field_image_creation_date[0]['value']  . "\n";
        }
        
       $meta .=  "\n\n\n";
        
      }
      
     else {
       $meta = "no info found for file id $fid \n\n\n";
     }
	
     return $meta;
   
    }
 /*
  * 
  * returns a string with all the drupal users who author this node.
  */
  function getDrupalAuthors($node)  {
    
    $authors = array();
    //first get the node uid
    $user = user_load(array('uid' => $node->uid));
    $authors[] = $user->name;
    //now anybody in the additional authors fields
    if(isset($node->field_additional_users)) {
    foreach($node->field_additional_users as $additional_user) {
      if(isset($additional_user['uid'])) {
      $user = user_load(array('uid' => $additional_user['uid']));
      $authors[] = $user->name;
      }
     }
    }
    return implode(', ', $authors);
  }
  
  /*
   * returns a string of tags for this node
   * 
   * 
   */
  
  function getDrupalKeywords($node)  {
    $terms =array();
    if(isset($node->taxonomy))  {
      foreach($node->taxonomy as $term_obj) {
         $terms[] = $term_obj->name;
      }
    }
    return implode(', ', $terms);
  }
  
  /*
   * get the node that contains this file object
   * 
   */ 
  function getNodeFromFid()  {
    $fid = $this->file->fid;
    $extension = trim(array_pop(explode('.', $this->file->filename)));
    $type = $this->get_type_from_extension(strtolower($extension));
    $query = 'SELECT nid FROM {content_type_'.$type.'} where field_'.$type.'_file_fid = %d';
   // dprint_r($query);
    $result = db_query($query, $fid);
    $node_info = db_fetch_object($result);
    return $node_info;
  }
    
  //gets the file extensions for particular node types  
  function get_types_extensions() {   
    $types = array();
    $query = "SELECT field_name, type_name, widget_settings from {content_node_field_instance} 
    where type_name = 'audio' or type_name = 'video' or type_name = 'image'";
    $result = db_query($query);
    while($row = db_fetch_array($result))  {
      $data = unserialize($row['widget_settings']);
      if (isset($data['file_extensions'])) {
         $types[$row['type_name']] = $data['file_extensions'];
      }
   }
   return $types;
  }
  
  function get_type_from_extension($extension) {
    foreach($this->types as $type => $extensions)  {
      $ext_array = explode(' ', $extensions);
      if(in_array(trim($extension), $ext_array)) {
        return $type;
      }
    }  
  }
  
  function get_types_extensions_array() {
    $extensions = array();
    foreach($this->types as $types) {
      $avtypes = preg_split('#\s#',$types);
      foreach($avtypes as $extension) {
        $extensions[] = trim($extension);
      }
    }
    return $extensions;
  }
}  
 
 
 //creates an .tar.gz of whats in the dir
 class archiver
 {
       var $xml;
        //the full relative path to the archive
        var $path;
	//the name for this tar  archive
	var $tarname;
	 
	//contructor takes the path to the directory we want
	function __construct($xml)   {
		$this->xml = $xml;
		$this->path = $xml->get_dir_path();
		$this->tarname = array_pop(explode("/", $this->path))."_version.tgz";	
	}
	
	//can add more options here
	/*
	Using the -C (--directory) option to change directory from the point of view of tar.
	This creates an archive with no folders, when extracted, all the assets, text files etc wil appear  in the current directory.
	eg. sudo tar -C tmp/1215097650 -czvf tmp/test.tgz .
	*/
	function makeArchive()   {

	    $file_to_write = $this->xml->get_tmp_path() . $this->tarname;
		//here we set the change directory -C option parameter
		$c_option = $xml;
		$command = "tar -C ". $this->path ." -czf $file_to_write .";
		$cmd =  escapeshellcmd ($command );
		$result = exec($cmd);
		return $file_to_write;
	}

	 
 
 }
 

function element_set($element_name, $xml, $content_only = false) {
    if ($xml == false) {
        return false;
    }
    $found = preg_match_all('#<'.$element_name.'(?:\s+[^>]+)?>' .
            '(.*?)</'.$element_name.'>#s',
            $xml, $matches, PREG_PATTERN_ORDER);
    if ($found != false) {
        if ($content_only) {
            return $matches[1];  //ignore the enlosing tags
        } else {
            return $matches[0];  //return the full pattern match
        }
    }
    // No match found: return false.
    return false;
}


