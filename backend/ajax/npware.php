<?php
session_start();
require_once(dirname(dirname(__DIR__)).'/api/Okay.php');
$okay = new Okay();
$cityRef = $okay->request->get('city');
$request = array(
    "apiKey" => $okay->settings->newpost_key,
    "modelName" => "Address",
    "calledMethod" => "getWarehouses",
    "methodProperties" => array(
        "CityRef" => $cityRef,
        "Page" => 1
    )
);
$response = $okay->np->request_novaposhta($request);

$result = array('success'=>$response->success);
$result['warehouses'] = '<option value=""></option>';
foreach ($response->data as $i=>$warehouse) {
    $result['warehouses'] .= '<option value="'.$warehouse->Ref.'">'.$warehouse->DescriptionRu.'</option>';
}
die(json_encode($result));
?>