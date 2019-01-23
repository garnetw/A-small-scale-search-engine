<?php

include 'SpellCorrector.php';

// make sure browsers see this page as utf-8 encoded HTML
header('Content-Type: text/html; charset=utf-8');

$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$searchType = isset($_REQUEST['algo']) ? $_REQUEST['algo'] : false;
$results = false;
$start = 0;
$wrongVer = false; // This flag is used to check whether users have chosen to show results of misspelled query.

if ($query && $searchType) // Only when user enter the query and select an algorihtm will the results be retrieved.
{                          // Otherwise nothing will happen.
  $correctedQuery = ""; // Corrected query after calling correction function 
  $preQuery = "";       // Record the query entered by users.
  $correctFlag = false;   // Whether the query has been corrected or not, used to show hints on search results page.

  // The Apache Solr Client library should be on the include path
  // which is usually most easily accomplished by placing in the
  // same directory as this script ( . or current directory is a default
  // php include path entry in the php.ini)
  require_once('Apache/Solr/Service.php');

  // create a new solr service instance - host, port, and webapp
  // path (all defaults in this example)
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');

  /* If user choose to show misspelled results, set the flag to true. */
  if(isset($_GET['again'])){
    $wrongVer = true;
    
  }else{
    $wrongVer = false;
  }
    // Correct Query now using Norvig's function 
    $query = trim($query);
    $query = strtolower($query);
    $queryArr = explode(" ",$query);
    foreach ($queryArr as $token) {
       $correctedQuery = $correctedQuery . "" . SpellCorrector::correct($token) . " " ;
    }
  
    // Store previous query and set coorect flag to true, so that it will display hint on result page.
  if(trim($correctedQuery) != trim($query)){
      $correctFlag = true;
      $preQuery = $query;
  }
  // if magic quotes is enabled then stripslashes will be needed
  if (get_magic_quotes_gpc() == 1)
  {
      $correctedQuery = stripslashes($correctedQuery);
  }

  // in production code you'll always want to use a try /catch for any
  // possible exceptions emitted  by searching (i.e. connection
  // problems or a query parsing error)
  try
  {
    $sortVal;
    if($searchType == "default"){
        $sortVal = NULL;
    }else{
        $sortVal = 'pageRankFile desc';
    }
    $additionalParameters = array(
      'fl' => 'title og_url id description',
      'facet' => 'true',
      'wt' => 'json',
      'sort' => $sortVal,
      'hl' => 'on',
      'hl.fl' => '*',
      'hl.highlightMultiTerm' => 'true',
      'hl.fragsize' => '160'

    );
    if($wrongVer == true){
        $results = $solr->search($query, 0, $limit, $additionalParameters); 
    }else{
      $results = $solr->search($correctedQuery, 0, $limit, $additionalParameters); 
    }
    
    
  }
  catch (Exception $e)
  {
    // in production you'd probably log or email this error to an admin
    // and then show a special message to the user but for this example
    // we're going to show the full exception
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }

}

?>


<html>
  <head>
    <title>Searh enginee of Boston Globe</title>
    <!-- Auto Complete module in jQuery-->
  <link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="/resources/demos/style.css">

  <script src="https://code.jquery.com/jquery-1.12.4.js"></script>
  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
  <script>
    $(function(){
      var suggList = new Array();
      var fixedWord = "";
      $("#q").autocomplete({
      // Send request to Solr to obtain suggestions.
        source: function(request, response){
          var term = $("#q").val().toLowerCase();
          if(term.trim() == null || term.trim() == ""){
            suggList = [];
            fixedWord = "";
          }
          else if(term.charAt(term.length - 1) == " "){
              // A term is finished, keep the prefix words. 
              if($("#q").val().toLowerCase().replace(/\n/g,'') == ""){
                fixedWord = "";
                suggList = [];
              }else{
                fixedWord = suggList[0];
                for(var j = 0; j < suggList.length; j++){
                  suggList[j] = fixedWord;
                } 
              }
          }
          /* Split the new words and send it to Solr for suggestions.*/
          else if(term.includes(" ") && term.charAt(term.length - 1) != " "){
              var startIdx = term.lastIndexOf(" ") + 1;
              term = term.substring(startIdx);
          }
          else{}
          $.ajax({
            'url': 'http://localhost:8983/solr/myexample/suggest',
            'type': "GET",
            'data': {
              'q': term,
              'wt': 'json'
            },
            'dataType': 'jsonp',
            'jsonp': 'json.wrf',
            'success': function(data) { 
                var suggestions = data.suggest.suggest[term].suggestions;
                var i = 0;
                $.each(suggestions, function(idx, obj) {
                    suggList[i] = fixedWord + " " +  obj.term;
                    i++;
                });
               response(suggList);
            },
            error: function(ex) {
               alert("Error occurs during autocomplete. Please retry later.");
            }
            
        });
        },
        minLength: 1         
      });         
    });

</script>
  </head>
  
  <style>
    .error{color: #FF0000;}
  </style>
  <body>
    <form  accept-charset="utf-8" method="get">
      <div class="ui-widget">
        <label for="q">Search:</label>
        <input id="q" name="q" type="text" value="<?php echo htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>"/>
        
      </div>  
      <br>Choose: 
      <input type="radio" name="algo" value="default" <?php if($_GET['algo'] == "default") echo "checked=checked;"?>/> 
         Lucene
      <input type="radio" name="algo" value="pageRank" <?php if($_GET['algo'] == "pageRank") echo "checked=checked;"?>/> PageRank
      &nbsp;&nbsp;
      <input type="submit" value="Submit"/>
    
    </form>

<?php

// display results
if ($results)
{
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
?>
    <div>
        <?php 
            if($correctFlag == true && $wrongVer == false){
              echo "Showing results for query: <span style='color:red'>" . $correctedQuery . "</span><br>";
              $comm = "";
              // Correct Query now
              $tmpArr = explode(" ",$preQuery);
              foreach ($tmpArr as $tokens) {
                  $comm = $comm . "+" . $tokens;
              }
              $comm = substr($comm, 1);
              $again = "f";
              echo "Still wants results for query: <a href='http://localhost/HW4_BG?q=" 
                      . $comm . "&algo=" . $searchType ."&again=" . $again ."'>" . $preQuery . "</a><br>";
            }
            else if($wrongVer == true){
              $comm = "";
              // Correct Query now
              $tmpArr = explode(" ",$correctedQuery);
              foreach ($tmpArr as $tokens) {
                  $comm = $comm . "+" . $tokens;
              }
              $comm = substr($comm, 1);
              echo "Do you mean: <a href='http://localhost/HW4_BG?q=" 
                    . $comm . "&algo=" . $searchType ."'>" . $correctedQuery . "</a><br>";
            }

        ?>
    </div>
    <div>Results <?php echo $start; ?> - <?php echo $end;?> of <?php echo $total; ?>:</div>
    <ol>
<?php
  /* Get urls from map.csv */
  $mapFile = fopen("/Users/waterdore/solr-7.1.0/Boston Global Map.csv","r");
  $list = array();
  while($data = fgetcsv($mapFile)){
    $list[$data[0]] = $data[1];
  }
  fclose($mapFile);

  $snippets = $results->highlighting;

  // iterate result documents
  foreach ($results->response->docs as $doc)
  {
    $title = ($doc->getField('title'))['value'];
    $url = ($doc->getField('og_url'))['value'];
    $id = ($doc->getField('id'))['value'];
    $desc = ($doc->getField('description'))['value'];

    if($url == NULL){
      $index = strrpos($id, "/");
      $newId = substr($id, $index + 1);
      if($list[$newId] != NULL){
        $url = $list[$newId];
      }
    }
    if($desc == NULL){
      $desc = "N/A";
    }
    
    $actResult = $snippets->$id->description;
    $str = "";
    if($actResult == NULL){
        $str = 'N/A';
    }else{
      $str = implode(",", $actResult);
    }

?>
      <li>
        <table style="border: 1px solid black; text-align: left" width="100%">
          <tr>
            <th width="10%" valign="top"><?php echo htmlspecialchars("Title", ENT_NOQUOTES, 'utf-8'); ?></th>
            <td width="90%"><a href="<?php echo htmlspecialchars($url, ENT_NOQUOTES, 'utf-8'); ?>" target="_blank">
                  <?php echo htmlspecialchars($title, ENT_NOQUOTES, 'utf-8'); ?></a></td>
          </tr>
          <tr>
            <th width="10%" valign="top"><?php echo htmlspecialchars("URL", ENT_NOQUOTES, 'utf-8'); ?></th>
            <td width="90%"><a href="<?php echo htmlspecialchars($url, ENT_NOQUOTES, 'utf-8'); ?>" target="_blank">
                  <?php echo htmlspecialchars($url, ENT_NOQUOTES, 'utf-8'); ?></a></td>
          </tr>
          <tr>
            <th width="10%" valign="top"><?php echo htmlspecialchars("ID", ENT_NOQUOTES, 'utf-8'); ?></th>
            <td width="90%"><?php echo htmlspecialchars($id, ENT_NOQUOTES, 'utf-8'); ?></td>
          </tr>
          <tr>
            <th width="10%" valign="top"><?php echo htmlspecialchars("Description", ENT_NOQUOTES, 'utf-8'); ?></th>
            <td width="90%"><?php echo htmlspecialchars($desc, ENT_NOQUOTES, 'utf-8'); ?></td>
          </tr>
           <tr>
            <th width="10%" valign="top"><?php echo htmlspecialchars("Snippet", ENT_NOQUOTES, 'utf-8'); ?></th>
            <td width="90%"><?php echo $str; ?></td>
          </tr>
          <tr></tr>
        </table>
      </li>
<?php
  }
?>
    </ol>
<?php
}
?>
  </body>
</html>