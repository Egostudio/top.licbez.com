#!/usr/local/bin/php
<?php
exit;
require_once('lib/import/Import.php');


/*
 * Class ImportCron
 *
 */
$import = new ImportCron();
//$import->currentProcess();
$import->send('Start import');


$filename = 'reader/examples/000006062.yml';
//$filename = '/reader/examples/000006062-full.yml';

$file = $import->root_dir . $filename;

if(file_exists ( $file )){
    $import->send("Root dir file: " . $file);
}
else{
    $import->send("No file");
    exit();
}





/*
 * Start parsing
 * Adding parsing data json to the table "Data"
 *
 */
$import->send("Start parsing");  
$reader = new ExampleXmlReader1;
$reader->open($file);
$reader->parse();
$count_items = count($reader->arr);
$import->send("The number of categories: $count_items");  





/*
 * Delete all datas
 *
 */

if($import->delete()){
    $import->send("Delete old data");   
}





/*
 * Insert categories
 * Insert categories from data tables
 *
 */
$import->send("Start insert categories");
$k = 0;
foreach($reader->arr as $item) {
    $import->import_categories($item);
    ++$k;
}
$import->send("Number of inserted categories : $k");  




/*
 * Insert products
 * Insert products from data tables
 *
 */
$import->send("Start insert products"); 
$limit = 35; $k = 0;
$query = $import->db->placehold("SELECT * FROM __data  limit ?  ", $limit);
while($results = $import->db->query($query)) {

            $import->send("test");  

    $item1 = array();
    foreach($import->db->results() as $item)
    {
        $item1 = (array)json_decode($item->data);
        $product_id = $import->import_product($item1);
        $import->db->query("DELETE FROM __data WHERE id=?", $item->id);
        ++$k;
        if($k%500 == 0){
            $import->send("Number of inserted products : $k");  
        }
    }
    
    if(!count($item1)){
        break;
    }
}
$import->send("Inserted all products : $k");  




/*
 * End import
 *
 */
$reader->close();
$import->send("End import");

//$import->settings->update('cron_current_process', 0);
